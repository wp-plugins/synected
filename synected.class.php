<?php
/*  Copyright 2009 Jeff Smith (email: jeff@blurbia.com)

    This file is part of Synected.

    Synected is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Synected is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Synected.  If not, see <http://www.gnu.org/licenses/>.
*/
class Synected 
{
	var $t_urls;
	var $t_allowlist;
	var $options;
	var $url;
	var $url_array;
	var $errors;
	var $success = false;
	var $blocked = false;
	var $requested_key;
	private static $instance;
		
    private function Synected() 
    {
		global $wpdb;
		$this->t_urls 			= 	$wpdb->prefix . "synected_urls";
		$this->t_allowlist		= 	$wpdb->prefix . "synected_allowlist";
		
		$this->options = array(
			'url_chars' => 'abcdefghijklmnopqrstuvwxyz0123456789$-_.+!*\'(),',
			'base_url' => get_bloginfo('url'),
			'synected_url' => 'shorten',
			'create_from_url' => true,
			'short_url_prefix' => 'u/',
			'short_url_suffix' => '',
			'require_login' => true,
			'require_permission' => true,
			'view_page_size' => 20
			);
		$db_options = get_option('synected_options');
		if (is_array($db_options))
		{ 
			foreach($db_options as $key => $val) $db_options[$key] = stripslashes($val);
			$this->options = array_merge($this->options, $db_options);
		}
		
		add_action('activate_synected/synected.php', array($this, 'install'));
		add_action('generate_rewrite_rules', array($this, 'add_rewrite_rules'));
		add_action('admin_menu', array($this, 'add_menus'));
		
		add_filter('capabilities_list', array($this, 'add_capabilities'));
    }
	public static function singleton() 
	{
	    if (!isset(self::$instance)) {
	        $c = __CLASS__;
	        self::$instance = new $c;
	    }
	
	    return self::$instance;
	}
	public function __clone()
	{
	    trigger_error('Clone operation disabled for class Synected. Synected is a Singleton.', E_USER_ERROR);
	}
	function install() 
	{
		global $wpdb, $wp_rewrite, $wp_roles;
		$wp_rewrite->flush_rules();
		
	   	if($wpdb->get_var("show tables like '$this->t_urls'") != $this->t_urls) 
	   	{
	      	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	      	dbDelta("
				CREATE TABLE $this->t_urls (
		  		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url varchar(255) NOT NULL,
				code varchar(64) NULL,
				created datetime NOT NULL,
				click_count bigint(20) unsigned NOT NULL DEFAULT 0,
				status int NOT NULL DEFAULT 1,
		  		PRIMARY KEY (id)
				) TYPE=MyISAM;
				");
			dbDelta("
				CREATE TABLE $this->t_allowlist (
		  		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				domain varchar(255) NOT NULL,
				type int NOT NULL DEFAULT 0,
		  		PRIMARY KEY (id)
				) TYPE=MyISAM;
				");
	   	}
		
		$wp_roles->add_cap('administrator', 'create_short_urls');
		$wp_roles->add_cap('administrator', 'edit_short_urls');
		$wp_roles->add_cap('administrator', 'edit_short_url_blacklist');
		$wp_roles->add_cap('administrator', 'edit_short_url_whitelist');
		
		$wp_roles->add_cap('editor', 'create_short_urls');
		$wp_roles->add_cap('editor', 'edit_short_urls');
		
		$wp_roles->add_cap('author', 'create_short_urls');
		$wp_roles->add_cap('contributor', 'create_short_urls');
	}
	function add_capabilities($caps)
	{
		$caps[] = 'create_short_urls';
		$caps[] = 'edit_short_urls';
		$caps[] = 'edit_short_url_blacklist';
		return $caps;
	}
	function add_rewrite_rules(&$rewrite)
	{
		if (!empty($this->options['synected_url']))
			$rewrite->add_external_rule($this->options['synected_url'].'$', PLUGINDIR.'/synected/create_url.php');
			
		if ($this->options['create_from_url'])
			$rewrite->add_external_rule('((http:/|https:/|www\.).+)$', PLUGINDIR.'/synected/create_url.php?url=$1');
			
		if (!empty($this->options['short_url_prefix']))
			$rewrite->add_external_rule($this->options['short_url_prefix'].
										'(.+)'.
										$this->options['short_url_suffix'].
										'$', PLUGINDIR.'/synected/url.php?code=$1');
	}
	function create_url($url, $key = '')
	{
		global $wpdb;
		$this->url = $url;
		$url = $wpdb->escape($url);
		$this->requested_key = $key;
		
		if (empty($url))
		{
			$this->errors = new WP_Error('url', 'You must specify a destination URL.');
			return false;
		}
		
		if (isset($key) && !empty($key))
		{
			$_code = $wpdb->escape($key);
			$url = $wpdb->get_row("select * from $this->t_urls where code='$_code'");
			if (!$url)
			{
				$this->url_array = array(
					'url' => $this->url,
					'code' => $key,
					'created' => date('Y-m-d H:i:s')
					);
				$wpdb->insert($this->t_urls, $this->url_array);
				$this->success = true;
			}
			else
				$this->errors = new WP_Error('code', 'The requested short URL is already in use.');
		}
		else
		{
			$this->url_array = $wpdb->get_row("select * from $this->t_urls where url='$url'", ARRAY_A);
			if (!$this->url_array)
			{
				$this->url_array = array(
					'url' => $this->url,
					'created' => date('Y-m-d H:i:s')
					);
				$wpdb->insert($this->t_urls, $this->url_array);
				$id = $wpdb->insert_id;
				$this->url_array['code'] = $this->generate_key($id);
				$wpdb->update($this->t_urls, $this->url_array, array('id' => $id));
				$this->url_array['id'] = $id;
				$this->success = true;
			}
		}
		if ($this->success) $this->short_url = $this->full_short_url($this->url_array['code']);
	}
	function generate_key($id)
	{
		$id = intval($id);
		if ($id < 1) return false;
		
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789$-_.+!*\'(),';
		$n = strlen($chars);
		$x = $id - 1;
		$code = '';
		
		while ($x >= 0)
		{
			$code = $chars[$x % $n] . $code;
			$x = floor($x / $n) - 1;
		}
		
		return $code;
	}
	function full_short_url($code)
	{
		return trailingslashit($this->options['base_url']).
			stripslashes($this->options['short_url_prefix']).
			$code.
			stripslashes($this->options['short_url_suffix']);
	}
	function get_url($code, $countclick = false)
	{
		global $wpdb;
		$_code = $wpdb->escape($code);
		$urlobj = $wpdb->get_row("select * from $this->t_urls where code='$_code'");
		
		if ($urlobj && $urlobj->status == 1)
		{ 
			if ($countclick) $wpdb->query("update $this->t_urls set click_count=click_count+1 where id=$urlobj->id");
			return $urlobj->url;
		}
		elseif ($urlobj->status == 0) $this->blocked = true;
		
		return false;
	}
	function get_url_object($code)
	{
		global $wpdb;
		$_code = $wpdb->escape($code);
		return $wpdb->get_row("select * from $this->t_urls where code='$_code'");
	}
    function block_url($url_id, $status = 0)
    {
    	global $wpdb;
    	if (!is_array($url_id)) $url_id = array($url_id);
    	$id_list = implode(', ', array_map('intval', $url_id));
    	$wpdb->query("update $this->t_urls set status=$status where id in ($id_list)");
    }
    function unblock_url($url_id)
    {
    	$this->block_url($url_id, 1);
    }
    function delete_url($url_id)
    {
    	global $wpdb;
    	if (!is_array($url_id)) $url_id = array($url_id);
    	$id_list = implode(', ', array_map('intval', $url_id));
    	$wpdb->query("delete from $this->t_urls where id in ($id_list)");
    }
	function can_create_url()
	{
		if (!$this->options['require_login']) return true;
		if (is_user_logged_in() && !$this->options['require_permission']) return true;
		if (current_user_can('create_short_urls')) return true;
		return false;
	}
	function hook_theme()
	{
		global $wp_query;
		if ($this->can_create_url() && !$this->blocked)
		{
			$wp_query->is_page = true;
			$wp_query->in_the_loop = true;
			$wp_query->post_count = 1;
		}
		else
			$wp_query->is_404 = true;

		add_filter('wp_title', array($this, 'wp_title'), 10, 3);
		add_filter('the_title', array($this, 'the_title'));
		add_filter('the_content', array($this, 'the_content'));
		
		remove_all_actions('template_redirect');
		add_action('template_redirect', array($this, 'template_redirect'));
	}
	function wp_title($title, $sep, $seplocation)
	{
		if ($seplocation == 'right') return 'Create Short URL'." $sep ";
		else return " $sep ".'Create Short URL';
	}
	function the_title()
	{
		return 'Create Short URL';
	}
	function the_content()
	{
		$short_url_base = trailingslashit($this->options['base_url']);
		$url_prefix = stripslashes($this->options['short_url_prefix']);
		$url_suffix = stripslashes($this->options['short_url_suffix']);
		
		$short_url = $short_url_base.$url_prefix.$this->url_array['code'].$url_suffix;
		$requested_short_url = isset($this->requested_key) ? 
			$short_url_base.$url_prefix.$this->requested_key.$url_suffix : $short_url;
		$synected_url = trailingslashit(get_bloginfo('url')).$this->options['synected_url'];
		
		if (!is_wp_error($this->errors)) :
			if (isset($_GET['url'])) : 
		
				$content = <<<SYNECTED_CONTENT
				
				<p>Your shortcut was successfully created!</p>
				<p>The original web address:<br /><strong>$this->url</strong></p>
				<p>Is now accessible at the following short url:<br /><a href="$short_url"><strong>$short_url</strong></a></p>
				<p><a href="$synected_url">Create another URL</a></p>
				
SYNECTED_CONTENT;

			else :
			
				$content = <<<SYNECTED_CONTENT
				
				<p>
					To create a shortened URL, enter the full destination address (including the <tt>http://</tt>) 
					in the box below. You may optionally specify a shortcut key, which will be used in the form 
					<tt>{$short_url_base}your_shortcut_key</tt>. If you do not specify a key, the shortest possible 
					code will be generated.
				</p>
				
				<form method="get" action="">
					<p><label>Destination URL: <br/>
						<input name="url" type="text" size="50" /></label></p>
						
					<p><label>Shortcut Key (Optional): <br/>
						<input name="key" type="text" /></label></p>
						
					<p><input type="submit" class="submit button" value="Create URL" />
				</form>
				
SYNECTED_CONTENT;

			endif;
		else :
		
			foreach ( $this->errors->get_error_codes() as $code ) {
				foreach ( $this->errors->get_error_messages($code) as $error ) {
					$error_message[] = '<p class="error">'.$error.'</p>';
				}
			}
			$error_message = implode("\n", $error_message);
			
			$content = <<<SYNECTED_CONTENT
			
			<p>There was a problem creating your short URL.</p>
			<p>
				Destination URL: <strong>$this->url</strong><br />
				Requested short URL: <strong>$requested_short_url</strong>
			</p>
			$error_message
			
SYNECTED_CONTENT;
		
		endif;
		
		return $content;
	}
	function template_redirect()
	{
		if ($template = get_query_template('synected'))
		{
			include($template);
			exit;
		}
	}
	function add_menus()
	{
		add_options_page("Synected", "Synected", 'manage_options', basename(__FILE__).'/options', array($this, 'options_page'));
		$cap = $this->options['require_permission'] ? 'create_short_urls' : 'edit_posts';
		
		add_menu_page("Short URLs", "Short URLs", $cap, basename(__FILE__).'/manage', array($this, 'view_urls_page'));
		add_submenu_page(basename(__FILE__).'/manage', 'Manage Short URLs', 'Edit', 'edit_short_urls', basename(__FILE__).'/manage', array($this, 'view_urls_page'));
		add_submenu_page(basename(__FILE__).'/manage', 'Create Short URL', 'Add New', $cap, basename(__FILE__).'/create', array($this, 'create_url_page'));
		
		//--incomplete--
		//add_submenu_page(basename(__FILE__).'/create', 'URL Blacklist', 'URL Blacklist', 'edit_short_url_blacklist', basename(__FILE__).'/blacklist', array($this, 'view_blacklist_page'));
		
		if (isset($_GET['page']) && strpos($_GET['page'], basename(__FILE__)) === 0)
		{
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_head', array($this, 'admin_head'));
		}
	}
    function options_page()
    {
		if (isset($_POST['Submit']))
		{
			foreach($this->options as $key => $val) 
			{
				if (isset($_POST["var_$key"]))
				{
					$this->options[$key] = $_POST["var_$key"];
					$update = true;
				}
			}
			if ($update)
			{
				update_option('synected_options', $this->options);
				foreach($this->options as $key => $val) $this->options[$key] = stripslashes($val);
				global $wp_rewrite;
				$wp_rewrite->flush_rules();
			}
			$updateMessage = 'Options saved'."<br />";
		}
		if (isset($updateMessage) && $update) 
			echo '<div id="message" class="updated fade"><p><strong>'.__($updateMessage).'</strong></p></div>';
		?>
    	<div class="wrap">
    		<h2>Synected Settings</h2>
	    	<form method="post" action="">
				<table class="form-table">
					
					<tr valign="top">
						<th scope="row"><label for="var_base_url">Base URL</label></th>
						<td>
							<input type="text" name="var_base_url" id="var_base_url" 
								value="<?php echo $this->options['base_url']; ?>" class="regular-text" />
							<br />
							<span class="setting-description">
								The domain off of which all short URLs are built. In most cases, you 
								should not need to change this. Can be used to remove the 'www' if it is
								part of your normal Home URL in Wordpress.
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_synected_url">Synected URL</label></th>
						<td>
							<input type="text" name="var_synected_url" id="var_synected_url" 
								value="<?php echo $this->options['synected_url']; ?>" class="regular-text" />
							<br />
							<span class="setting-description">
								The path for the custom page that allows your blog visitors to create shortened URLs. 
								If 'require_login' is enabled, this path will result in a 404 for non-logged-in visitors.
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_short_url_prefix">Short URL Prefix</label></th>
						<td>
							<input type="text" name="var_short_url_prefix" id="var_short_url_prefix" 
								value="<?php echo $this->options['short_url_prefix']; ?>" class="regular-text" />
							<br />
							<span class="setting-description">
								Required. This prefix is added to the front of all short URLs. It separates your 
								short URLs from the posts and pages on your site. Be sure to choose something that 
								your regular permalinks would never begin with - for this reason, it's advisable to 
								end your prefix with a forward slash (<tt>/</tt>). This setting makes use of regular 
								expressions and follows standard Rewrite Rules. It is necessary to escape any 
								RegEx special characters, a full list of which can be 
								<a href="http://www.php.net/manual/en/regexp.reference.php">found here</a>. For example, 
								the <tt>(</tt> character would be specified as <tt>\(</tt>.
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_short_url_suffix">Short URL Suffix</label></th>
						<td>
							<input type="text" name="var_short_url_suffix" id="var_short_url_suffix" 
								value="<?php echo $this->options['short_url_suffix']; ?>" class="regular-text" />
							<br />
							<span class="setting-description">
								Optional. This suffix is appended to the end of all short URLs. Follows the same 
								regular expression escaping rules as the Prefix.
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_url_chars">Allowed URL Characters</label></th>
						<td>
							<input type="text" name="var_url_chars" id="var_url_chars" 
								value="<?php echo $this->options['url_chars']; ?>" class="regular-text" />
							<br />
							<span class="setting-description">
								For advanced tweaking - in most cases, you will not need to change this. The URL codes 
								are generated using the full set of specified characters. The more available characters, 
								the more combinations are possible with a shorter total length. However, not all 
								characters are URL-safe. Remove a character if you find it causes problems with your 
								web server configuration.
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_create_from_url">Enable creation from address bar?</label></th>
						<td>
							<input type="checkbox" name="var_create_from_url" id="var_create_from_url" value="1"
								<?php if ($this->options['create_from_url']) echo 'checked="checked"'; ?> />
							<span class="setting-description">
								Whether to enable short URL creation by adding the blog home URL to the front of an 
								address, for example: <tt>http://blurbia.com/http://wordpress.org/</tt>
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_require_login">Require login?</label></th>
						<td>
							<input type="checkbox" name="var_require_login" id="var_require_login" value="1"
								<?php if ($this->options['require_login']) echo 'checked="checked"'; ?> />
							<span class="setting-description">
								Whether to restrict URL creation to users logged into the Wordpress admin panel.
							</span>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="var_require_permission">Require permission?</label></th>
						<td>
							<input type="checkbox" name="var_require_permission" id="var_require_permission" value="1"
								<?php if ($this->options['require_permission']) echo 'checked="checked"'; ?> />
							<span class="setting-description">
								Whether to restrict URL creation to users that have been given the 'Create Short URLs' 
								capability.
							</span>
						</td>
					</tr>
					
	    		</table>
	    		
				<p class="submit">
				<input type="submit" class="button-primary" name="Submit" value="<?= __('Save Settings');?>" />
				</p>
			</form>
    	</div>
    	<?php
    }
    function admin_init()
    {
    	switch($_GET['page'])
    	{
    		case basename(__FILE__).'/manage':
				if (!current_user_can('edit_short_urls')) die('Access denied.');
		    	if (isset($_GET['doaction']))
		    	{
		    		if ($_GET['action'] == 'block')
		    		{
		    			if (is_array($_GET['url'])) $this->block_url($_GET['url']);
		    		}
		    		elseif ($_GET['action'] == 'unblock')
		    		{
		    			if (is_array($_GET['url'])) $this->unblock_url($_GET['url']);
		    		}
		    		elseif ($_GET['action'] == 'delete')
		    		{
		    			if (is_array($_GET['url'])) $this->delete_url($_GET['url']);
		    		}
		    		wp_redirect('?page='.$_GET['page']);
		    	}
		    	break;
    	}
    }
    function admin_head()
    {
    	?>
    	<style type="text/css">
    		tr.noresults td { padding:60px 40px; font-style:italic; }
    		tr.blocked td.col-status { color:red; }
    	</style>
    	<script type="text/javascript">
    		function blockURL(id)
    		{
    			var action = jQuery('#block-link-'+id).html() == 'Block' ? 'block' : 'unblock';
    			jQuery.get('<?php echo WP_PLUGIN_URL; ?>/synected/ajax.php', 
    				{ synected_action:action, url_id:id }, 
    				function(html) {
    					if (html == 'ACK')
    					{
    						if (jQuery('#block-link-'+id).html() == 'Block')
    						{
    							jQuery('#url-row-'+id).addClass('blocked');
    							jQuery('#url-status-'+id).html('Blocked');
    							jQuery('#block-link-'+id).html('Unblock');
    						}
    						else
    						{
    							jQuery('#url-row-'+id).removeClass('blocked');
    							jQuery('#url-status-'+id).html('Enabled');
    							jQuery('#block-link-'+id).html('Block');
    						}
    					}
    				}
    			);
    			return false;
    		}
    		function deleteURL(id)
    		{
    			jQuery.get('<?php echo WP_PLUGIN_URL; ?>/synected/ajax.php', 
    				{ synected_action:'delete', url_id:id }, 
    				function(html) {
    					if (html == 'ACK')
    						jQuery('#url-row-'+id).css('display', 'none');
    				}
    			);
    			return false;
    		}
    	</script>
    	<?php
    }
    function create_url_page()
    {
		if (isset($_POST['Submit']))
		{
			$url = $_POST['dest_url'];
			if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) $url = "http://$url";

			$this->create_url($url, $_POST['url_code']);
			
			$updateMessage = '<strong>URL created</strong><br />'.
				'Your new shortened url is: <a href="'.$this->short_url.'">'.$this->short_url.'</a>';
		}
		if (isset($updateMessage)) 
			echo '<div id="message" class="updated fade"><p>'.__($updateMessage).'</p></div>';
    	?>
    	<div class="wrap">
	    	<h2>Create Shortened URL</h2>
	    	<blockquote style="width:500px;">
	    		Enter the full desination url you would like shortened, including the 'http://'. If you want to use 
	    		a specific shortcut key, enter it in the box below, or to simply use the shortest available code, leave 
	    		it blank.
	    	</blockquote>
	    	<form method="post" action="">
				<table class="form-table">
					
					<tr valign="top">
						<th scope="row"><label for="dest_url">Destination URL</label></th>
						<td><input type="text" name="dest_url" id="dest_url" value="" class="regular-text" /></td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><label for="url_code">Shortcut Key</label></th>
						<td><input type="text" name="url_code" id="url_code" value="" class="regular-text" /></td>
					</tr>
					
				</table>
				
				<p class="submit">
				<input type="submit" class="button-primary" name="Submit" value="<?= __('Create URL');?>" />
				</p>
	    	</form>
	    </div>
	    <?php
    }
    function view_urls_page()
    {
    	global $wpdb, $current_user;
    	
    	$page_size = intval($this->options['view_page_size']);
    	if ($page_size < 10) $page_size = 10;
    	$paged = intval($_GET['paged']);
    	if ($paged < 1) $paged = 1;
	
    	if ($_GET['view'] == 'enabled') $where = 'and status = 1 ';
    	elseif ($_GET['view'] == 'blocked') $where = 'and status = 0 ';
    	else $where = '';
    	
    	$url_count = $wpdb->get_var("select count(*) from $this->t_urls");
    	$url_blocked_count = $wpdb->get_var("select count(*) from $this->t_urls where status = 0");
	    $url_unblocked_count = $url_count - $url_blocked_count;
	    	
    	$page_count = ceil($url_count / $page_size);
    	if ($paged > $page_count) $paged = $page_count;
    	
    	$offset = ($paged - 1) * $page_size;
    	$urls = $wpdb->get_results(
			"select * from $this->t_urls where 1=1 $where order by created desc, id desc limit $offset, $page_size"
			);
    	?>
    	<div class="wrap">
	    	<h2>Shortened URLs</h2>
	    	<form method="get" action="">
	    		<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
	    		<ul class="subsubsub">
					<li><a<?php if (empty($_GET['view'])) echo ' class="current"'; ?> href="?page=<?php echo basename(__FILE__).'/manage'; ?>">All <span class="count">(<?php echo $url_count; ?>)</span></a> |</li>
					<li><a<?php if ($_GET['view'] == 'enabled') echo ' class="current"'; ?> href="?page=<?php echo basename(__FILE__).'/manage'; ?>&amp;view=enabled">Enabled <span class="count">(<?php echo $url_unblocked_count; ?>)</span></a> |</li>
					<li><a<?php if ($_GET['view'] == 'blocked') echo ' class="current"'; ?> href="?page=<?php echo basename(__FILE__).'/manage'; ?>&amp;view=blocked">Blocked <span class="count">(<?php echo $url_blocked_count; ?>)</span></a></li>
				</ul>
	    		<div class="tablenav">
					<?php if (current_user_can('edit_short_urls')) : ?>
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="-1">Bulk Actions</option>
							<option value="block">Block</option>
							<option value="unblock">Unblock</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply" />
					</div>
		    		<?php endif; ?>
		    		<div class="tablenav-pages">
		    			<span class="displaying-num">Displaying 
		    				<?php echo ($url_count == 0 ? 0 : ($offset+1).'-'.
		    						($page_count > 1 ? $offset+$page_size+1 : $url_count)).
									' of '.$url_count ?></span>
		    			<?php if ($page_count > 1) :
		    			for ($i=1; $i<=$page_count; $i++) : ?>
		    			<a class="page-numbers" href="?paged=<?php echo $i ?>"><?php echo $i ?></a>
		    			<?php endfor;
		    			endif; ?>
		    		</div>
		    		<div class="clear"></div>
		    	</div>
		    	<div class="clear"></div>
		    	<table class="widefat">
		    		<thead>
		    			<tr>
		    				<th class="manage-column check-column" scope="col"><input type="checkbox"/></th>
		    				<th class="manage-column">Short URL</th>
		    				<th class="manage-column">Destination</th>
		    				<th class="manage-column">Clicked</th>
		    				<th class="manage-column">Status</th>
		    				<th class="manage-column">Created</th>
		    			</tr>
		    		</thead>
		    		<tfoot>
		    			<tr>
		    				<th class="manage-column check-column" scope="col"><input type="checkbox"/></th>
		    				<th class="manage-column">Short URL</th>
		    				<th class="manage-column">Destination</th>
		    				<th class="manage-column">Clicked</th>
		    				<th class="manage-column">Status</th>
		    				<th class="manage-column">Created</th>
		    			</tr>
		    		</tfoot>
		    		<tbody>
		    		<?php 
		    		if (count($urls) > 0) :
		    		
		    			$odd = true; 
		    			foreach ($urls as $url) : 
		    			
		    			$classes = array();
		    			if ($odd) $classes[] = 'alternate';
		    			$odd = !$odd;
		    			
		    			if ($url->status == 0) $classes[] = 'blocked';
		    			
		    			$short_url = $this->full_short_url($url->code);
		    			?>
		    			<tr id="url-row-<?php echo $url->id; ?>"<?php 
		    				if (count($classes) > 0) echo ' class="'.implode(' ', $classes).'"'; ?>>
		    				<th class="check-column" scope="row">
								<input type="checkbox" value="<?php echo $url->id; ?>" name="url[]"/>
							</th>
		    				<td>
		    					<a href="<?php echo $short_url; ?>" target="_blank"><?php echo $short_url; ?></a>
		    					<?php if (current_user_can('edit_short_urls')) : ?>
		    					<div class="row-actions" style="padding-top:8px;">
		    						<span><a id="block-link-<?php echo $url->id; ?>" href="#" 
		    							onclick="return blockURL(<?php echo $url->id; ?>);"><?php 
		    							echo ($url->status == 1) ? 'Block' : 'Unblock'; ?></a> | </span>
		    						<span><a href="#" onclick="return deleteURL(<?php echo $url->id; ?>);">Delete</a></span>
		    					</div>
		    					<?php endif; ?>
		    				</td>
		    				<td><?php echo $url->url; ?></td>
		    				<td><?php echo $url->click_count; ?></td>
		    				<td id="url-status-<?php echo $url->id; ?>" class="col-status"><?php 
		    					echo $url->status == 1 ? 'Enabled' : 'Blocked'; ?></td>
		    				<td><?php echo date('g:ia (n/j)', strtotime($url->created)); ?></td>
		    			</tr>
		    			<?php endforeach; 
		    		else : ?>
		    			<tr class="noresults">
		    				<td colspan="6">No urls found.</td>
		    			</tr>
		    		<?php endif; ?>
		    		</tbody>
		    	</table>
		    </form>
	    	<form method="get" action="">
	    		<div class="tablenav">
					<?php if (current_user_can('edit_short_urls')) : ?>
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="-1">Bulk Actions</option>
							<option value="block">Block</option>
							<option value="unblock">Unblock</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply" />
					</div>
		    		<?php endif; ?>
		    		<div class="tablenav-pages">
		    			<span class="displaying-num">Displaying 
		    				<?php echo ($url_count == 0 ? 0 : ($offset+1).'-'.
		    						($page_count > 1 ? $offset+$page_size+1 : $url_count)).
									' of '.$url_count ?></span>
		    			<?php if ($page_count > 1) :
		    			for ($i=1; $i<=$page_count; $i++) : ?>
		    			<a class="page-numbers" href="?paged=<?php echo $i ?>"><?php echo $i ?></a>
		    			<?php endfor;
		    			endif; ?>
		    		</div>
		    		<div class="clear"></div>
		    	</div>
		    </form>
    	</div>
    	<?php
    }
    function view_blacklist_page()
    {
    	global $wpdb, $current_user;
    	
    	$page_size = intval($this->options['view_page_size']);
    	if ($page_size < 10) $page_size = 10;
    	$paged = intval($_GET['paged']);
    	if ($paged < 1) $paged = 1;
    	
    	$url_count = $wpdb->get_var("select count(*) from $this->t_allowlist where type=0");
    	
    	$page_count = ceil($url_count / $page_size);
    	if ($paged > $page_count) $paged = $page_count;
    	
    	$offset = ($paged - 1) * $page_size;
    	$urls = $wpdb->get_results(
			"select * from $this->t_allowlist where type=0 order by id desc limit $offset, $page_size"
			);
    	?>
    	<div class="wrap">
	    	<h2>Short URL Domain Blacklist</h2>
	    	<form method="get" action="">
	    		<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
	    		<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="-1">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply" />
					</div>
		    		<div class="tablenav-pages">
		    			<span class="displaying-num">Displaying 
		    				<?php echo ($url_count == 0 ? 0 : ($offset+1).'-'.
		    						($page_count > 1 ? $offset+$page_size+1 : $url_count)).
									' of '.$url_count ?></span>
		    			<?php if ($page_count > 1) :
		    			for ($i=1; $i<=$page_count; $i++) : ?>
		    			<a class="page-numbers" href="?paged=<?php echo $i ?>"><?php echo $i ?></a>
		    			<?php endfor;
		    			endif; ?>
		    		</div>
		    		<div class="clear"></div>
		    	</div>
		    	<div class="clear"></div>
		    	<table class="widefat">
		    		<thead>
		    			<tr>
		    				<th class="manage-column check-column" scope="col"><input type="checkbox"/></th>
		    				<th class="manage-column">Domain</th>
		    			</tr>
		    		</thead>
		    		<tfoot>
		    			<tr>
		    				<th class="manage-column check-column" scope="col"><input type="checkbox"/></th>
		    				<th class="manage-column">Domain</th>
		    			</tr>
		    		</tfoot>
		    		<tbody>
		    		<?php 
		    		if (count($urls) > 0) :
		    		
		    			$odd = true; 
		    			foreach ($urls as $url) : 
		    			
		    			$classes = array();
		    			if ($odd) $classes[] = 'alternate';
		    			$odd = !$odd;
		    			if ($url->wp_user_id) $classes[] = 'read';
		    			?>
		    			<tr id="url-row-<?php echo $url->id; ?>"<?php 
		    				if (count($classes) > 0) echo ' class="'.implode(' ', $classes).'"'; ?>>
		    				<th class="check-column" scope="row">
								<input type="checkbox" value="<?php echo $url->id; ?>" name="url[]"/>
							</th>
		    				<td><?php echo $url->domain; ?></td>
		    			</tr>
		    			<?php endforeach; 
		    		else : ?>
		    			<tr class="noresults">
		    				<td colspan="2">No blacklisted domains were found.</td>
		    			</tr>
		    		<?php endif; ?>
		    		</tbody>
		    	</table>
	    		<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="-1">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply" />
					</div>
		    		<div class="tablenav-pages">
		    			<span class="displaying-num">Displaying 
		    				<?php echo ($url_count == 0 ? 0 : ($offset+1).'-'.
		    						($page_count > 1 ? $offset+$page_size+1 : $url_count)).
									' of '.$url_count ?></span>
		    			<?php if ($page_count > 1) :
		    			for ($i=1; $i<=$page_count; $i++) : ?>
		    			<a class="page-numbers" href="?paged=<?php echo $i ?>"><?php echo $i ?></a>
		    			<?php endfor;
		    			endif; ?>
		    		</div>
		    		<div class="clear"></div>
		    	</div>
		    </form>
    	</div>
    	<?php
    }
}

global $synected;
$synected = Synected::singleton();
?>
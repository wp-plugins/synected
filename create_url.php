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
if (!defined('ABSPATH')) {
	for ($i = 0; $i < 40; $i++) {
		$configfile = str_repeat('../', $i).'wp-config.php';
		if (file_exists($configfile)) {
			require_once($configfile);
			break;
		}
	}
}

global $synected;
if (!$synected->can_create_url() && stripos($_SERVER['REQUEST_URI'], get_bloginfo('url')) !== 0)
{
	if ($synected->options['create_from_url']) $redirect = trailingslashit(get_bloginfo('url')).$_GET['url'];
	else $redirect = trailingslashit(get_bloginfo('url')).$synected->options['synected_url'].'?url='.$_GET['url'];
	
	wp_redirect($redirect);
}
if ($synected->can_create_url() && isset($_GET['url']))
{
	if (!empty($_GET['url']))
	{
		$url = $_GET['url'];
		if (strpos($url, 'http://') === false && strpos($url, 'http:/') !== false) $url = str_replace('http:/', 'http://', $url);
		if (strpos($url, 'https://') === false && strpos($url, 'https:/') !== false) $url = str_replace('https:/', 'https://', $url);
		if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) $url = "http://$url";
	}
	$synected->create_url($url, $_GET['key']);
}

//Attempt to hijack the theme 
$synected->hook_theme();

define('WP_USE_THEMES', true);
require(ABSPATH.WPINC.'/template-loader.php');

//die($url);
?>
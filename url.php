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

global $synected, $wp_rewrite;

$code = ($wp_rewrite->using_permalinks() ? $_GET['code'] : get_query_var($synected->options['url_query_var']));
$url = $synected->get_url($code, true);

if ($url)
	wp_redirect($url, 301);
else
{
	$synected->hook_theme();

	define('WP_USE_THEMES', true);
	require(ABSPATH.WPINC.'/template-loader.php');
}
?>
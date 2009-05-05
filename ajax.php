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
if (!defined('ABSPATH')) die('Could not load Wordpress.');

if (!current_user_can('edit_short_urls')) die('Access Denied.');

global $synected;
if (isset($_GET['synected_action']))
{
	if (isset($_GET['url_id'])) $url_id = intval($_GET['url_id']);
	elseif (isset($_GET['format_index'])) $format_index = intval($_GET['format_index']);
	else die();
	
	switch($_GET['synected_action'])
	{
		case 'block':
			$synected->block_url($url_id);
			echo 'ACK';
			break;
		case 'unblock':
			$synected->unblock_url($url_id);
			echo 'ACK';
			break;
		case 'delete':
			$synected->delete_url($url_id);
			echo 'ACK';
			break;
		case 'delete_format':
			$synected->delete_format($format_index);
			echo 'ACK';
			break;
	}
}
?>
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
function synected_destination_url()
{
	global $synected;
	echo $synected->url;
}
function synected_shortened_url()
{
	global $synected;
	echo $synected->full_short_url($synected->url_array['code']);
}
function synected_requested_shortened_url()
{
	global $synected;
	echo $synected->full_short_url($synected->requested_key);
}
function synected_successful_creation()
{
	global $synected;
	return $synected->success;
}
function get_synected_errors()
{
	global $synected;
	return $synected->errors;
}
function synected_errors()
{
	global $synected;
	foreach ( $synected->errors->get_error_codes() as $code ) {
		foreach ( $synected->errors->get_error_messages($code) as $error ) {
			$error_message[] = '<p class="error">'.$error.'</p>';
		}
	}
	echo implode("\n", $error_message);
}
function synected_base_url()
{
	global $synected;
	echo trailingslashit($synected->options['base_url']);
}
function synected_creation_url()
{
	global $synected;
	echo trailingslashit(get_bloginfo('url')).$synected->options['synected_url'];
}
?>
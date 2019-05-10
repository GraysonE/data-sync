<?php namespace DataSync;
/**
 * Plugin Name: Data Sync
 * Version:     1.0.0
 * Description: Synchronizes all post data, custom ACF fields, and Yoast data across multiple, authenticated sites.
 * Author:      Copper Leaf Creative
 * Author URI:  https://copperleafcreative.com
 * Text Domain: data-sync
 * Domain Path: /languages/
 * License:     GPL v3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'DataSync\add_action_links' );

function add_action_links ( $links ) {
  $mylinks = array(
      '<a href="' . admin_url( 'options-general.php?page=data-sync-settings' ) . '">Settings</a>',
  );
  return array_merge( $links, $mylinks );
}

if ( ! function_exists( 'add_filter' ) ) {
  header( 'Status: 403 Forbidden' );
  header( 'HTTP/1.1 403 Forbidden' );
  exit();
}

if ( ! defined( 'DATA_SYNC_PATH' ) ) {
  define( 'DATA_SYNC_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DATA_SYNC_URL' ) ) {
	define( 'DATA_SYNC_URL', plugin_dir_url( __FILE__ ) );
}

// Load the plugin files.
require_once (DATA_SYNC_PATH . 'includes/load.php');
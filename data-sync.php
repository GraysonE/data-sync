<?php

namespace DataSync;

use DataSync\Controllers\Load;
use DataSync\Controllers\SyncedPosts;
use DataSync\Models\ConnectedSite;
use DataSync\Models\SyncedPost;

/**
 * Plugin Name: Data Sync
 * Version:     1.0.0
 * Description: Securely synchronizes all post data, custom ACF fields, and Yoast data across multiple, authorized sites. Dependent on these plugins: ACF-pro, JWT Authentication for WP REST API, and CPT UI
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

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * @param $links
 *
 * Adds Settings link to the plugin on the plugin page
 *
 * @return array
 */
function add_settings_link( $links ) {
	$my_links = array(
		'<a href="' . admin_url( 'options-general.php?page=data-sync-options' ) . '">Settings</a>',
	);

	return array_merge( $links, $my_links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\add_settings_link' );

if ( ! defined( 'DATA_SYNC_PATH' ) ) {
	define( 'DATA_SYNC_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DATA_SYNC_URL' ) ) {
	define( 'DATA_SYNC_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'DATA_SYNC_BASENAME' ) ) {
	define( 'DATA_SYNC_BASENAME', 'data-sync' );
}

if ( ! defined( 'DATA_SYNC_API_BASE_URL' ) ) {
	define( 'DATA_SYNC_API_BASE_URL', 'data-sync/v1' );
}

// Load the plugin classes.
if ( file_exists( DATA_SYNC_PATH . 'vendor/autoload.php' ) ) {
	require_once DATA_SYNC_PATH . 'vendor/autoload.php';
}

add_filter( 'https_local_ssl_verify', '__return_true' );

new Load();

register_activation_hook( __FILE__, 'flush_rewrite_rules' );

$connected_site = new ConnectedSite();
$synced_post    = new SyncedPost();
register_activation_hook( __FILE__, [ $connected_site, 'create_db_table' ] );
register_activation_hook( __FILE__, [ $synced_post, 'create_db_table' ] );

//// Setting a custom timeout value for cURL. Using a high value for priority to ensure the function runs after any other added to the same action hook.
//add_action('http_api_curl', __NAMESPACE__ . '\custom_curl_timeout', 9999, 1);
//function custom_curl_timeout( $handle ){
//	curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 15 ); // 15 seconds. Too much for production, only for testing.
//	curl_setopt( $handle, CURLOPT_TIMEOUT, 15 ); // 15 seconds. Too much for production, only for testing.
//}
//// Setting custom timeout for the HTTP request
//add_filter( 'http_request_timeout', __NAMESPACE__ . '\custom_http_request_timeout', 9999 );
//function custom_http_request_timeout( $timeout_value ) {
//	return 15; // 15 seconds. Too much for production, only for testing.
//}
//// Setting custom timeout in HTTP request args
//add_filter('http_request_args', __NAMESPACE__ . '\custom_http_request_args', 9999, 1);
//function custom_http_request_args( $r ){
//	$r['timeout'] = 15; // 15 seconds. Too much for production, only for testing.
//	return $r;
//}
//
add_action( 'http_api_debug', function( $response, $type, $class, $args, $url ) {
//	echo '<pre>';
//	print_r( 'Request URL: ' . var_export( $url, true ) );
//	print_r( 'Request Args: ' . var_export( $args, true ) );
//	print_r( 'Request Response : ' . var_export( $response, true ) );
//	echo '</pre>';
}, 10, 5 );
<?php

namespace DataSync\Controllers;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use stdClass;

/**
 * Class Options
 * @package DataSync\Controllers
 *
 * Controller class for Options
 *
 * Doesn't need model because model is abstracted by WordPress core functionality
 */
class Options {

	/**
	 * Table prefix to save custom options
	 *
	 * @var string
	 */
	protected static $table_prefix = 'data_sync_';
	/**
	 * Option key to save options
	 *
	 * @var string
	 */
	protected static $option_key = 'option';
	/**
	 * Default options
	 *
	 * @var array
	 */
	protected static $defaults = array();

	public $view_namespace = 'DataSync';

	/**
	 * Options constructor.
	 */
	public function __construct() {
		require_once DATA_SYNC_PATH . 'views/admin/options/page.php';
		require_once DATA_SYNC_PATH . 'views/admin/options/fields.php';
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Get saved options
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public static function get( WP_REST_Request $request ) {

		$key = $request->get_url_params()[ self::$option_key ];

		if ( 'secret_key' === $key ) {
			$response = new WP_REST_Response();
			$response->set_status( 401 );

			return $response;
		}

		if ( ! isset( $key ) ) {
			return rest_ensure_response( $key );
		}

		$response = new WP_REST_Response( get_option( $key, array() ) );
		$response->set_status( 201 );

		return $response;
	}

	public static function source() {

		$options = new stdClass();

		if ( function_exists( 'cptui_get_post_type_data' ) ) {

			$cpt_data = cptui_get_post_type_data();

			foreach ( get_option( 'push_enabled_post_types' ) as $post_type ) {
				if ( 'post' === $post_type ) {
					$options->push_enabled_post_types['post'] = array( 'post' => array() );
				} else {
					$options->push_enabled_post_types[ $post_type ] = $cpt_data[ $post_type ];
				}
			}
		}

		$options->enable_new_cpts                     = (bool) get_option( 'enable_new_cpts' );
		$options->overwrite_receiver_post_on_conflict = (bool) get_option( 'overwrite_receiver_post_on_conflict' );
		$options->debug                               = (bool) get_option( 'debug' );
		$options->show_body_responses                 = (bool) get_option( 'show_body_response' );

		return $options;

	}

	public static function receiver() {
		$option_keys = array(
			'notified_users',
			'enabled_post_types',
		);

		return Options::get_all( $option_keys );
	}

	public static function get_all( array $option_keys ) {
		$options = new stdClass();

		foreach ( $option_keys as $key ) {
			$options->$key = get_option( $key );
		}

		return $options;
	}

	/**
	 * Save options
	 *
	 *
	 * @param array $options
	 */
	public static function save( WP_REST_Request $request ) {

		$key  = $request->get_url_params()[ self::$option_key ];
		$data = $request->get_json_params();

		$success = update_option( $key, $data );

		if ( $success ) {
			wp_send_json_success( $data );
		} else {
			$log = new Logs( 'ERROR: Options not saved.', true );
			unset( $log );
			wp_send_json_error();
		}
	}

	/**
	 * Add admin menu
	 */
	public function admin_menu() {
		add_options_page(
			'Data Sync',
			'Data Sync',
			'manage_options',
			'data-sync-options',
			$this->view_namespace . '\data_sync_options_page'
		);
	}

	public function get_settings_tab_html( WP_REST_Request $request ) {
		$settings_tab     = $request->get_param( 'tab' );
		$settings_content = new stdClass();

		if ( 'syndicated_posts' === $settings_tab ) {
			require_once DATA_SYNC_PATH . 'views/admin/options/status-dashboard.php';
			\DataSync\display_syndicated_posts();
		} elseif ( 'connected_sites' === $settings_tab ) {
			require_once DATA_SYNC_PATH . 'views/admin/options/fields.php';
			\DataSync\display_connected_sites();
		} elseif ( 'enabled_post_types' === $settings_tab ) {
			require_once DATA_SYNC_PATH . 'views/admin/options/enabled-post-types-dashboard.php';
			\DataSync\display_enabled_post_types();
		} elseif ( 'templates' === $settings_tab ) {
			require_once DATA_SYNC_PATH . 'views/admin/options/template-sync.php';
			\DataSync\display_synced_templates();
		}

	}


	public function create_admin_notice( WP_REST_Request $request ) {
		$params  = $request->get_params();
		$output  = '';
		$success = $params['result'];
		$topic   = $params['topic'];

		if ( $success ) {
			$output .= '<div class="notice updated notice-success is-dismissible">';

			if ( 'Enabled post types' === $topic ) {
				$output .= '<p>' . $topic . ' saved successfully.</p>';
			} elseif ( 'Connected sites' === $topic ) {
				if ( ! empty( $params['message'] ) ) {
					$output .= '<p>' . $params['message'] . '</p>';
				} else {
					$output .= '<p>' . $topic . ' saved successfully.</p>';
				}

			} elseif ( ( 'Post' === $topic ) || ( 'Posts' === $topic ) ) {
				$output .= '<p>' . $topic . ' successfully syndicated.</p>';
			} elseif ( 'Templates' === $topic ) {
				$output .= '<p>' . $topic . ' successfully syndicated.</p>';
			}


		} else {
			$output .= '<div class="notice notice-warning is-dismissible">';

			if ( 'Enabled post types' === $topic ) {
				$output .= '<p>' . $topic . ' data is identical to saved data.</p>';
			} elseif ( 'Connected sites' === $topic ) {
				$output .= '<p>' . $topic . ' not saved.</p>';
			} elseif ( ( 'Post' === $topic ) || ( 'Posts' === $topic ) ) {
				$output .= '<p>' . $topic . ' not syndicated.</p>';
			} elseif ( 'Templates' === $topic ) {
				$output .= '<p>' . $topic . ' not syndicated.</p>';
			}

		}

		$output .= '<button type="button" class="notice-dismiss">';
		$output .= '<span class="screen-reader-text">Dismiss this notice.</span>';
		$output .= '</button>';

		$output .= '</div>';

		wp_send_json_success( $output );

	}


	public function register_routes() {
		$registered = register_rest_route(
			DATA_SYNC_API_BASE_URL,
			'/options/(?P<option>[a-zA-Z-_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( __NAMESPACE__ . '\Auth', 'permissions' ),
					'args'                => array(
						'option' => array(
							'description' => 'Option key',
							'type'        => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( __NAMESPACE__ . '\Auth', 'permissions' ),
					'args'                => array(
						'option' => array(
							'description' => 'Option key',
							'type'        => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( __NAMESPACE__ . '\Auth', 'permissions' ),
					'args'                => array(
						'option' => array(
							'description' => 'Option key',
							'type'        => 'string',
//							'validate_callback' => function ( $param, $request, $key ) {
//								return true;
//							},
						),
					),
				),
			)
		);

		$registered = register_rest_route(
			DATA_SYNC_API_BASE_URL,
			'/settings_tab/(?P<tab>[a-zA-Z-_]+)',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_settings_tab_html' ),
					'args'     => array(
						'tab' => array(
							'description' => 'Tab to get',
							'type'        => 'string',
						),
					),
				),
			)
		);

		$registered = register_rest_route(
			DATA_SYNC_API_BASE_URL,
			'/admin_notice',
			array(
				array(
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => array( $this, 'create_admin_notice' ),
				),
			)
		);


	}

	/**
	 * Add sections and options to Data Sync WordPress admin options page.
	 * This also registers all options for updating.
	 */
	public function register() {
		add_settings_section( 'data_sync_options', '', null, 'data-sync-options' );

		add_settings_field( 'source_site', 'Source or Receiver?', $this->view_namespace . '\display_source_input', 'data-sync-options', 'data_sync_options' );
		register_setting( 'data_sync_options', 'source_site' );

		register_setting( 'data_sync_options', 'data_sync_source_site_url' );
		register_setting( 'data_sync_options', 'data_sync_receiver_site_id' );

		register_setting( 'data_sync_options', 'debug' );

		add_settings_field( 'awareness_messages', '', $this->view_namespace . '\display_awareness_messages', 'data-sync-options', 'data_sync_options' );

		$source = get_option( 'source_site' );

		if ( '1' === $source ) :

//			add_settings_field( 'connected_sites', 'Connected Sites', $this->view_namespace . '\display_connected_sites', 'data-sync-options', 'data_sync_options' );

			add_settings_field( 'enable_new_cpts', 'Automatically Enable New Custom Post Types On Receiver', $this->view_namespace . '\display_auto_add_cpt_checkbox', 'data-sync-options', 'data_sync_options' );
			register_setting( 'data_sync_options', 'enable_new_cpts' );

//			add_settings_field( 'overwrite_yoast', 'Overwrite Receiver Yoast Settings', $this->view_namespace . '\display_overwrite_yoast_checkbox', 'data-sync-options', 'data_sync_options' );
//			register_setting( 'data_sync_options', 'overwrite_yoast' );

			add_settings_field( 'overwrite_receiver_post_on_conflict', 'Overwrite Receiver Post if Receiver Post Was More Recently Edited', $this->view_namespace . '\display_overwrite_receiver_post_checkbox', 'data-sync-options', 'data_sync_options' );
			register_setting( 'data_sync_options', 'overwrite_receiver_post_on_conflict' );

//			add_settings_field( 'push_enabled_post_types', 'Push-Enabled Post Types', $this->view_namespace . '\display_push_enabled_post_types', 'data-sync-options', 'data_sync_options' );
//			register_setting( 'data_sync_options', 'push_enabled_post_types' );

			add_settings_field( 'debug', 'Debug', $this->view_namespace . '\display_debug_checkbox', 'data-sync-options', 'data_sync_options' );

			if ( '1' === get_option( 'debug' ) ) :
				add_settings_field( 'show_body_responses', 'Print AJAX Body Responses', $this->view_namespace . '\display_show_body_responses_checkbox', 'data-sync-options', 'data_sync_options' );
				register_setting( 'data_sync_options', 'show_body_responses' );

				add_settings_field( 'start_fresh', 'Start Fresh', $this->view_namespace . '\display_start_fresh_link', 'data-sync-options', 'data_sync_options' );
			endif;

		elseif ( '0' === $source ) :

			add_settings_field( 'secret_key', 'Secret Key', $this->view_namespace . '\display_secret_key', 'data-sync-options', 'data_sync_options' );
			register_setting( 'data_sync_options', 'secret_key' );

			add_settings_field( 'notified_users', 'Notified Users', $this->view_namespace . '\display_notified_users', 'data-sync-options', 'data_sync_options' );
			register_setting( 'data_sync_options', 'notified_users' );

			register_setting( 'data_sync_options', 'enabled_post_types' );
			add_settings_field(
				'enabled_post_types',
				'Enabled Post Types',
				$this->view_namespace . '\display_post_types_to_accept',
				'data-sync-options',
				'data_sync_options'
			);

		endif;
	}

}
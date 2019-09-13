<?php


namespace DataSync\Controllers;

use DataSync\Controllers\File;
use DataSync\Models\DB;
use DataSync\Models\SyncedPost;
use WP_REST_Server;

/**
 * Class Media
 * @package DataSync\Controllers
 */
class Media {

	/**
	 * Media constructor.
	 *
	 * @param null $all_posts
	 */
	public function __construct( $all_posts = null ) {

		if ( null === $all_posts ) {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		} else {

			$connected_sites = (array) ConnectedSites::get_all()->get_data();
			$synced_posts    = new SyncedPosts();
			$synced_posts    = (array) $synced_posts->get_all()->get_data();

			$media = array();

			foreach ( $all_posts as $post_type ) {
				foreach ( $post_type as $post ) {

					$image_attachments = (array) $post->media->image;
					foreach ( $image_attachments as $key => $image ) {
						foreach ( $synced_posts as $synced_post ) {
							if ( (int) $image->post_parent === (int) $synced_post->source_post_id ) {
								$image->receiver_post_id = $synced_post->receiver_post_id;
							}
						}
						$this->send_to_receiver( $image, $connected_sites );
					}

					$audio_attachments = (array) $post->media->audio;
					foreach ( $audio_attachments as $key => $audio ) {
						foreach ( $synced_posts as $synced_post ) {
							if ( (int) $audio->post_parent === (int) $synced_post->source_post_id ) {
								$audio->receiver_post_id = $synced_post->receiver_post_id;
							}
						}
						$this->send_to_receiver( $audio, $connected_sites );
					}

					$video_attachments = (array) $post->media->video;
					foreach ( $video_attachments as $key => $video ) {
						foreach ( $synced_posts as $synced_post ) {
							if ( (int) $video->post_parent === (int) $synced_post->source_post_id ) {
								$video->receiver_post_id = $synced_post->receiver_post_id;
							}
						}
						$this->send_to_receiver( $video, $connected_sites );
					}
				}
			}

		}

	}

	/**
	 * @param $media
	 * @param $connected_sites
	 */
	public function send_to_receiver( $media, $connected_sites ) {

		$synced_posts = new SyncedPosts();
		$upload_dir   = wp_get_upload_dir();
		$path         = wp_parse_url( $media->guid ); // ['host'], ['scheme'], and ['path'].

		$data                     = new \stdClass();
		$data->media              = $media;
		$data->source_base_url    = get_site_url();
		$data->synced_posts       = (array) $synced_posts->get_all()->get_data();
		$data->source_upload_path = $upload_dir['path'];
		$data->source_upload_url  = $upload_dir['url'];
		$data->filename           = basename( $path['path'] );

		foreach ( $connected_sites as $site ) {

			$data->receiver_site_id = (int) $site->id;
			$auth                   = new Auth();
			$json                   = $auth->prepare( $data, $site->secret_key );
			$url                    = trailingslashit( $site->url ) . 'wp-json/' . DATA_SYNC_API_BASE_URL . '/media/update';
			$response               = wp_remote_post( $url, [ 'body' => $json ] );

			if ( is_wp_error( $response ) ) {
				echo $response->get_error_message();
				$log = new Logs( 'Error in Media->update() received from ' . $site->url . '. ' . $response->get_error_message(), true );
				unset( $log );
			} else {
				if ( get_option( 'show_body_responses' ) ) {
					if ( get_option( 'show_body_responses' ) ) {
						echo 'Media';
						print_r( wp_remote_retrieve_body( $response ) );
					}
				}
			}

		}

	}

	/**
	 *
	 */
	public function update() {
		$source_data = (object) json_decode( file_get_contents( 'php://input' ) );
		$this->insert_into_wp( $source_data );
		wp_send_json_success( $source_data );
	}


	/**
	 * @param string $source_base_url
	 * @param object $post
	 * @param array $synced_posts
	 */
	public function insert_into_wp( object $source_data ) {

		$upload_dir      = wp_get_upload_dir();
		$file_path       = $upload_dir['path'] . '/' . $source_data->filename;

		var_dump( 'file path' );
		var_dump( $file_path );

		$result = File::copy( $source_data );

		if ( $result ) {

			$wp_filetype = wp_check_filetype( $source_data->filename, null );

			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_parent'    => (int) $source_data->media->receiver_post_id,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $source_data->filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$args        = array(
				'receiver_site_id' => (int) get_option( 'data_sync_receiver_site_id' ),
				'source_post_id'   => $source_data->media->ID,
			);
			$synced_post = SyncedPost::get_where( $args );

			// SET DIVERGED TO FALSE TO OVERWRITE EVERY TIME.
			$source_data->media->diverged = false;

			if ( count( $synced_post ) ) {
				$source_data->media->diverged = false;
				$attachment_id                = $synced_post[0]->receiver_post_id;
			} else {
				$attachment_id = wp_insert_attachment( $attachment, $file_path, (int) $source_data->media->receiver_post_id );
			}


			if ( ! is_wp_error( $attachment_id ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
				wp_update_attachment_metadata( $attachment_id, $attachment_data );
				$this->update_thumbnail_id( $source_data->media, $attachment_id );
				SyncedPosts::save_to_receiver( $attachment_id, $source_data->media );
			} else {
				$log = new Logs( 'Post not uploaded and attached to ' . $source_data->media->post_title, true );
				unset( $log );
			}


		}
	}


	/**
	 * This makes sure the parent's thumbnail id to the attached image (featured image) is updated.
	 */
	private function update_thumbnail_id( $post, $attachment_id ) {
		$args               = array(
			'receiver_site_id' => (int) get_option( 'data_sync_receiver_site_id' ),
			'source_post_id'   => $post->post_parent,
		);
		$synced_post_parent = SyncedPost::get_where( $args );
		if ( $synced_post_parent ) {
			$updated = update_post_meta( $synced_post_parent[0]->receiver_post_id, '_thumbnail_id', $attachment_id );
		} else {
			$log = new Logs( 'Post thumbnail not updated for ' . $post->post_title, true );
			unset( $log );
		}


	}


	/**
	 *
	 */
	public function register_routes() {
		$registered = register_rest_route(
			DATA_SYNC_API_BASE_URL,
			'/media/update',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( __NAMESPACE__ . '\Auth', 'authorize' ),
				),
			)
		);
	}

}
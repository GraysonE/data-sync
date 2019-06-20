<?php namespace DataSync;

use DataSync\Controllers\ConnectedSites;
use DataSync\Controllers\Options;
use DataSync\Controllers\SyncedPosts;
use DataSync\Controllers\Posts;
use DataSync\Models\SyncedPost;

/**
 * Dashboard widget that displays status of posts that haven't been synced
 */
function status_widget() {
	// TODO: add update failed section
	?>
	<table id="wp_data_sync_status">
		<thead>
		</thead>
		<tr>
			<th><?php _e( 'ID', 'data_sync' ); ?></th>
			<th><?php _e( 'Post', 'data_sync' ); ?></th>
			<th><?php _e( 'Type', 'data_sync' ); ?></th>
			<th><?php _e( 'Created', 'data_sync' ); ?></th>
			<th><?php _e( 'Synced', 'data_sync' ); ?></th>
		</tr>
		<?php
		$connected_sites_obj       = new ConnectedSites();
		$connected_sites           = $connected_sites_obj->get_all()->data;
		$number_of_sites_connected = count( $connected_sites );

		$receiver_options = (object) Options::receiver()->get_data();
		$posts            = Posts::get( $receiver_options->enabled_post_types );
		foreach ( $posts as $post ) {
			$post        = $post[0];
			$result      = SyncedPost::get_where(
				array(
					'source_post_id' => (int) filter_var( $post->ID, FILTER_SANITIZE_NUMBER_INT ),
				)
			);
			$post_status = '<i class="dashicons dashicons-warning"></i>';
			if ( count( $result ) === $number_of_sites_connected ) {
				$post_status = '<i class="dashicons dashicons-yes"></i>';
			} else {
				$post_status = '<i class="dashicons dashicons-info"></i>';
			}
			?>
			<tr>
				<td><?php echo $post->ID ?></td>
				<td><?php echo $post->post_title ?></td>
				<td><?php echo ucfirst( $post->post_type ); ?></td>
				<td><?php echo $post->post_date ?></td>
				<td><?php echo $post_status; ?></td>
			</tr>
			<?php
		}
		?>
	</table>
	<div id="status_dashboard_button_wrap">
		<button id="bulk_data_push"><?php _e( 'Push All', 'data_sync' ); ?></button>
		<button id="recent_data_push"><?php _e( 'Push Unsynced', 'data_sync' ); ?></button>
		<button id="template_push"><?php _e( 'Push Template', 'data_sync' ); ?></button>
	</div>
	<?php
}

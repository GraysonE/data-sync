import AJAX from '../AJAX.es6.js';

document.addEventListener( 'DOMContentLoaded', function () {
	
	diverged_post_init();
	
	if ( document.getElementById( 'bulk_data_push' ) ) {
		document.getElementById( 'bulk_data_push' ).onclick = function ( e ) {
			e.preventDefault();
			
			// CHANGE ICONS TO SPINNING UPDATE ICON
			document.querySelectorAll( '.wp_data_synced_post_status_icons' ).forEach( function ( node ) {
				node.innerHTML = '<i class="dashicons dashicons-update"></i>';
			} );
			
			let sync_start_time = parseInt( Date.now() );
			let syncs_to_complete = document.querySelectorAll( '.wp_data_synced_post_status_icons' ).length;
			let connected_site_ids_strings = document.getElementById( 'sites_connected_info' ).getAttribute( 'data-ids' ).split( ',' );
			let connected_site_ids_int = [];
			connected_site_ids_strings.forEach( function ( id ) {
				connected_site_ids_int.push( parseInt( id ) );
			});
			
			let connected_site_count = connected_site_ids_int.length;
			
			// BEGIN DATA PUSH
			AJAX.get( DataSync.api.url + '/source_data/push' ).then( function ( push_result ) {
				
				console.log( push_result );
				
				// CLEAR AUTO-REFRESH INTERVAL IF IT ALREADY EXISTS
				if ( typeof post_status_interval !== 'undefined' ) {
					clearInterval( post_status_interval );
				}
				
				// CREATE AUTO-REFRESH INTERVAL
				let post_status_interval = setInterval( function () {
					
					
					
					// GET ALL SYNCED POSTS
					AJAX.get( DataSync.api.url + '/synced_posts/all' ).then( function ( synced_posts ) {
						
						let sites_left_to_check = connected_site_count;
						
						
						
						
						// LOOP THROUGH CONNECTED SITE IDS
						connected_site_ids_strings.forEach( function( connected_site_id ) {
							
							// DECREASE IMMEDIATELY TO MAKE SURE WE SHOW THE CHECK ICON WHEN WE'RE CHECKING LAST SERVER
							sites_left_to_check--;
							
							// FIND INDEXES OF SYNCED POSTS THAT MATCH THE CONNECTED SITE ID
							let array_indexes = getAllIndexesWhere( synced_posts, 'receiver_site_id', connected_site_id );
							
							
							
							
							
							// LOOP THROUGH SYNCED POSTS WITH MATCHED CONNECTED SITE IDS
							array_indexes.forEach( function( i ) {
								
								
								
								
								let synced_post = synced_posts[i];
								let receiver_site_id = parseInt( synced_post.receiver_site_id );
								
								
								// SKIP IF IT'S MEDIA.
								if ( 'attachment' !== synced_post.post_type ) {
									
									
									// SET LAST UPDATED DATE STRING
									let post_modified_date = new Date( synced_post.date_modified ).toLocaleString( 'en-US', { timeZone: 'America/Denver' } );
									
									// SET LAST UPDATED DATE TIMESTAMP TO COMPARE WITH START TIME TO MAKE SURE IT'S THE MOST UP-TO-DATE RESULT
									let post_modified_date_timestamp = parseInt( new Date( synced_post.date_modified ).getTime() );
									
									// ONLY CHANGE UI IF MODIFIED DATE IS NEWER THAN THE TIME THE SYNC STARTED
									if ( post_modified_date_timestamp > sync_start_time ) {
										
										let synced_post_id = parseInt( synced_post.source_post_id );
									
										
										// INDICATE THAT A RECEIVER WAS SYNCED, BUT NOT ALL OF THEM
										document.getElementById( 'synced_post-' + synced_post_id ).getElementsByClassName( 'wp_data_synced_post_status_icons' )[0].innerHTML = '<i class="dashicons dashicons-info" title="Partially synced. Please allow time for the data to propogate. Some posts may have failed to sync with a connected site because the post type isn\'t enabled on the receiver or there was an error."></i>';
										
										if ( 0 === sites_left_to_check ) {
											
											// INDICATE THAT ALL RECEIVERS HAVE BEEN SYNCED
											document.getElementById( 'synced_post-' + synced_post_id ).getElementsByClassName( 'wp_data_synced_post_status_icons' )[0].innerHTML = '<i class="dashicons dashicons-yes" title="Synced on all connected sites."></i>';
											
											syncs_to_complete--;
											if ( 0 === syncs_to_complete ) {
												clearInterval( post_status_interval );
											}
										}
										
										// UPDATE SYNCED TIME
										document.getElementById( 'synced_post-' + synced_post_id ).getElementsByClassName( 'wp_data_synced_post_status_synced_time' )[0].innerHTML = synced_post.date_modified;
									
									} else {
										if ( 1 === parseInt( synced_post.diverged ) ) {
											let tr = document.getElementById( 'synced_post-' + synced_post.source_post_id );
											let att = document.createAttribute('diverged');
											att.value = 1;
											tr.setAttributeNode( att );
											syncs_to_complete--;
										}
									}
								}
								
							});
							
						});
						
						display_diverged_notice( synced_posts );
						
						
					} );
				}, 3000 );
				
			} );
			
		};
	}
	
	if ( document.getElementById( 'template_push' ) ) {
		document.getElementById( 'template_push' ).onclick = function ( e ) {
			e.preventDefault();
			AJAX.post( DataSync.api.url + '/templates/sync' ).then( function ( result ) {
				console.log( result );
			} );
		};
	}
	
} );

function display_diverged_notice( synced_posts) {
	jQuery( function ( $ ) {
		$('#wp_data_sync_status tbody tr').each( function() {
			if ( 1 == $(this).attr('diverged') ) {
				let receiver_site_id = null;
				synced_posts.forEach( (synced_post) => {
					if ( synced_post.source_post_id == $(this).attr('data-id') ) {
						receiver_site_id = synced_post.receiver_site_id;
					}
				});
				$( '#synced_post-' + $(this).attr('data-id') + ' .wp_data_synced_post_status_icons' )[0].innerHTML = '<i class="dashicons dashicons-editor-unlink" title="A receiver post was updated after the last sync. Click to repair." data-receiver-site-id="' + receiver_site_id + '" data-source-post-id="' + $(this).attr('data-id') + '"></i>';
			}
		});
	} );
	
	// MAKE SURE AJAX CAN PROCESS NEW ELEMENT.
	diverged_post_init();
}

function diverged_post_init() {
	jQuery( function ( $ ) {
		$('.wp_data_synced_post_status_icons .dashicons-editor-unlink').unbind().click(function() {
			
			// CHANGE ICON TO SPINNING UPDATE ICON
			$(this).innerHTML = '<i class="dashicons dashicons-update"></i>';
			
			let receiver_site_id = $(this).data('receiver-site-id');
			let source_post_id = $(this).data('source-post-id');
			
			AJAX.get( DataSync.api.url + '/source_data/overwrite/' + receiver_site_id + '/' + source_post_id ).then( function ( result ) {
				console.log( result );
				if ( result ) {
					let synced_post = result.data;
					// INDICATE THAT ALL RECEIVERS HAVE BEEN SYNCED
					document.getElementById( 'synced_post-' + source_post_id ).getElementsByClassName( 'wp_data_synced_post_status_icons' )[0].innerHTML = '<i class="dashicons dashicons-yes" title="Synced on all connected sites."></i>';
					// UPDATE SYNCED TIME
					document.getElementById( 'synced_post-' + source_post_id ).getElementsByClassName( 'wp_data_synced_post_status_synced_time' )[0].innerHTML = synced_post.date_modified;
				}
			} );
		});
	} );
}

jQuery( function ( $ ) {
	$( document ).ready( function () {
		$( '#data_sync_status_tabs' ).tabs();
	} );
	
} );

function getAllIndexesWhere(arr, key, val) {
	var indexes = [], i;
	for(i = 0; i < arr.length; i++) {
		// console.log(arr[i], arr[i][key], val);
		if (arr[i][key] === val) {
			indexes.push( i );
		}
	}
	
	return indexes;
}

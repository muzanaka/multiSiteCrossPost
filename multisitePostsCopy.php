<?php
 /*
 Plugin Name: Copier new posts to other sites in network
 Plugin URI: https://devstages.ru/
 Description: When a new post is created this plugin will copy it to other sites in network
 Version: 0.1
 Author: Devstages
 License: GPL2
 */

require_once( ABSPATH . 'wp-admin/includes/image.php' );

add_action('add_meta_boxes', 'dsCopyPostAddCustomBox');
function dsCopyPostAddCustomBox(){
	$screens = array();
	add_meta_box( 'dsCopyPostIntoOtherSites', 'Копировать в другие языковые версии сайта', 'dsCopyPostIntoOtherSitesCallback', $screens );
}

function dsCopyPostIntoOtherSitesCallback( $post, $meta ){

	wp_nonce_field( plugin_basename(__FILE__), 'dsCopyPost_noncename' );

	$dsIsCopyMetaKey = get_post_meta( $post->ID, 'dsIsCopyMetaKey', 1 );
    $dsIsChecked = '';
    if(!empty($dsIsCopyMetaKey)) {
        $dsIsChecked = 'checked';
    }

	echo '<label for="dsIsCopyMetaKeyValue">Копировать в другие языковые версии сайта</label> ';
	echo '<input type="checkbox" id="dsIsCopyMetaKeyValue" name="dsIsCopyMetaKeyValue" '.$dsIsChecked.' />';

	$dsLinkedIds = get_post_meta( $post->ID, 'dsLinkedIdsMetaKey', 1);

	// echo '<br><br><label for="dsdsLinkedIdsValue">Перелинковка</label> ';
	// echo '<input type="text" id="dsdsLinkedIdsValue" name="dsdsLinkedIdsValue" value="'.$dsLinkedIds.'" size="25"/>';
}

function DScopyPost($post) {

    if ( ! isset( $_POST['dsIsCopyMetaKeyValue'] ) )
		return;

	if ( ! wp_verify_nonce( $_POST['dsCopyPost_noncename'], plugin_basename(__FILE__) ) )
		return;

	// if ( ! wp_verify_nonce( $_POST['dsLinkedIds_noncename'], plugin_basename(__FILE__) ) )
	// 	return;

	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return;

	if( ! current_user_can( 'edit_post', $post->ID ) )
		return;

	$dsIsCopyMetaKeyValue = sanitize_text_field( $_POST['dsIsCopyMetaKeyValue'] );
	update_post_meta( $post->ID, 'dsIsCopyMetaKey', $dsIsCopyMetaKeyValue);

	// $dsdsLinkedIdsValue = sanitize_text_field( $_POST['dsdsLinkedIdsValue'] );
	// update_post_meta( $post->ID, 'dsLinkedIdsMetaKey', $dsdsLinkedIdsValue);

    $isCopyPostMeta = get_post_meta( $post->ID, 'dsIsCopyMetaKey', 1 );

    if(!empty($isCopyPostMeta)) {

		$postArgs = array(
						'post_content' => $post->post_content,
						'post_title' => $post->post_title,
						'post_type' => get_post_type($post)
					);

		$filename = get_attached_file(get_post_thumbnail_id($post));

		$sites = get_sites();

		if(!empty($sites)) {
			$dsLinkPostId = array(get_current_blog_id() => $post->ID);
			foreach ($sites as $site) {
				if($site->blog_id != get_current_blog_id()) {
					switch_to_blog($site->blog_id);
					$dsInsertedPost = wp_insert_post($postArgs);

					if($dsInsertedPost) {

						$dsLinkPostId[$site->blog_id] = $dsInsertedPost;

						if(!empty($filename)) {

							$filetype = wp_check_filetype( basename( $filename ), null );

							$wpUploadDir = wp_upload_dir();
							$upload = wp_upload_bits( $filename, null, file_get_contents( $filename ) );

							$wpFiletype = wp_check_filetype( basename( $upload['file'] ), null );

							$attachment = array(
						        'guid' => $wpUploadDir['baseurl'] . _wp_relative_upload_path( $upload['file'] ),
						        'post_mime_type' => $wpFiletype['type'],
						        'post_title' => preg_replace('/\.[^.]+$/', '', basename( $upload['file'] )),
						        'post_content' => '',
						        'post_status' => 'inherit'
						    );

							$attachId = wp_insert_attachment( $attachment, $upload['file'] , $dsInsertedPost );

							$attach_data = wp_generate_attachment_metadata( $attachId, $upload['file']  );
							wp_update_attachment_metadata( $attachId, $attach_data );

							set_post_thumbnail( $dsInsertedPost, $attachId );
						}

					}
				}
			}

			foreach ($dsLinkPostId as $blogId => $postId) {
				switch_to_blog($blogId);
				update_post_meta( $postId, 'dsLinkedIdsMetaKey', serialize($dsLinkPostId));
			}
		}
		restore_current_blog();
    }
}
add_action('draft_to_publish','DScopyPost', 10, 3);

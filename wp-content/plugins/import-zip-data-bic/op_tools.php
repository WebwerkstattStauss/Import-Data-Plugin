<?php
/**
 * @desc			Tools und Helper 
 * @version		$Id: opimtools.php 2009-09-15,  V1.0
 * @package		P2GO
 * @copyright	Copyright (C) 2009 Abcoso Ltd.  All rights reserved.
 * @license		Abcoso SW Usage 2009
 */

function createPost($wpPostData){
	$postId = wp_insert_post($wpPostData, true);
	
	if (!is_wp_error($postId)) {
		// echo " - Post Inserted";
		return $postId;
	} else {
		$repotr_description = " - Error when insert post - " . $postId->get_error_message() . "\n\r";
		// echo $repotr_description;
	} 
}

function updatePost($postId, $wpPostData){
	
	//From here on update object
	custom_wp_delete_object_term_relationships($postId, array(
		'property-status',
		'property-city',
		'property-feature',
		'property-type',
	));

	wp_update_post($wpPostData);

	delete_post_meta($postId, 'REAL_HOMES_attachments');
	delete_post_meta($postId, 'REAL_HOMES_property_images');
}

function custom_wp_delete_object_term_relationships($objectId, $taxonomies) {
	$objectId = (int) $objectId;

	if (!is_array($taxonomies)) {
		$taxonomies = array($taxonomies);
	}

	foreach ((array) $taxonomies as $taxonomy) {
		/////////
		if (!taxonomy_exists($taxonomy)) {
			register_taxonomy(
				$taxonomy,
				'property',
			);
		}
		/////////

		$termIds = wp_get_object_terms($objectId, $taxonomy, array('fields' => 'ids'));
		
		if (!is_wp_error($termIds)) {
			$termIds = array_map('intval', $termIds);
			wp_remove_object_terms($objectId, $termIds, $taxonomy);
		} else {
			$repotr_description = " - Error - " . $termIds->get_error_message() . "\n\r";
			// echo $repotr_description;
		} 
	}
}

function deletePost($postId){
	if(!empty($postId)){
		//Action was set to delete, so delete object
		wp_delete_post($postId, false);
	
		$postAttachment = get_post_meta($postId, 'REAL_HOMES_attachments');
		if(!empty($postAttachment))
		{
			foreach ($postAttachment as $key => $value) {
				wp_delete_attachment($value, true);
			}
		}
			
		$postPropertyImages = get_post_meta($postId, 'REAL_HOMES_property_images');
		if(!empty($postPropertyImages))
		{
			foreach ($postPropertyImages as $key => $value) {
				wp_delete_attachment($value, true);
			}
		}
	}
}

function my_sideload_image_new($postId, $filename, $filepath, $desc = false, $returnId = false, $createCrops = true) {

	if (file_exists($filepath . $filename) && !is_dir($filepath . $filename)) {

		$uploaddir  = wp_upload_dir();
		$uploadfile = $uploaddir['path'] . '/' . $filename;

		//check exist file
		$check_exist_file = find_post_id_from_path($uploadfile);

		if ($check_exist_file) {

			$filename_new = str_replace(".", "_" . time() . ".", $filename);
			$uploadfile = $uploaddir['path'] . '/' . $filename_new;
			$attach_id = $check_exist_file;
		} else {

			//copy file
			copy($filepath . $filename, $uploadfile);
			//add file to media library
			$wp_filetype = wp_check_filetype(basename($filename), null);
			$image_title = '';
			
			if ($desc) {
				$image_title = $desc;
			} else {
				$image_title = $filename;
			}

			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => $image_title,
				'post_content' => '',
				'post_status' => 'inherit',
			);

			$attach_id = wp_insert_attachment($attachment, $uploadfile);
			
			if ($createCrops) {
				$imagenew = get_post($attach_id);
				$fullsizepath = get_attached_file($imagenew->ID);
				$attach_data = wp_generate_attachment_metadata($attach_id, $fullsizepath);
				wp_update_attachment_metadata($attach_id, $attach_data);
			}
		}

		if ($postId) {
			set_post_thumbnail($postId, $attach_id);
		}

		if ($returnId) {
			return $attach_id;
		}
	}
}

function find_post_id_from_path($path) {
	// detect if is a media resize, and strip resize portion of file name
	if (preg_match('/(-\d{1,4}x\d{1,4})\.(jpg|jpeg|png|gif)$/i', $path, $matches)) {
		$path = str_ireplace($matches[1], '', $path);
	}

	// process and include the year / month folders so WP function below finds properly
	if (preg_match('/uploads\/(\d{1,4}\/)?(\d{1,2}\/)?(.+)$/i', $path, $matches)) {
		unset($matches[0]);
		$path = implode('', $matches);
	}
	
	// call WP native function to find post ID properly
	return attachment_url_to_postid($path);
}

function ws_add_meta($postId, $metaName, $metaValue) {
	if ($metaValue) {
		update_post_meta($postId, $metaName, $metaValue);
	}
}


//_____ end of file ________
?>
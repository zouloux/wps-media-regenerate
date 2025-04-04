<?php
/**
 * Plugin Name:       WPS Media Regenerate
 * Plugin URI:        https://github.com/zouloux/wps-media-regenerate
 * GitHub Plugin URI: https://github.com/zouloux/wps-media-regenerate
 * Description:       Simplest Media Regeneration plugin
 * Author:            Alexis Bouhet
 * Author URI:        https://zouloux.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       WPS
 * Domain Path:       /cms
 * Version:           1.0.0
 * Copyright:         © 2025 Alexis Bouhet
 */

if ( !defined("ABSPATH") ) exit;
if ( !is_blog_installed() ) return;
if ( defined('WPS_MEDIA_REGENERATE_DISABLE') && WPS_MEDIA_REGENERATE_DISABLE ) return;

add_action('admin_menu', function () {
	add_submenu_page(
		'upload.php',
		'Regenerate Media',
		'Regenerate Media',
		'manage_options',
		'regenerate-media',
		'wps_media_regenerate_admin_action'
	);
});

// Add ajax action handler
add_action('wp_ajax_media_regenerate', function () {
	check_ajax_referer('media_regenerate_nonce');
	$mediaId = isset($_POST['id']) ? intval($_POST['id']) : 0;
	if ( $mediaId <= 0 )
		wp_send_json_error('Invalid media ID.');
	$mediaPath = get_attached_file( $mediaId );
	if ( $mediaPath === false || !file_exists($mediaPath) )
		wp_send_json_error("File not found.");
  // Common path info
    $basePath = trailingslashit(pathinfo($mediaPath)['dirname']);
  // Get generated media files sizes
	$currentMetadata = wp_get_attachment_metadata( $mediaId );
	$filesToRemove = [];
    if ( isset($currentMetadata['sizes']) )
			$filesToRemove = array_values($currentMetadata['sizes']);
    // Check if we generated some webp and add them
    $webpImages = get_post_meta($mediaId, 'webp_sizes', true);
		if ( $webpImages ) {
      $webpImages = json_decode($webpImages, true);
		$webpImages = array_values($webpImages);
		$filesToRemove = [ ...$filesToRemove, ...$webpImages, ];
	}
    // Remove every old file
    foreach ( $filesToRemove as $file ) {
			$oldFilePath = $basePath.$file['file'];
			if ( file_exists($oldFilePath) )
					@unlink($oldFilePath);
    }
	// fixme : does it force regenerate already existing ?
	$metadata = wp_generate_attachment_metadata( $mediaId, $mediaPath );
	if ( is_wp_error($metadata) )
		wp_send_json_error("Unable to generate media");
	wp_update_attachment_metadata( $mediaId, $metadata );
	wp_send_json_success("Media regenerated.");
});

// Register admin page html
function wps_media_regenerate_admin_action () {
	if ( !is_admin() || !current_user_can('manage_options') )
		return;
	// Get all media ids to be sent to js as json
	function getAllMediaIDs() {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$query = new WP_Query($args);
		return $query->posts;
	}
	// Data sent to js
	$jsData = [
		"url" => admin_url('admin-ajax.php'),
		"ids" => getAllMediaIDs(),
		"nonce" => wp_create_nonce('media_regenerate_nonce')
	];
	?>
	<div class="wrap">
		<script>
			var _data = <?php echo json_encode( $jsData ) ?>;
			let _started = false
			let _total = _data.ids.length
			var _currentMediaIndex = 0
			var $ = jQuery;
			function _getMediaStats (offset = 0) {
				return (_currentMediaIndex + offset)+" / "+_total
			}
			function _updateMediaStats ( offset ) {
				$('#stats').text( _getMediaStats(offset) )
			}
			function _updateLoader () {
				$("#loader-wrapper .loader").css({
					width: ((_currentMediaIndex + 1) / _total) * 100 + "%"
				})
			}
			function _startMediaRegenerate() {
				if ( _started )
					return
				_started = true
				$('#startButton').attr('disabled', 'disabled')
				$('#setStartIndexButton').attr('disabled', 'disabled')
				_regenerateNextMedia();
			}
			function _setStartIndex () {
				const index = prompt("Type start index")
				const parsedIndex = parseInt(index)
				if ( isNaN(parsedIndex) || parsedIndex < 0 || parsedIndex > _total ) {
					alert("Invalid index")
					return;
				}
				_currentMediaIndex = parsedIndex;
				_updateMediaStats( 0 )
			}
			function _regenerateNextMedia () {
				if ( !(_currentMediaIndex in _data.ids) ) {
					_started = false
					$('#startButton').attr('disabled', null);
					$('#setStartIndexButton').attr('disabled', null)
					return
				}
				var mediaId = _data.ids[ _currentMediaIndex ]
				console.log("Regenerating media id "+mediaId+" ( "+_getMediaStats()+" )")
				_updateMediaStats( 1 )
				_updateLoader()
				$.post( _data.url, {
					'action': 'media_regenerate',
					'id': mediaId,
					'_ajax_nonce': _data.nonce,
				}, function(response) {
					console.log( response )
					if ( !response.success ) {
						alert("An error occurred, see console.");
						return;
					}
					++_currentMediaIndex;
					_regenerateNextMedia();
				});
			}
		</script>
		<style>
			#loader-wrapper {
				position: relative;
				width: 100%;
				height: 20px;
				background: #ddd;
				border-radius: 4px;
				margin-top: 12px;
				overflow: hidden;
			}
			#loader-wrapper .loader {
				position: absolute;
				width: 0;
				height: 100%;
				background: #0d99d5;
				transition: width 200ms linear;
			}
			#stats {
				display: inline-block;
				margin-top: 4px;
				margin-left: 12px;
			}
		</style>
		<h1><?php echo esc_html(get_admin_page_title()) ?></h1>
		<br />
		<div>
			<input type="submit" name="submit" id="startButton" class="button button-primary" value="Start" onClick="_startMediaRegenerate()" />
			<button id="setStartIndexButton" class="button" onClick="_setStartIndex()">Set start index</button>
			<span id="stats"></span>
			<script>_updateMediaStats()</script>
		</div>
		<div id="loader-wrapper">
			<div class="loader"></div>
		</div>
	</div>
	<?php
}

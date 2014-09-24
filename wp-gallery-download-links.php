<?php
/*
Plugin Name: WP Gallery Download Links
Plugin URI: https://wordpress.org/plugins/wp-gallery-download-link/
Description: This plugin adds a download link below every image in your WordPress galleries.
Author: Michel Kusters
Version: 1.0
Author URI: https://www.linkedin.com/in/michelkusters
*/

/* 
Copyright 2014-2015

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Add custom filter to shortcode output
add_filter('post_gallery', 'my_post_gallery', 10, 2);
function my_post_gallery($output, $attr) {
	global $post, $wp_locale;

	// Gallery instance counter
	static $instance = 0;
	$instance++;

	// Validate the author's orderby attribute
	if(isset($attr['orderby'])) {
		$attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
		if(!$attr['orderby']) unset($attr['orderby']);
	}

	// Get attributes from shortcode
	extract(shortcode_atts(array(
		'order'      => 'ASC',
		'orderby'    => 'menu_order ID',
		'id'         => $post->ID,
		'itemtag'    => 'dl',
		'icontag'    => 'dt',
		'captiontag' => 'dd',
		'columns'    => 3,
		'size'       => 'thumbnail',
		'include'    => '',
		'exclude'    => ''
	), $attr));

	// Initialize
	$id = intval($id);
	$attachments = array();
	if($order == 'RAND') $orderby = 'none';

	if(!empty($include)) {
		// Include attribute is present
		$include = preg_replace('/[^0-9,]+/', '', $include);
		$_attachments = get_posts(array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));

		// Setup attachments array
		foreach($_attachments as $key => $val) {
			$attachments[$val->ID] = $_attachments[$key];
		}
	} elseif(!empty($exclude)) {
		// Exclude attribute is present 
		$exclude = preg_replace('/[^0-9,]+/', '', $exclude);
		// Setup attachments array
		$attachments = get_children(array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
	} else {
		// Setup attachments array
		$attachments = get_children(array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
	}

	if(empty($attachments)) return '';

	// Filter gallery differently for feeds
	if(is_feed()) {
		$output = "\n";
		foreach($attachments as $att_id => $attachment) $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
		return $output;
	}

	// Filter tags and attributes
	$itemtag = tag_escape($itemtag);
	$captiontag = tag_escape($captiontag);
	$columns = intval($columns);
	$itemwidth = $columns > 0 ? floor(100 / $columns) : 100;
	$float = is_rtl() ? 'right' : 'left';
	$selector = "gallery-{$instance}";

	// Filter gallery CSS
	$output = apply_filters('gallery_style', "
		<style type='text/css'>
			#{$selector} {
				margin: auto;
			}
			#{$selector} .gallery-item {
				float: {$float};
				margin-top: 10px;
				text-align: center;
				width: {$itemwidth}%;
			}
			#{$selector} img {
				border: 2px solid #cfcfcf;
			}
			#{$selector} .gallery-caption {
				margin-left: 0;
			}
		</style>
		<!-- see gallery_shortcode() in wp-includes/media.php -->
		<div id='$selector' class='gallery galleryid-{$id}'>"
	);

	$i = 0;
	foreach($attachments as $id => $attachment) {
		// Attachment link
		$link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false); 
		$image_attributes = wp_get_attachment_image_src( $id, 'full' ); // returns an array
		$url = $image_attributes[0];

		// Start itemtag
		$output .= "<{$itemtag} class='gallery-item'>";
		// icontag
		$output .= "
		<{$icontag} class='gallery-icon'>
			$link
		</{$icontag}>";
		// Download text
		$output .= "
		<dt class='gallery-download'>
			<a href='{$url}' download='$id'>Download</a>
		</dt>";
		if($captiontag && trim($attachment->post_excerpt)) {
			// captiontag
			$output .= "
			<{$captiontag} class='gallery-caption'>
				" . wptexturize($attachment->post_excerpt) . "
			</{$captiontag}>";
		}
		// End itemtag
		$output .= "</{$itemtag}>";
		// Line breaks by columns set
		if($columns > 0 && ++$i % $columns == 0) $output .= '<br style="clear: both">';
	}

	$output .= "
		<br style='clear: both;'>
	</div>\n";

	return $output;
}
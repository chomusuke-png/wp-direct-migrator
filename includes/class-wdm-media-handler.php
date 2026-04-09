<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

class WDM_Media_Handler {

    public function sideload_image( $url, $post_id, $description = '' ) {
        if ( empty( $url ) ) {
            return false;
        }

        $attachment_id = media_sideload_image( $url, $post_id, $description, 'id' );

        if ( is_wp_error( $attachment_id ) ) {
            return array( 'error' => $attachment_id->get_error_message() );
        }

        return $attachment_id;
    }
}
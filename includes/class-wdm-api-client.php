<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WDM_API_Client {

    public function fetch_posts( $base_url, $page = 1, $per_page = 10 ) {
        $endpoint = rtrim( $base_url, '/' ) . '/wp-json/wp/v2/posts';
        $url      = add_query_arg( array(
            'page'     => $page,
            'per_page' => $per_page,
            '_embed'   => 1
        ), $endpoint );

        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'API Connection failed: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return array( 'error' => 'HTTP Error: ' . $status_code );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data ) || ! is_array( $data ) ) {
            return array( 'error' => 'Invalid or empty JSON response.' );
        }

        $total_pages = wp_remote_retrieve_header( $response, 'x-wp-totalpages' );

        return array(
            'data'        => $data,
            'total_pages' => intval( $total_pages )
        );
    }
}
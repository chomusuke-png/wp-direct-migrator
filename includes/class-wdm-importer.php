<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WDM_Importer {

    private $api_client;
    private $media_handler;

    public function __construct() {
        $this->api_client    = new WDM_API_Client();
        $this->media_handler = new WDM_Media_Handler();
    }

    public function process_batch( $url, $page ) {
        $response = $this->api_client->fetch_posts( $url, $page );
        
        if ( isset( $response['error'] ) ) {
            return array( 'success' => false, 'message' => $response['error'] );
        }

        $posts       = $response['data'];
        $total_pages = $response['total_pages'];
        $skipped     = array();
        $imported    = 0;
        
        $parsed_old_url = wp_parse_url( $url );
        $old_domain     = isset( $parsed_old_url['host'] ) ? $parsed_old_url['host'] : '';

        foreach ( $posts as $post_data ) {
            $original_id  = intval( $post_data['id'] );
            $post_title   = sanitize_text_field( $post_data['title']['rendered'] );

            if ( $this->post_exists( $original_id ) ) {
                $skipped[] = "Post '{$post_title}' ya importado (ID Remoto: {$original_id}). Omitido.";
                continue;
            }

            $post_content = wp_kses_post( $post_data['content']['rendered'] );
            $post_date    = sanitize_text_field( $post_data['date'] );

            $new_post_id = wp_insert_post( array(
                'post_title'   => $post_title,
                'post_content' => '', 
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_date'    => $post_date,
            ), true );

            if ( is_wp_error( $new_post_id ) ) {
                $skipped[] = "Error al insertar Post '{$post_title}': " . $new_post_id->get_error_message();
                continue; 
            }

            update_post_meta( $new_post_id, '_wdm_original_post_id', $original_id );

            if ( isset( $post_data['_embedded']['wp:term'] ) ) {
                $this->process_taxonomies( $new_post_id, $post_data['_embedded']['wp:term'] );
            }

            if ( isset( $post_data['_embedded']['wp:featuredmedia'][0]['source_url'] ) ) {
                $image_url = esc_url_raw( $post_data['_embedded']['wp:featuredmedia'][0]['source_url'] );
                $attachment_result = $this->media_handler->sideload_image( $image_url, $new_post_id, $post_title );

                if ( is_array( $attachment_result ) && isset( $attachment_result['error'] ) ) {
                    $skipped[] = "Media destacado falló para '{$post_title}': " . $attachment_result['error'];
                } else {
                    set_post_thumbnail( $new_post_id, $attachment_result );
                }
            }

            if ( ! empty( $old_domain ) ) {
                $post_content = $this->process_inline_images( $post_content, $new_post_id, $old_domain );
            }

            wp_update_post( array(
                'ID'           => $new_post_id,
                'post_content' => $post_content
            ) );

            $imported++;
        }

        return array(
            'success'     => true,
            'imported'    => $imported,
            'skipped'     => $skipped,
            'total_pages' => $total_pages,
            'next_page'   => ( $page < $total_pages ) ? $page + 1 : null
        );
    }

    private function post_exists( $original_id ) {
        $query = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'meta_key'       => '_wdm_original_post_id',
            'meta_value'     => $original_id,
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ) );
        return ! empty( $query->posts );
    }

    private function process_taxonomies( $post_id, $terms_groups ) {
        foreach ( $terms_groups as $group ) {
            foreach ( $group as $term ) {
                $taxonomy = ( $term['taxonomy'] === 'post_tag' ) ? 'post_tag' : 'category';
                
                $local_term = get_term_by( 'slug', $term['slug'], $taxonomy );

                if ( ! $local_term ) {
                    $inserted = wp_insert_term( $term['name'], $taxonomy, array(
                        'slug'        => $term['slug'],
                        'description' => $term['description'] ?? ''
                    ) );
                    
                    $term_id = ( ! is_wp_error( $inserted ) ) ? $inserted['term_id'] : false;
                } else {
                    $term_id = $local_term->term_id;
                }

                if ( $term_id ) {
                    wp_set_object_terms( $post_id, (int) $term_id, $taxonomy, true );
                }
            }
        }
    }

    private function process_inline_images( $content, $post_id, $old_domain ) {
        if ( empty( $content ) || ! is_string( $content ) ) return (string) $content;

        if ( ! preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches ) ) {
            return $content; 
        }

        $urls = array_unique( $matches[1] );

        foreach ( $urls as $url ) {
            if ( strpos( $url, $old_domain ) !== false ) {
                $clean_url = strtok( $url, '?' );
                $attachment_id = $this->media_handler->sideload_image( $clean_url, $post_id );
                
                if ( ! is_wp_error( $attachment_id ) && is_int( $attachment_id ) ) {
                    $new_url = wp_get_attachment_url( $attachment_id );
                    if ( $new_url ) {
                        $content = str_replace( $url, $new_url, $content );
                    }
                }
            }
        }
        return $content;
    }
}
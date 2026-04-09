<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WDM_Admin_Page {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'wp_ajax_wdm_run_migration', array( $this, 'handle_ajax_migration' ) );
    }

    public function add_menu_page() {
        add_menu_page(
            'Direct Migrator',
            'Migrator',
            'manage_options',
            'wdm-migrator',
            array( $this, 'render_view' ),
            'dashicons-download',
            100
        );
    }

    public function render_view() {
        require_once WDM_PLUGIN_DIR . 'views/admin-page.php';
    }

    public function handle_ajax_migration() {
        check_ajax_referer( 'wdm_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized access.' ) );
        }

        $url  = isset( $_POST['target_url'] ) ? esc_url_raw( $_POST['target_url'] ) : '';
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;

        if ( empty( $url ) ) {
            wp_send_json_error( array( 'message' => 'Target URL is required.' ) );
        }

        $importer = new WDM_Importer();
        $result   = $importer->process_batch( $url, $page );

        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }

        wp_send_json_success( $result );
    }
}
<?php
/**
 * Plugin Name: WP Direct Migrator
 * Description: Migrates posts and media directly from a remote WordPress host via REST API.
 * Version: 1.0.0
 * Author: Zumito
 * Text Domain: wp-direct-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WDM_PLUGIN_DIR . 'includes/class-wdm-admin-page.php';
require_once WDM_PLUGIN_DIR . 'includes/class-wdm-api-client.php';
require_once WDM_PLUGIN_DIR . 'includes/class-wdm-media-handler.php';
require_once WDM_PLUGIN_DIR . 'includes/class-wdm-importer.php';

class WP_Direct_Migrator {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_dependencies' ) );
    }

    public function load_dependencies() {
        if ( is_admin() ) {
            $admin_page = new WDM_Admin_Page();
            $admin_page->init();
        }
    }
}

WP_Direct_Migrator::get_instance();
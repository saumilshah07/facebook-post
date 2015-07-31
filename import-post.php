<?php
/*
Plugin Name: Facebook Post to WordPress import
Description: Import facebook page posts to WordPress
Version: 1
Author: Saumil Shah
*/
// don't access file directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( is_admin() ) {
    require_once dirname( __FILE__ ) . '/includes/admin.php';
}

/**
 * FBPagesToWP class
 *
 * @class FBPagesToWP base class of plugin
 */

class FBPagesToWP {


    /**
     * Hooks and action within  plugin.
     *
     * register_activation_hook()
     * register_deactivation_hook()
     * is_admin()
     * add_action()
     */
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'localization_setup' ) );
        add_action( 'init', array( $this, 'onInit' ) );

        if ( is_admin() ) {
            new FBPagesToWPAdmin();
        }
    }

    /**
     * Activation function
     *
     */
    public function activate() {
    }

    /**
     * Deactivation function
     */
    public function deactivate() {

    }

    /**
     * Initialize plugin for localization
     *
     */
    public function localization_setup() {
        load_plugin_textdomain( 'fbps', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }


    public function onInit() {
        if( isset( $_POST['fbps-import-config'] ) ) {
            add_action( 'admin_init', array( $this, 'import_post') );
        }
    }

    public function import_post(){
        $opts = $this->fbps_get_settings();
        require_once dirname( __FILE__ ) . '/includes/class.facebook.php';
        $api = new FBPS_FACEBOOK( $opts['app_id'], $opts['app_secret'], $opts['fb_id'] , $opts['comment_status'], $opts['post_status'] );
        $result = $api->get_posts();

        if(isset($result['status']) && !$result['status']){
            add_settings_error('fbps', 'fbps-error', __('The following error was encountered when contacting to facebook.', 'facebook-posts' ) . '<br /><br />' . $result['msg'] );
            return false;
        } else if( isset($result) &&  !$result) {
            add_settings_error('fbps', 'fbps-error', __('Facebook app id , app secret and page slug / ID must not empty.', 'facebook-posts' )  );
            return false;
        }

        add_settings_error('fbps', 'fbps-success', __( 'Successfully imported post!.', 'facebook-posts' ), "updated");
    }

    /**
     * Get the facebook settings
     *
     * @return array
     */
    function fbps_get_settings() {
        $option = get_option( 'fbps_settings', array() );

        // return if no configuration found
        if ( !isset( $option['app_id'] ) || !isset( $option['app_secret'] ) || !isset( $option['fb_id'] ) ) {
            return false;
        }

        // no app id or app secret
        if ( empty( $option['app_id'] ) || empty( $option['app_secret'] ) ) {
            return false;
        }

        // no group id
        if ( empty( $option['fb_id'] ) ) {
            return false;
        }

        return $option;
    }

    /**
     * Initializes class
     *
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new FBPagesToWP();
        }

        return $instance;
    }


}

$FBPagesToWP = FBPagesToWP::init();
<?php

require_once dirname( __FILE__ ) . '/class.settings-api.php';

/**
 * Admin Panel
 *
  */
class FBPagesToWPAdmin {

    private $settings_api;

    function __construct() {
        $this->settings_api = new FBPS_Settings_API();

        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'admin_menu', array($this, 'admin_menu') );
    }

    function admin_init() {

        //set the settings
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );

        //initialize settings
        $this->settings_api->admin_init();
    }

    function admin_menu() {
        add_options_page( 'Facebook Posts', 'Facebook Posts', 'manage_options', 'fbps', array( $this, 'settings_page' ) );
    }

    function get_settings_sections() {
        $sections = array(
            array(
                'id' => 'fbps_settings',
                'title' => __( 'Settings', 'cpm' )
            )
        );

        return $sections;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {
        $settings_fields = array();
        $settings_fields['fbps_settings'] = array(
            array(
                'name'    => 'app_id',
                'label'   => __( 'Facebook App ID', 'cpm'),
                'default' => '',
                'desc'    => 'Facebook Application ID e.g: 333533913426405 '
            ),
            array(
                'name'    => 'app_secret',
                'label'   => __( 'Facebook App Secret', 'cpm'),
                'default' => '',
                'desc'    => __( 'Facebook App Secret e.g: 4578f0c3b6d82fd9e7a827c1b509c907 ' )
            ),
            array(
                'name'    => 'fb_id',
                'label'   => __( 'Facebook Page Slug or ID', 'fbps'),
                'default' => '',
                'desc'    => __( 'facebook Page ID. e.g: 209526793530' )
            ),

            array(
                'name'    => 'post_status',
                'label'   => __( 'Default Post Status', 'fbps'),
                'default' => 'publish',
                'type'    => 'select',
                'options' => get_post_statuses(),
                'desc'    => __( 'Default post status' )
            ),
            array(
                'name'    => 'comment_status',
                'label'   => __( 'Default Comment Status', 'fbps'),
                'default' => 'open',
                'type'    => 'select',
                'options' => array(
                    'open'   => __( 'Open', 'fbps' ),
                    'closed' => __( 'Closed', 'fbps' )
                ),
            ),
        );

        return $settings_fields;
    }

    function settings_page() {
        echo '<div class="wrap">';

        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();

        $this->settings_api->show_import();

        echo '</div>';
    }
}
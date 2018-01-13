<?php
/**
 * Activation handler
 *
 * @package     EDD\ActivationHandler
 * @since       1.0.0
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


/**
 * EDD Extension Activation Handler Class
 *
 * @since       1.0.0
 */
class EDD_Extension_Activation {

    public $plugin_name, $plugin_path, $plugin_file, $has_edd, $edd_base;

    /**
     * Setup the activation class
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function __construct( $plugin_path, $plugin_file ) {
        // We need plugin.php!
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        $plugins = get_plugins();

        // Set plugin directory
        $plugin_path = array_filter( explode( '/', $plugin_path ) );
        $this->plugin_path = end( $plugin_path );

        // Set plugin file
        $this->plugin_file = $plugin_file;

        // Set plugin name
        if( isset( $plugins[$this->plugin_path . '/' . $this->plugin_file]['Name'] ) ) {
            $this->plugin_name = str_replace( 'Easy Digital Downloads - ', '', $plugins[$this->plugin_path . '/' . $this->plugin_file]['Name'] );
        } else {
            $this->plugin_name = __( 'This plugin', 'edd' );
        }

        // Is EDD installed?
        foreach( $plugins as $plugin_path => $plugin ) {
            if( $plugin['Name'] == 'Easy Digital Downloads' ) {
                $this->has_edd = true;
                $this->edd_base = $plugin_path;
                break;
            }
        }
    }


    /**
     * Process plugin deactivation
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function run() {
        // Display notice
        add_action( 'admin_notices', array( $this, 'missing_edd_notice' ) );
    }


    /**
     * Display notice if EDD isn't installed
     *
     * @access      public
     * @since       1.0.0
     * @return      string The notice to display
     */
    public function missing_edd_notice() {
        if( $this->has_edd ) {
            $url  = esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $this->edd_base ), 'activate-plugin_' . $this->edd_base ) );
            $link = '<a href="' . $url . '">' . __( 'activate it', 'edd-shipping-commissions' ) . '</a>';
        } else {
            $url  = esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=easy-digital-downloads' ), 'install-plugin_easy-digital-downloads' ) );
            $link = '<a href="' . $url . '">' . __( 'install it', 'edd-shipping-commissions' ) . '</a>';
        }

        echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires Easy Digital Downloads! Please %s to continue!', 'edd-shipping-commissions' ), $link ) . '</p></div>';
    }
}


/**
 * EDD Commissions Extension Activation Handler Class
 *
 * @since       1.0.0
 */
class EDD_Commissions_Activation {

    public $plugin_name, $plugin_path, $plugin_file, $has_edd_commissions;

    /**
     * Setup the activation class
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function __construct( $plugin_path, $plugin_file ) {
        // We need plugin.php!
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $plugins = get_plugins();
        // Set plugin directory
        $plugin_path = array_filter( explode( '/', $plugin_path ) );
        $this->plugin_path = end( $plugin_path );
        // Set plugin file
        $this->plugin_file = $plugin_file;
        // Set plugin name
        if ( isset( $plugins[$this->plugin_path . '/' . $this->plugin_file]['Name'] ) ) {
            $this->plugin_name = str_replace( 'Easy Digital Downloads - ', '', $plugins[$this->plugin_path . '/' . $this->plugin_file]['Name'] );
        } else {
            $this->plugin_name = __( 'This plugin', 'edd' );
        }
        // Is EDD installed?
        foreach ( $plugins as $plugin_path => $plugin ) {
            if ( $plugin['Name'] == 'Easy Digital Downloads - Commissions' ) {
                $this->has_edd_commissions = true;
                break;
            }
        }
    }


    /**
     * Process plugin deactivation
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function run() {
        // Display notice
        add_action( 'admin_notices', array( $this, 'missing_commissions_notice' ) );
    }


    /**
     * Display notice if EDD isn't installed
     *
     * @access      public
     * @since       1.0.0
     * @return      string The notice to display
     */
    public function missing_commissions_notice() {
        if ( $this->has_edd_commissions ) {
            echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires %sEDD Commissions%s. Please activate it to continue.', 'edd-shipping-commissions' ), '<a href="https://easydigitaldownloads.com/downloads/commissions/" title="EDD Commissions" target="_blank">', '</a>' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires %sEDD Commissions%s. Please install it to continue.', 'edd-shipping-commissions' ), '<a href="https://easydigitaldownloads.com/downloads/commissions/" title="EDD Commissions" target="_blank">', '</a>' ) . '</p></div>';
        }
    }
}


/**
 * EDD Simple Shipping Extension Activation Handler Class
 *
 * @since       1.0.0
 */
class EDD_Simple_Shipping_Activation {

    public $plugin_name, $plugin_path, $plugin_file, $has_edd_simple_shipping;

    /**
     * Setup the activation class
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function __construct( $plugin_path, $plugin_file ) {
        // We need plugin.php!
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $plugins = get_plugins();
        // Set plugin directory
        $plugin_path = array_filter( explode( '/', $plugin_path ) );
        $this->plugin_path = end( $plugin_path );
        // Set plugin file
        $this->plugin_file = $plugin_file;
        // Set plugin name
        if ( isset( $plugins[$this->plugin_path . '/' . $this->plugin_file]['Name'] ) ) {
            $this->plugin_name = str_replace( 'Easy Digital Downloads - ', '', $plugins[$this->plugin_path . '/' . $this->plugin_file]['Name'] );
        } else {
            $this->plugin_name = __( 'This plugin', 'edd' );
        }
        // Is EDD installed?
        foreach ( $plugins as $plugin_path => $plugin ) {
            if ( $plugin['Name'] == 'Easy Digital Downloads - Simple Shipping' ) {
                $this->has_edd_simple_shipping = true;
                break;
            }
        }
    }


    /**
     * Process plugin deactivation
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function run() {
        // Display notice
        add_action( 'admin_notices', array( $this, 'missing_commissions_notice' ) );
    }


    /**
     * Display notice if EDD isn't installed
     *
     * @access      public
     * @since       1.0.0
     * @return      string The notice to display
     */
    public function missing_commissions_notice() {
        if ( $this->has_edd_simple_shipping ) {
            echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires %sEDD Simple Shipping%s. Please activate it to continue.', 'edd-shipping-commissions' ), '<a href="https://easydigitaldownloads.com/downloads/simple-shipping/" title="EDD Simple Shipping" target="_blank">', '</a>' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires %sEDD Simple Shipping%s. Please install it to continue.', 'edd-shipping-commissions' ), '<a href="https://easydigitaldownloads.com/downloads/simple-shipping/" title="EDD Simple Shipping" target="_blank">', '</a>' ) . '</p></div>';
        }
    }
}

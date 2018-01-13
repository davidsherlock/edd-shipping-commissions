<?php
/**
 * Plugin Name:     Easy Digital Downloads - Shipping Commissions
 * Plugin URI:      https://sellcomet.com/downloads/shipping-commissions
 * Description:     Record commissions when a simple shipping payment is marked as shipped.
 * Version:         1.0.0
 * Author:          Sell Comet
 * Author URI:      https://sellcomet.com
 * Text Domain:     edd-shipping-commissions
 * Domain Path:     languages
 *
 * @package         EDD\Shipping_Commissions
 * @author          Sell Comet
 * @copyright       Copyright (c) Sell Comet
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_Shipping_Commissions' ) ) {

    /**
     * Main EDD_Shipping_Commissions class
     *
     * @since       1.0.0
     */
    class EDD_Shipping_Commissions {

        /**
         * @var         EDD_Shipping_Commissions $instance The one true EDD_Shipping_Commissions
         * @since       1.0.0
         */
        private static $instance;


        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Shipping_Commissions
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_Shipping_Commissions();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin version
            define( 'EDD_SHIPPING_COMMISSIONS_VER', '1.0.0' );

            // Plugin path
            define( 'EDD_SHIPPING_COMMISSIONS_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_SHIPPING_COMMISSIONS_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = EDD_SHIPPING_COMMISSIONS_DIR . '/languages/';
            $lang_dir = apply_filters( 'edd_plugin_name_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-shipping-commissions' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-shipping-commissions', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-shipping-commissions/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-shipping-commissions/ folder
                load_textdomain( 'edd-shipping-commissions', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-shipping-commissions/languages/ folder
                load_textdomain( 'edd-shipping-commissions', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-shipping-commissions', false, $lang_dir );
            }
        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {

            if ( is_admin() ) {

                // Process commission records when payment is edited (saved)
                add_action( 'edd_updated_edited_purchase', array( $this, 'process_shipping_commissions' ) );
            }

            // Allow the record commissions process to avoid downloads that have shipping enabled.
            add_filter( 'eddc_should_record_recipient_commissions', array( $this, 'should_record_recipient_commissions' ), 10, 4 );

            // Add a payment note if download has simple shipping enabled
            add_action( 'eddsc_should_record_recipient_commissions', array( $this, 'should_record_payment_note' ), 10, 7 );

            // Record a payment note about this commission
            add_action( 'eddsc_insert_commission', 'eddc_record_commission_note', 10, 6 );

            // Run daily cron job to process commissions for all orders marked as "Shipped"
            add_action( 'edd_daily_scheduled_events', array( $this, 'process_shipped_orders' ), 10 );
        }


        /**
         * Allow the record recipient commissions process to avoid downloads that have shipping enabled.
         *
         * @since       1.0.0
         * @param       boolean $record_commissions
         * @param       integer $recipient
         * @param       integer $download_id
         * @param       integer $payment_id
         * @return      boolean
         */
        public function should_record_recipient_commissions( $record_commissions, $recipient, $download_id, $payment_id ) {

            // Bail and return true if saving edited payment record or running cron job
            if ( doing_action( 'edd_updated_edited_purchase') || doing_action( 'edd_daily_scheduled_events' ) ) {
                return true;
            }

            $payment = new EDD_Payment( $payment_id );

            $has_shipping = false;

            // Loop through each purchased download, get the price_id and check for shipping
        	foreach ( $payment->cart_details as $cart_index => $cart_item ) {
                if ( absint( $cart_item['id'] ) == absint( $download_id ) ) {

                    // But if we have price variations, then we need to get the name of the variation
                    $has_variable_prices = edd_has_variable_prices( $download_id );

            		if ( $has_variable_prices ) {
            			$price_id  = edd_get_cart_item_price_id( $cart_item );
            		}

                    $price_id = isset( $price_id ) ? $price_id : NULL;

                    // Check if item has shipping enabled
    				if( $this->item_has_shipping( $download_id, $price_id ) ) {
    					$has_shipping = true;
    					break;
    				}
                }
            }

        	if ( $has_shipping ) {
                $record_commissions = false;
        	}

            do_action( 'eddsc_should_record_recipient_commissions', $record_commissions, $recipient, $download_id, $price_id, $has_variable_prices, $payment_id, $payment );

        	return $record_commissions;
        }


        /**
         * Process Shipping Commissions
         *
         * @since       1.0
         * @param       int $payment_id The ID of a given payment
         * @return      void
         */
        public function record_shipping_commissions( $payment_id ) {
            // If we were passed a numeric value as the payment id (which it should be)
            if ( ! is_object( $payment_id ) && is_numeric( $payment_id ) ) {
                $payment = new EDD_Payment( $payment_id );
            } else {
                // In case we happened to be passed an EDD_Payment object as the $payment_id, reset the $payment_id variable to be the int payment ID.
                $payment    = $payment_id;
                $payment_id = $payment->ID;
            }

            $commissions_calculated = eddc_calculate_payment_commissions( $payment_id );

            // If there are no commission recipients set up, trigger an action and return.
            if ( empty( $commissions_calculated ) ) {
                do_action( 'eddsc_no_commission_recipients', $payment_id );
                return;
            }

            $user_info = $payment->user_info;

            // loop through each calculated commission and award commissions
            foreach ( $commissions_calculated as $commission_calculated ) {

                // Bail if the commission amount is $0 and the zero-value setting is disabled
                if ( (float) $commission_calculated['commission_amount'] === (float) 0 && edd_get_option( 'edd_commissions_allow_zero_value', 'yes' ) == 'no' ) {
                    continue;
                }

                $default_commission_calculated = array(
                    'recipient'             => 0,
                    'commission_amount'     => 0,
                    'rate'                  => 0.00,
                    'download_id'           => 0,
                    'payment_id'            => 0,
                    'currency'              => NULL,
                    'has_variable_prices'   => NULL,
                    'price_id'              => NULL,
                    'variation'             => NULL,
                    'cart_item'             => NULL,
                    'cart_index'            => 0,
                );

                $commission_calculated = wp_parse_args(	$commission_calculated, $default_commission_calculated );

                $commission_calculated['download_id'] = absint( $commission_calculated['download_id'] );

                $commission = new EDD_Commission;
                $commission->status      = 'unpaid';
                $commission->cart_index  = $commission_calculated['cart_index'];
                $commission->user_id     = $commission_calculated['recipient'];
                $commission->rate        = $commission_calculated['rate'];
                $commission->amount      = $commission_calculated['commission_amount'];
                $commission->currency    = $commission_calculated['currency'];
                $commission->download_id = (int) $commission_calculated['download_id'];
                $commission->payment_id  = $payment_id;
                $commission->type        = eddc_get_commission_type( $commission_calculated['download_id'] );

                // If we are dealing with a variation, then save variation info
                if ( $commission_calculated['has_variable_prices'] && ! empty( $commission_calculated['variation'] ) ) {
                    $commission->price_id = $commission_calculated['price_id'];
                }

                $price_id = isset( $commission->price_id ) ? $commission->price_id : NULL;

                // Skip download if it does not have shipping enabled
                if( ! $this->item_has_shipping( $commission->download_id, $price_id ) ) {
                    continue;
                }

                // If it's a renewal, save that detail
                if ( ! empty( $commission_calculated['cart_item']['item_number']['options']['is_renewal'] ) ) {
                    $commission->is_renewal = true;
                }

                $commission->save();

                $args = array(
                    'user_id'  => $commission->user_id,
                    'rate'     => $commission->rate,
                    'amount'   => $commission->amount,
                    'currency' => $commission->currency,
                    'type'     => $commission->type,
                );

                $commission_info = apply_filters( 'edd_commission_info', $args, $commission->ID, $commission->payment_ID, $commission->download_ID );
                $items_changed   = false;
                foreach ( $commission_info as $key => $value ) {
                    if ( $value === $args[ $key ] ) {
                        continue;
                    }

                    $commission->$key = $value;
                    $items_changed    = true;
                }

                if ( $items_changed ) {
                    $commission->save();
                }

                do_action( 'eddsc_insert_commission', $commission_calculated['recipient'], $commission_calculated['commission_amount'], $commission_calculated['rate'], $commission_calculated['download_id'], $commission->ID, $payment_id );
            }
        }


        /**
         * Process the commissions when the payment is saved
         *
         * @since 1.0.0
         * @access      public
         * @param       integer $payment_id The ID of a given payment
         * @return      void
         */
        public function process_shipping_commissions( $payment_id = 0 ) {
            $processed = get_post_meta( $payment_id, '_edd_payment_shipping_commissions_processed', true );

            if ( ! $processed && isset( $_POST['edd-payment-shipped'] ) ) {
                $this->record_shipping_commissions( $payment_id );
                update_post_meta( $payment_id, '_edd_payment_shipping_commissions_processed', true );
            }
        }


        /**
    	 * Determine if a product has shipping enabled
    	 *
    	 * @since       1.0.0
    	 * @access      public
         * @param       integer $item_id The download ID
         * @param       integer $price_id The download ID price option ID
    	 * @return      boolean
    	 */
    	public function item_has_shipping( $item_id = 0, $price_id = 0 ) {
    		$enabled          = get_post_meta( $item_id, '_edd_enable_shipping', true );
    		$variable_pricing = edd_has_variable_prices( $item_id );

    		if( $variable_pricing && ! $this->price_option_has_shipping( $item_id, $price_id ) ) {
    			$enabled = false;
    		}

    		return (bool) apply_filters( 'eddsc_item_has_shipping', $enabled, $item_id );
    	}


    	/**
    	 * Determine if a price option has snipping enabled
    	 *
         * @since       1.0.0
    	 * @access      public
         * @param       integer $item_id The download ID
         * @param       integer $price_id The download ID price option ID
    	 * @return      boolean
    	 */
    	public function price_option_has_shipping( $item_id = 0, $price_id = 0 ) {
    		$prices = edd_get_variable_prices( $item_id );
    		$ret    = false;

    		// Backwards compatibility checks
    		$has_shipping = isset( $prices[ $price_id ]['shipping'] ) ? $prices[ $price_id ]['shipping'] : false;
    		if ( false !== $has_shipping && ! is_array( $has_shipping ) ) {
    			$ret = true;
    		} elseif ( is_array( $has_shipping ) ) {
    			$domestic = $has_shipping['domestic'];
    			$international = $has_shipping['international'];

    			// If the price has either domestic or international prices, we have shipping.
    			$ret = ( ! empty( $domestic ) || ! empty( $international ) ) ? true : false;
    		}

    		return (bool) apply_filters( 'eddsc_price_option_has_shipping', $ret, $item_id, $price_id );
    	}


        /**
         * Add a payment note if the commission record was skipped because shipping is enabled
         *
         * @access      public
         * @since       1.0.0
         * @param       boolean $record_commission
         * @param       integer $recipient The WordPress user ID of the commission record
         * @param       integer $download_id The download ID of the commission record
         * @param       integer $price_id The price ID of the commission record
         * @param       boolean $has_variable_prices
         * @param       integer $payment_id The payment ID of the commission record
         * @param       object $payment The payment object of the commission record
         * @return      void
         */
        public function should_record_payment_note( $record_commissions, $recipient, $download_id, $price_id, $has_variable_prices, $payment_id, $payment ) {
            if ( false === (bool) $record_commissions ) {
                $processed = get_post_meta( $payment_id, '_edd_payment_shipping_commissions_processed', true );

                if ( ! $processed ) {
                    $download = new EDD_Download( $download_id );
                    $download_name = ( $has_variable_prices ) ? $download->get_name() . ' - ' . edd_get_price_option_name( $download_id, $price_id ) : $download->get_name();
                    $payment->add_note( sprintf( __( 'Commission for %s skipped because %s has shipping enabled.', 'edd-shipping-commissions' ), get_userdata( $recipient )->display_name, $download_name ) );
                }
            }
        }


        /**
         * Processes commissions for all orders marked as "Shipped" on a daily basis.
         *
         * This function is only intended to be used by WordPress cron.
         *
         * @access      public
         * @since       1.0.0
         * @return      void
        */
        public function process_shipped_orders() {

        	// Bail if not in WordPress cron
        	if ( ! edd_doing_cron() ) {
        		return;
        	}

        	$args = array(
        		'status'     => 'complete',
        		'number'     => -1,
        		'output'     => 'edd_payments',
        		'meta_key'   => '_edd_payment_shipping_status',
        		'meta_value' => 2,
        	);

        	$payments = edd_get_payments( $args );

        	if ( $payments ) {
        		foreach( $payments as $payment ) {
        			$processed = get_post_meta( $payment->ID, '_edd_payment_shipping_commissions_processed', true );

        			if ( ! $processed ) {
        				$this->record_shipping_commissions( $payment->ID );
        				update_post_meta( $payment->ID, '_edd_payment_shipping_commissions_processed', true );
        			}
        		}
        	}
        }
    }
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Shipping_Commissions
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Shipping_Commissions The one true EDD_Shipping_Commissions
 */
function EDD_Shipping_Commissions_load() {
    if ( ! class_exists( 'Easy_Digital_Downloads' ) || ! class_exists( 'EDDC' ) || ! class_exists( 'EDD_Simple_Shipping' ) ) {
        if ( ! class_exists( 'EDD_Extension_Activation' ) || ! class_exists( 'EDD_Commissions_Activation' ) || ! class_exists( 'EDD_Simple_Shipping_Activation' ) ) {
          require_once 'includes/class-activation.php';
        }

        // Easy Digital Downloads activation
		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			$edd_activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
			$edd_activation = $edd_activation->run();
		}

        // Commissions activation
		if ( ! class_exists( 'EDDC' ) ) {
			$edd_commissions_activation = new EDD_Commissions_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
			$edd_commissions_activation = $edd_commissions_activation->run();
		}

        // Simple Shipping activation
        if ( ! class_exists( 'EDD_Simple_Shipping') ) {
            $edd_simple_shipping_activation = new EDD_Simple_Shipping_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
            $edd_simple_shipping_activation = $edd_simple_shipping_activation->run();
        }

    } else {

      return EDD_Shipping_Commissions::instance();
    }
}
add_action( 'plugins_loaded', 'EDD_Shipping_Commissions_load' );

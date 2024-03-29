<?php

namespace Automater\WC;

use Automater\WC\Notice;
use WC_Admin_Settings;
use WC_Integration;
use WC_Product;

class Integration extends WC_Integration {
	protected $api_key;
	protected $api_secret;
	protected $debug_log;
	protected $enable_cron_job;

	public function get_api_key() {
		return $this->api_key;
	}

	public function get_api_secret() {
		return $this->api_secret;
	}

	public function get_debug_log() {
		return $this->debug_log;
	}

	public function get_enable_cron_job() {
		return $this->enable_cron_job;
	}

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->init_integration();
		$this->init_form_fields();
		$this->init_settings();
		$this->load_settings();
		$this->init_admin_ajax_hooks();
		$this->display_status_message();
		$this->init_order_hooks();
		$this->init_cron_job();
		$this->maybe_create_product_attribute();
	}

	protected function init_integration() {
		$this->id                 = 'automater-integration';
		$this->method_title       = __( 'Automater', 'automater' );
		$this->method_description = sprintf( __( 'An integration with %s', 'automater' ), '<a href="https://automater.com" target="_blank">Automater</a>' );
		$link_import              = admin_url( 'admin-ajax.php?action=import_automater_products&nonce=' . wp_create_nonce( 'import_automater_products_nonce' ) );
		$link_stocks              = admin_url( 'admin-ajax.php?action=update_automater_stocks&nonce=' . wp_create_nonce( 'update_automater_stocks_nonce' ) );
		$this->method_description .= '<br><br><a href="' . $link_import . '" class="page-title-action">' . __( 'Import products from your Automater account', 'automater' ) . '</a>';
		$this->method_description .= '<br><br><a href="' . $link_stocks . '" class="page-title-action">' . __( 'Update products stocks', 'automater' ) . '</a>';
	}

	public function init_form_fields() {
		$this->form_fields = [
			'api_key'         => [
				'title'       => __( 'API Key', 'automater' ),
				'type'        => 'text',
				'description' => __( 'Login to Automater and get keys from Settings / settings / API', 'automater' ),
				'desc_tip'    => true,
				'default'     => ''
			],
			'api_secret'      => [
				'title'       => __( 'API Secret', 'automater' ),
				'type'        => 'text',
				'description' => __( 'Login to Automater and get keys from Settings / settings / API', 'automater' ),
				'desc_tip'    => true,
				'default'     => ''
			],
			'debug_log'       => [
				'title'       => __( 'Debug Log', 'automater' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable the logging of API calls and errors', 'automater' ),
				'desc_tip'    => true,
				'default'     => 'no'
			],
			'enable_cron_job' => [
				'title'       => __( 'Synchronize stocks every 5 minutes', 'automater' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable cron job to synchronize products stocks', 'automater' ),
				'desc_tip'    => true,
				'default'     => 'no'
			]
		];
	}

	protected function load_settings() {
		add_action( 'woocommerce_update_options_integration_' . $this->id, [ $this, 'process_admin_options' ] );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, [ $this, 'sanitize_settings' ] );

		$this->api_key         = $this->get_option( 'api_key' );
		$this->api_secret      = $this->get_option( 'api_secret' );
		$this->debug_log       = $this->get_option( 'debug_log' ) === 'yes';
		$this->enable_cron_job = $this->get_option( 'enable_cron_job' ) === 'yes';

		// Display an admin notice, if setup is required.
		add_action( 'admin_notices', [ $this, 'maybe_display_admin_notice' ] );
	}

	protected function init_admin_ajax_hooks() {
		add_action( 'wp_ajax_import_automater_products', [
			new Synchronizer( $this ),
			'import_automater_products_to_wp_terms'
		] );
		add_action( 'wp_ajax_update_automater_stocks', [
			new Synchronizer( $this ),
			'update_products_stocks_with_automater_stocks'
		] );
	}

	protected function display_status_message() {
		if ( isset( $_REQUEST['import'] ) && $_REQUEST['import'] === 'success' ) {
			WC_Admin_Settings::add_message( __( 'Products import success.', 'automater' ) );
		} elseif ( isset( $_REQUEST['import'] ) && $_REQUEST['import'] === 'failed' ) {
			WC_Admin_Settings::add_error( __( 'Products import failed. Check logs.', 'automater' ) );
		} elseif ( isset( $_REQUEST['import'] ) && $_REQUEST['import'] === 'nothing' ) {
			WC_Admin_Settings::add_message( __( 'Nothing new to import.', 'automater' ) );
		}

		if ( isset( $_REQUEST['update'] ) && $_REQUEST['update'] === 'success' ) {
			WC_Admin_Settings::add_message( __( 'Stocks update success.', 'automater' ) );
		} elseif ( isset( $_REQUEST['update'] ) && $_REQUEST['update'] === 'nothing' ) {
			WC_Admin_Settings::add_message( __( 'Nothing to update.', 'automater' ) );
		}
	}

	protected function init_order_hooks() {
		// Hook order placed
        add_action( 'woocommerce_checkout_update_order_meta', [ new OrderProcessor( $this ), 'order_placed' ] );
        add_action( 'woocommerce_order_status_changed', [ new OrderProcessor( $this ), 'order_placed' ] );

        // Hook order processing (paid)
		add_action( 'woocommerce_order_status_processing', [ new OrderProcessor( $this ), 'order_processing' ] );
	}

	protected function init_cron_job() {
		$synchronizer = new Synchronizer( $this );
		$synchronizer->init_cron_job();
	}

	public function maybe_create_product_attribute() {
		global $wpdb;

		$attribute_name = 'automater_product';

		$exists = $wpdb->get_var(
			$wpdb->prepare( "
                    SELECT attribute_id
                    FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies
                    WHERE attribute_name = %s",
				$attribute_name )
		);

		if ( ! $exists ) {
			wc_get_logger()->notice( "Automater: Create product attribute '$attribute_name'" );
			$wpdb->insert( $wpdb->prefix . "woocommerce_attribute_taxonomies", [
				'attribute_name'    => $attribute_name,
				'attribute_label'   => __( 'Automater Product', 'automater' ),
				'attribute_type'    => 'select',
				'attribute_orderby' => 'menu_order',
				'attribute_public'  => 0,
			] );
			delete_transient( 'wc_attribute_taxonomies' );
		}
	}

	public function api_enabled() {
		return $this->valid_key( $this->api_key, false ) && $this->valid_key( $this->api_secret, false );
	}

	/**
	 * Sanitize our settings
	 */
	public function sanitize_settings( $settings ) {
		if ( isset( $settings ) ) {
			if ( isset( $settings['api_key'] ) ) {
				$settings['api_key'] = trim( $settings['api_key'] );
			}
			if ( isset( $settings['api_secret'] ) ) {
				$settings['api_secret'] = trim( $settings['api_secret'] );
			}
		}

		return $settings;
	}

	public function validate_api_key_field( $key, $value ) {
		$value = trim( $value );
		if ( ! $this->valid_key( $value ) ) {
			WC_Admin_Settings::add_error( __( 'Looks like you made a mistake with the API Key field. Make sure it is 32 characters copied from Automater settings', 'automater' ) );
		}

		return $value;
	}

	public function validate_api_secret_field( $key, $value ) {
		$value = trim( $value );
		if ( ! $this->valid_key( $value ) ) {
			WC_Admin_Settings::add_error( __( 'Looks like you made a mistake with the API Secret field. Make sure it is 32 characters copied from Automater settings', 'automater' ) );
		}

		return $value;
	}

	protected function valid_key( $value, $can_be_empty = true ) {
		$valid = false;
		if ( $can_be_empty ) {
			$valid = $valid || ! isset( $value ) || $value === '';
		}
		$valid = $valid || strlen( $value ) === 32;

		return $valid;
	}

	/**
	 * Display an admin notice, if not on the integration screen and if the account isn't yet connected.
	 */
	public function maybe_display_admin_notice() {
		if ( isset( $_GET['page'], $_GET['tab'] ) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'integration' ) {
			return;
		}
		if ( $this->api_key && $this->api_secret ) {
			return;
		}

		$url = $this->get_settings_url();
		Notice::render_notice( __( 'Automater integration is almost ready. To get started, connect your account by providing API Keys.', 'automater' ) . ' <a href="' . esc_url( $url ) . '">' . __( 'Go to settings', 'automater' ) . '</a>' );
	}

	/**
	 * Generate a URL to our specific settings screen.
	 */
	public function get_settings_url() {
		$url = admin_url( 'admin.php' );
		$url = add_query_arg( 'page', 'wc-settings', $url );
		$url = add_query_arg( 'tab', 'integration', $url );

		return $url;
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return array|bool
	 */
	public function get_automater_product_id_for_wc_product( $product ) {
		$attributes = $product->get_attributes();
		$attribute  = 'automater_product';

		$attribute_object = false;
		if ( isset( $attributes[ $attribute ] ) ) {
			$attribute_object = $attributes[ $attribute ];
		} elseif ( isset( $attributes[ 'pa_' . $attribute ] ) ) {
			$attribute_object = $attributes[ 'pa_' . $attribute ];
		}
		if ( ! $attribute_object ) {
			$automater_product_id = false;
		} else {
			$automater_product_id = wc_get_product_terms( $product->get_id(), $attribute_object->get_name(), [ 'fields' => 'slugs' ] );
			$automater_product_id = array_values( $automater_product_id )[0];
		}

		return $automater_product_id;
	}
}

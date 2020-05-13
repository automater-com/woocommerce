<?php

namespace Automater\WC;

use Automater\WC\Notice;
use Automater\WC\Integration;

class Register {
	/**
	 * Initialize WooCommerce integration.
	 */
	public static function register_wc_integration() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Register the integration.
			add_filter( 'woocommerce_integrations', [ self::class, 'add_integration' ] );
		} else {
			add_action( 'admin_notices', function () {
				Notice::render_error( __( 'Unable to register Automater integration. Is WooCommerce installed?', 'automater' ) );
			} );
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public static function add_integration( $integrations ) {
		$integrations[] = Integration::class;

		return $integrations;
	}
}

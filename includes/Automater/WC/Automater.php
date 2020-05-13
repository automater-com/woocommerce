<?php

namespace Automater\WC;

use Automater\WC\Integration;

class Automater {
	/** @var Automater */
	protected static $instance;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		$this->set_locale();
		$this->init_wc_actions();
	}

	private function set_locale() {
		add_action( 'plugins_loaded', [ di_automater( I18n::class ), 'load_plugin_textdomain' ] );
	}

	private function init_wc_actions() {
		add_action( 'plugins_loaded', [ Register::class, 'register_wc_integration' ] );

		if ( $this->wc_active() ) {
			add_filter( 'woocommerce_integrations', [ $this, 'init_wc' ] );
		}
	}

	public function init_wc() {
	}

	private function wc_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	}
}

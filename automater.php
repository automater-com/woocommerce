<?php
/**
 * Plugin Name: Automater
 * Plugin URI: https://automater.com
 * Description: WooCommerce integration with Automater
 * Version: 1.0.0
 * Author: Automater
 * Author URI: https://automater.com
 * Requires at least: 5.0
 * Tested up to: 6.4
 *
 * Text Domain: automater
 * Domain Path: /languages
 *
 * WC requires at least: 3.2
 * WC tested up to: 8.5
 *
 * Copyright: © 2017-2024 Automater
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

__( 'WooCommerce integration with Automater', 'automater' );

if ( ! defined( 'AUTOMATER_PLUGIN_FILE' ) ) {
	define( 'AUTOMATER_PLUGIN_FILE', __FILE__ );
}

require_once 'includes/autoload.php';
require_once 'vendor/autoload.php';

use \Automater\WC\Automater;
use \Automater\WC\Activator;
use \Automater\WC\DI;

function activate_automater() {
	Activator::activate();
}

function deactivate_automater() {
	Activator::deactivate();
}

add_action('before_woocommerce_init', function(){
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

register_activation_hook( __FILE__, 'activate_automater' );
register_deactivation_hook( __FILE__, 'deactivate_automater' );

function di_automater( $name ) {
	return DI::getInstance()->getContainer()->get( $name );
}

function automater() {
	return Automater::get_instance();
}

automater();

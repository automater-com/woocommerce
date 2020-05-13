<?php
/**
 * Plugin Name: Automater
 * Plugin URI: https://automater.com
 * Description: WooCommerce integration with Automater
 * Version: 0.2.2
 * Author: Automater sp. z o.o.
 * Author URI: https://automater.com
 * Requires at least: 4.8
 * Tested up to: 4.9
 *
 * Text Domain: automater
 * Domain Path: /languages
 *
 * WC requires at least: 3.2
 * WC tested up to: 4.1
 *
 * Copyright: © 2017-2020 Automater sp. z o.o.
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
require_once 'includes/DI.php';
require_once 'vendor/autoload.php';

use \Automater\WC\Automater;
use \Automater\WC\Activator;

function activate_automater() {
	Activator::activate();
}

function deactivate_automater() {
	Activator::deactivate();
}

register_activation_hook( __FILE__, 'activate_automater' );
register_deactivation_hook( __FILE__, 'deactivate_automater' );

function di_automater( $name ) {
	return DI::getInstance()->getContainer()->get( $name );
}

function automater() {
	return Automater::get_instance();
}

automater();

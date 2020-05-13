<?php

namespace Automater\WC;

class I18n {
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'automater', false, dirname( plugin_basename( AUTOMATER_PLUGIN_FILE ) ) . '/languages' );
	}
}

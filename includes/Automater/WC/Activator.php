<?php

namespace Automater\WC;

use Automater\WC\Synchronizer;

class Activator {
	public static function activate() {
	}

	public static function deactivate() {
		Synchronizer::unschedule_cron_job();
	}
}

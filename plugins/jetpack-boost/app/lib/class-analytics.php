<?php

namespace Automattic\Jetpack_Boost\Lib;

use Automattic\Jetpack\Connection\Manager;
use Automattic\Jetpack\Tracking;

class Analytics {
	public static function get_tracking() {
		return new Tracking( 'jetpack_boost', new Manager( 'jetpack-boost' ) );
	}

	public static function record_user_event( $slug, $data = array() ) {
		return self::get_tracking()->record_user_event( $slug, $data );
	}
}

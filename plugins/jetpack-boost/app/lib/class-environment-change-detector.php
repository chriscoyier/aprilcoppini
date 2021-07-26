<?php
/**
 * Environment Change Detector class.
 *
 * @link       https://automattic.com
 * @since      1.0.0
 */

namespace Automattic\Jetpack_Boost\Lib;

/**
 * Class Environment_Change_Detector.
 */
class Environment_Change_Detector {

	public static function init() {
		add_action(
			'after_switch_theme',
			function () {
				self::handle_theme_change( 'switched_theme' );
			}
		);
		// Add more action here handle changes that will require action by the plugin.
	}

	/**
	 * @param $change_type
	 */
	public static function handle_theme_change( $change_type ) {
		do_action( 'handle_theme_change', $change_type );
	}
}

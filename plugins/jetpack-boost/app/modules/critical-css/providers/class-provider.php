<?php

namespace Automattic\Jetpack_Boost\Modules\Critical_CSS\Providers;

/**
 * Class Provider
 *
 * @package Automattic\Jetpack_Boost\Modules\Critical_CSS\Providers
 */
abstract class Provider {

	protected static $name;

	/**
	 * Each provider must return a list of URLs to generate CSS from
	 *
	 * @return array
	 */
	abstract public static function get_critical_source_urls();

	/**
	 * What key should this provider look for during the current request?
	 * Used in the front-end to determine where the CSS
	 * might be stored for the current request.
	 *
	 * @return array
	 */
	abstract public static function get_current_storage_keys();

	/**
	 * Returns a list of all keys that this provider can provide, regardless
	 * of the current URL.
	 */
	abstract public static function get_keys();

	/**
	 * Get the name of the provider.
	 *
	 * @return string
	 */
	public static function get_provider_name() {
		return static::$name;
	}

	/**
	 * Returns the ratio of valid urls from the provider source urls
	 * for the Critical CSS generation to be considered successful.
	 *
	 * @return array
	 */
	abstract public static function get_success_ratio();
}

<?php

namespace Automattic\Jetpack_Boost\Modules\Critical_CSS;

use Automattic\Jetpack_Boost\Lib\Storage_Post_Type;

class Critical_CSS_Storage {

	/**
	 * @var Storage_Post_Type
	 */
	protected $storage;

	/**
	 * Critical_CSS_Storage constructor.
	 */
	public function __construct() {
		$this->storage = new Storage_Post_Type( 'css' );
	}

	public function store_css( $key, $value ) {
		$this->storage->set(
			$key,
			array(
				'css' => $value,
			)
		);
	}

	public function clear() {
		$this->storage->clear();
	}

	public function get_css( $possible_keys ) {
		foreach ( $possible_keys as $key ) {
			$data = $this->storage->get( $key, false );
			if ( $data && $data['css'] ) {
				return array(
					'key' => $key,
					'css' => $data['css'],
				);
			}
		}

		return false;
	}
}

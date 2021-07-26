<?php

namespace Automattic\Jetpack_Boost\Modules\Critical_CSS;

use Automattic\Jetpack_Boost\Lib\Transient;
use Automattic\Jetpack_Boost\Modules\Critical_CSS\Providers\Provider;

class Critical_CSS_State {

	const NOT_GENERATED = 'not_generated';
	const SUCCESS = 'success';
	const FAIL = 'error';
	const REQUESTING = 'requesting';

	const KEY = 'critical_css_state';

	protected $state;

	/**
	 * Formatted sources array created from Providers
	 *
	 * @see get_provider_sources
	 * @var array
	 */
	protected $sources = array();

	/**
	 * @var int $created - Epoch time when was Critical CSS last updated.
	 */
	private $created;

	public function __construct() {

		$state = Transient::get(
			self::KEY,
			array(
				'created' => microtime( true ),
				'state' => null,
				'sources' => array(),
			)
		);

		$this->created = $state['created'];
		$this->state = $state['state'];
		$this->sources = $state['sources'];
	}

	public function save() {
		Transient::set(
			self::KEY,
			array(
				'created' => $this->created,
				'state' => $this->state,
				'sources' => $this->sources,
			)
		);
	}

	public function set_success( $key ) {
		$this->set_source_status( $key, self::SUCCESS );
	}

	public function set_error( $key, $error ) {
		if ( isset( $this->sources[ $key ] ) ) {
			$this->sources[ $key ]['error'] = $error;
			$this->set_source_status( $key, self::FAIL );
		}
	}

	protected function set_source_status( $key, $status ) {
		if ( isset( $this->sources[ $key ] ) ) {
			$this->sources[ $key ]['status'] = $status;
		}

		if ( 100 === $this->get_percent_complete() ) {
			$this->state = self::SUCCESS;
		}

		$this->save();
	}

	/**
	 * Request Critical CSS to be generated based on passed URL providers
	 *
	 * @param $providers
	 */
	public function create_request( $providers ) {
		$this->state = self::REQUESTING;
		$this->sources = $this->get_provider_sources( $providers );
		$this->created = microtime( true );
		$this->save();
	}

	/**
	 * @param $providers
	 *
	 * @return array
	 */
	protected function get_provider_sources( $providers ) {
		$sources = array();

		/***
		 * @var $provider Provider
		 */
		foreach ( $providers as $provider ) {
			$provider_name = $provider::get_provider_name();

			// For each provider,
			// Gather a list of URLs that are going to be used as Critical CSS source.
			foreach ( $provider::get_critical_source_urls() as $group => $urls ) {
				$key = $provider_name . '_' . $group;

				// For each URL
				// Track the state and errors in a state array.
				$sources[ $key ] = array(
					'urls' => $urls,
					'status' => self::REQUESTING,
					'error' => null,
					'success_ratio' => $provider::get_success_ratio(),
				);
			}
		}

		return $sources;
	}

	public function get_errors_from( $keys ) {
		$errors = $this->collate_column( 'error' );

		$results = array();
		foreach ( $errors as $provider_key => $error ) {
			if ( ! in_array( $provider_key, $keys, true ) ) {
				continue;
			}
			$results[ $provider_key ] = $error;
		}

		return array_filter( $results );
	}

	/**
	 * Returns the start time of this Critical CSS request.
	 *
	 * @return int
	 */
	public function get_created_time() {
		return $this->created;
	}

	/**
	 * Returns true if all provider keys have finished processing (whether successful or not).
	 *
	 * @return bool
	 */
	public function is_done() {
		return self::SUCCESS === $this->state;
	}

	public function is_empty() {
		return empty( $this->state );
	}

	public function is_pending() {
		return self::REQUESTING === $this->state;
	}

	/**
	 * Given a column, collate all provider sources returning the specified
	 * column for each one.
	 */
	private function collate_column( $column ) {
		$results = array_fill_keys( array_keys( $this->sources ), array() );

		foreach ( $this->sources as $source_key => $source ) {
			if ( isset( $source[ $column ] ) ) {
				$results[ $source_key ] = $source[ $column ];
			}
		}

		return $results;
	}

	public function get_source_urls() {
		return $this->collate_column( 'urls' );
	}

	public function get_provider_success_ratios() {
		return $this->collate_column( 'success_ratio' );
	}

	/**
	 * Returns the number of requests that were successfully finished. i.e.: number of blocks stored.
	 *
	 * @return int
	 */
	public function get_success_count() {
		$source_status = $this->collate_column( 'status' );
		$successes = array();
		foreach ( $source_status as $status ) {
			/**
			 * Note:
			 * "Success" is currently considered anything that's not Requesting.
			 * That's because Critical CSS generation can also end with failure.
			 */
			if ( self::REQUESTING !== $status ) {
				$successes[] = $status;
			}
		}

		return count( $successes );
	}

	/**
	 * Returns the percentage of requests that are finished processing (whether successful or failed)
	 *
	 * @return int
	 */
	public function get_percent_complete() {
		return $this->get_success_count() * 100 / max( 1, count( $this->sources ) );
	}

	public function reset() {
		Transient::delete( self::KEY );
	}
}

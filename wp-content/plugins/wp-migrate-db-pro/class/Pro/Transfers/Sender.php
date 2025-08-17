<?php

namespace DeliciousBrains\WPMDB\Pro\Transfers;

use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\Payload;
use DeliciousBrains\WPMDB\Common\Transfers\Files\Util;

class Sender {

	static $end_sig = '###WPMDB_EOF###';
	static $end_bucket = '###WPMDB_END_BUCKET###';
	static $start_payload = '###WPMDB_PAYLOAD###';
	static $end_payload = '###WPMDB_END_PAYLOAD###';
	static $start_meta = '###WPMDB_START_META###';
	static $end_meta = '###WPMDB_END_META###';
	static $start_bucket_meta = '####WPMDB_BUCKET_META####';
	static $end_bucket_meta = '###WPMDB_END_BUCKET_META###';

	public $util;
	public $payload;

	/**
	 * Sender constructor.
	 *
	 * @param Util    $util
	 * @param Payload $payload
	 */
	public function __construct(
		Util $util,
		Payload $payload
	) {
		$this->util    = $util;
		$this->payload = $payload;
	}

	/**
	 * HTTP POST payload to remote site
	 *
	 * @param string $payload
	 * @param string $url
	 *
	 * @return \Requests_Response
	 */
	public function post_payload( $payload, $state_data, $url = '' ) {
		$requests_options = $this->util->get_requests_options();
		$options          = apply_filters( 'wpmdb_transfers_requests_options', $requests_options );

		//Prepare to send file as a stream
        $meta_data = stream_get_meta_data($payload);
        $filename  = $meta_data["uri"];

		//Encode state data as json to prevent issues with CURL
		$payload_data = [
			'state_data' => base64_encode(json_encode($state_data)),
			'action'     => $state_data['action']
		];
		//Attach the payload to the request as an octet stream to be received in $_FILES
		$payload_data['content'] = new \CURLFile($filename, 'application/octet-stream', 'payload');

		$hooks = new \Requests_Hooks();
		$hooks->register( 'curl.before_send', function ( $handle ) use ($payload_data) {
			curl_setopt($handle, CURLOPT_POSTFIELDS, $payload_data);
		} );

		$options['hooks'] = $hooks;

		//Set WPE Cookie if it exists
		$remote_cookie = Persistence::getRemoteWPECookie();
		if (false !== $remote_cookie) {
			$options['cookies'] = [
				'wpe-auth'   => $remote_cookie
			];
		}

		return \Requests::post( $url, array(), null, $options );
	}

	/**
	 * @param $state_data
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function respond_to_send_file( $state_data ) {
		if ( ! isset( $_POST['batch'] ) ) {
			throw new \Exception( __( '$_POST[\'batch\'] is empty.', 'wp-migrate-db' ) );
		}

		$batch = filter_var( $_POST['batch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$batch = json_decode( str_rot13( base64_decode( $batch ) ), true );

		if ( ! $batch || ! \is_array( $batch ) ) {
			throw new \Exception( __( 'Request for batch of files failed.', 'wp-migrate-db' ) );
		}

		$handle = $this->payload->create_payload( $batch, $state_data, $state_data['bottleneck'] );
		rewind( $handle );

		// Before outputting anything, make sure buffer is empty and set headers
		// so that services like Cloudflare do not corrupt the stream.
		@ob_clean();
		header( 'content-type: application/octet-stream' );
		header( 'cache-control: no-transform' );

		// Read payload line by line and send each line to the output buffer
		while ( ! feof( $handle ) ) {
			$buffer = fread( $handle, 10 * 10 * 10000 );
			echo $buffer;

			@ob_flush();
			flush();
		}

		fclose( $handle );
		exit;
	}

	protected function print_end() {
		echo "\n" . static::$end_sig;
	}
}

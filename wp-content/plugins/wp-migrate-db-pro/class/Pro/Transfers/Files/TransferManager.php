<?php

namespace DeliciousBrains\WPMDB\Pro\Transfers\Files;

use DeliciousBrains\WPMDB\Common\FullSite\FullSiteExport;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Queue\Manager;
use DeliciousBrains\WPMDB\Common\Transfers\Files\Util;
use DeliciousBrains\WPMDB\Pro\Transfers\Receiver;
use DeliciousBrains\WPMDB\Pro\Transfers\Sender;
use DeliciousBrains\WPMDB\Common\Transfers\Files\TransferManager as Common_TransferManager;

/**
 * Class TransferManager
 *
 * @package WPMDB\Transfers\Files
 */
class TransferManager extends Common_TransferManager
{

    /**
     * TransferManager constructor.
     *
     * @param $wpmdb
     */

    public $queueManager;
    public $payload;
    public $util;
    /**
     * @var Helper
     */
    private $http_helper;
    /**
     * @var Receiver
     */
    private $receiver;
    /**
     * @var Sender
     */
    private $sender;
    /**
     * @var Http
     */
    private $http;

    /**
     * @var SizeControllerInterface
     */
    private $size_controller;

    /**
     * @var DynamicProperties
     */
    private $dynamic_props;

    public function __construct(
        Manager $manager,
        Payload $payload,
        Util $util,
        SizeControllerInterface $size_controller,
        Helper $http_helper,
        Http $http,
        Receiver $receiver,
        Sender $sender,
        FullSiteExport $full_site_export
    ) {
        parent::__construct($manager, $util, $http, $full_site_export);
        $this->queueManager    = $manager;
        $this->payload         = $payload;
        $this->util            = $util;
        $this->http_helper     = $http_helper;
        $this->receiver        = $receiver;
        $this->sender          = $sender;
        $this->http            = $http;
        $this->size_controller = $size_controller;
        $this->dynamic_props   = DynamicProperties::getInstance();
    }

    /**
     * @param array  $processed
     * @param array  $state_data
     * @param string $remote_url
     *
     * @return array
     */
    public function handle_push($processed, $state_data, $remote_url)
    {
        $transfer_max                = $state_data['site_details']['remote']['transfer_bottleneck'];
        $actual_bottleneck           = $state_data['site_details']['remote']['max_request_size'];
        $high_performance_transfers  = $state_data['site_details']['remote']['high_performance_transfers'];
        $force_performance_transfers = isset($state_data['forceHighPerformanceTransfers']) ? $state_data['forceHighPerformanceTransfers'] : false;
        $force_performance_transfers = apply_filters('wpmdb_force_high_performance_transfers', $force_performance_transfers, $state_data);

        $bottleneck                 = apply_filters('wpmdb_transfers_push_bottleneck',
            $actual_bottleneck); //Use slider value
        $fallback_payload_size = 1000000;

        $bottleneck = $this->maybeUseHighPerformanceTransfers($bottleneck, $high_performance_transfers, $force_performance_transfers, $transfer_max,
            $fallback_payload_size, $state_data);

        // Remove 1KB from the bottleneck as some hosts have a 1MB bottleneck
        $bottleneck -= 1000;

        $batch      = [];
        $total_size = 0;

        // Get subset of files to combine into a payload
        foreach ($processed as $key => $file) {
            $batch[] = $file;

            // This is a loose enforcement, actual payload size limit is implemented in Payload::create_payload()
            if (($total_size + $file['size']) >= $bottleneck) {
                break;
            }

            $total_size += $file['size'];
        }

        list($count, $sent, $handle, $chunked, $file, $chunk_data) = $this->payload->create_payload($batch, $state_data, $bottleneck);

        $transfer_status = $this->attempt_post($state_data, $remote_url, $handle);
        $code            = $transfer_status->status_code;

        if (!$transfer_status || 200 !== $code) {
            return $this->util->fire_transfer_errors(sprintf(__('Payload transfer failed with code %s: %s', 'wp-migrate-db'), $code, $transfer_status->body));
        }

        list($total_sent, $sent_copy) = $this->process_sent_data_push($sent, $chunked);

        // Convert 'file data' to 'folder data', as that's how the UI/Client displays progress
        $result = $this->util->process_queue_data($sent_copy, $state_data, $total_sent);

        // If we're not chunking
        if ( empty( $chunked ) ) {
            $this->queueManager->delete_data_from_queue($count);
        }

        if ( ! empty( $chunked ) ) {
            $chunk_option_name = 'wpmdb_file_chunk_' . $state_data['migration_state_id'];

            //chunking is complete, remove file(s) from queue and clean up the file chunk option
            if ( (int) $chunked['bytes_offset'] === $file['size'] ) {
                delete_site_option( $chunk_option_name );
                $file['chunking_done'] = true;

                $this->queueManager->delete_data_from_queue($count);
            } else {
                // Record chunk data to DB for next iteration
                update_site_option( $chunk_option_name, $chunk_data );
            }
        }

        $result['fallback_payload_size'] = $fallback_payload_size;

        if ($this->canUseHighPerformanceTransfers($high_performance_transfers, $force_performance_transfers)) {
            $result['current_payload_size']     = $this->size_controller->get_current_size();
            $result['reached_max_payload_size'] = $this->size_controller->is_at_max_size();
        }

        return $result;
    }

    /**
     * @param array  $processed
     * @param array  $state_data
     * @param string $remote_url
     *
     * @return array
     */
    public function handle_pull($processed, $state_data, $remote_url)
    {
        $transfer_max                = $this->util->get_transfer_bottleneck();
        $actual_bottleneck           = $state_data['site_details']['local']['max_request_size'];
        $high_performance_transfers  = $state_data['site_details']['local']['high_performance_transfers'];
        $force_performance_transfers = isset($state_data['forceHighPerformanceTransfers']) ? $state_data['forceHighPerformanceTransfers'] : false;
        $force_performance_transfers = apply_filters('wpmdb_force_high_performance_transfers', $force_performance_transfers, $state_data);

        $bottleneck                 = apply_filters('wpmdb_transfers_pull_bottleneck',
            $actual_bottleneck); //Use slider value
        $fallback_payload_size = 2500000;

        $bottleneck = $this->maybeUseHighPerformanceTransfers($bottleneck, $high_performance_transfers, $force_performance_transfers, $transfer_max,
            $fallback_payload_size, $state_data);

        $batch      = [];
        $total_size = 0;
        $count      = 0;

        // Assign bottleneck to state data so remote can use it when assembling the payload
        $state_data['bottleneck'] = $bottleneck;

        foreach ($processed as $key => $file) {
            if ($file['size'] > $bottleneck) {
                $batch[] = $file;
                break;
            }

            $batch[] = $file;
            $count++;

            $total_size += $file['size'];
        }

        $stage = $state_data['stage'];
        $key   = $stage === 'media_files' ? 'mf' : 'tp';

        try {
            list($resp, $meta) = $this->request_batch(base64_encode(str_rot13(json_encode($batch))), $state_data, "wpmdb{$key}_transfers_send_file", $remote_url);
        } catch (\Exception $e) {
            $this->util->catch_general_error($e->getMessage());
        }

        //Delete data from queue
        $this->queueManager->delete_data_from_queue($meta['count']);

        $total_sent = 0;

        foreach ($meta['sent'] as $sent) {
            $total_sent += $sent['size'];
        }

        // Convert 'file data' to 'folder data', as that's how the UI/Client displays progress
        $result = $this->util->process_queue_data($meta['sent'], $state_data, $total_sent);

        $result['fallback_payload_size'] = $fallback_payload_size;

        if ($this->canUseHighPerformanceTransfers($high_performance_transfers, $force_performance_transfers)) {
            $result['current_payload_size']     = $this->size_controller->get_current_size();
            $result['reached_max_payload_size'] = $this->size_controller->is_at_max_size();
        }

        return $result;
    }

    /**
     * @param string $payload
     * @param array  $state_data
     * @param string $action
     * @param string $remote_url
     *
     * @return \Requests_Response
     * @throws \Exception
     */
    public function post($payload, $state_data, $action, $remote_url)
    {
        $sig_data = [
            'action'          => $action,
            'remote_state_id' => $state_data['migration_state_id'],
            'intent'          => $state_data['intent'],
            'stage'           => $state_data['stage'],
        ];

        $state_data['sig'] = $this->http_helper->create_signature($sig_data, $state_data['key']);

        $state_data['action']          = $action;
        $state_data['remote_state_id'] = $state_data['migration_state_id'];
        $ajax_url                      = trailingslashit($remote_url) . 'wp-admin/admin-ajax.php';

        $response = $this->sender->post_payload($payload, $state_data, $ajax_url);

        $decoded = json_decode($response->body);

        if (isset($decoded->wpmdb_error)) {
            throw new \Exception($decoded->msg);
        }

        if (isset($decoded->success) && $decoded->success === false) {
            return $this->http->end_ajax(new \WP_Error('wpmdb_transfer_failed', $decoded->data));
        }

        // Returns response directly
        return $response;
    }

    /**
     * @param string $batch
     * @param array  $state_data
     * @param string $action
     * @param string $remote_url
     *
     * @return array
     * @throws \Exception
     */
    public function request_batch($batch, $state_data, $action, $remote_url)
    {
        $data = [
            'action'     => $action,
            'intent'     => $state_data['intent'],
            'stage'      => $state_data['stage'],
            'bottleneck' => $state_data['bottleneck'],
        ];

        $sig_data      = $data;
        $data['sig']   = $this->http_helper->create_signature($sig_data, $state_data['key']);
        $ajax_url      = trailingslashit($remote_url) . 'wp-admin/admin-ajax.php';
        $data['batch'] = $batch;

        try {
            $response = $this->receiver->send_request($data, $ajax_url);
        } catch (\Exception $e) {
            $this->util->catch_general_error($e->getMessage());
        }

        return $this->receiver->receive_stream_batch($response, $state_data);
    }

    /**
     * @param array $sent
     * @param array $chunked
     *
     * @return array
     */
    public function process_sent_data_push($sent, $chunked)
    {
        $total_sent = 0;
        $filtered   = [];

        foreach ($sent as $files_sent) {
            $item_size = $files_sent['size'];

            if (isset($chunked['chunked']) && $chunked['chunked']) {
                $item_size = $chunked['chunk_size'];
            }

            $total_sent               += $item_size;
            $files_sent['chunk_size'] = $item_size;
            $filtered[]               = $files_sent;
        }

        return array($total_sent, $filtered);
    }

    /**
     * @param array    $state_data
     * @param string   $remote_url
     * @param resource $handle
     *
     * @return \Requests_Response
     */
    public function attempt_post($state_data, $remote_url, $handle)
    {
        rewind($handle);
        $stage           = $state_data['stage'];
        $key             = $stage === 'media_files' ? 'mf' : 'tp';

        try {
            $transfer_status = $this->post($handle, $state_data, "wpmdb{$key}_transfers_receive_file", $remote_url);
        } catch (\Exception $e) {
            $this->util->catch_general_error($e->getMessage());
        }

        return $transfer_status;
    }

    /**
     * Calculates the high performance mode bottleneck size.
     *
     * @param array $state_data
     *
     * @return int
     */
    private function calculatePayLoadSize($state_data)
    {
        if ($state_data['stabilizePayloadSize'] !== true) {

            if ($state_data['stepDownSize'] === true) {
                return $this->size_controller->step_down_size($state_data['retries']);
            }

            return $this->size_controller->step_up_size();
        }

        return $this->size_controller->get_current_size();
    }

    /**
     * If high performance mode can be used for current migration, the bottleneck value will be modified.
     * Otherwise, the passed bottleneck will be returned unmodified.
     *
     * @param int $bottleneck
     * @param bool $high_performance_transfers
     * @param bool $force_performance_transfers
     * @param int $transfer_max
     * @param int $fallback
     * @param array $state_data
     *
     * @return int
     */
    private function maybeUseHighPerformanceTransfers(
        $bottleneck,
        $high_performance_transfers,
        $force_performance_transfers,
        $transfer_max,
        $fallback,
        $state_data
    ) {
        if ($this->canUseHighPerformanceTransfers($high_performance_transfers, $force_performance_transfers)) {
            $this->size_controller->initialize($transfer_max, $fallback, isset($state_data['payloadSize']) ? $state_data['payloadSize'] : null);
            $bottleneck = apply_filters('wpmdb_high_performance_transfers_bottleneck',
                $this->calculatePayLoadSize($state_data), $state_data['intent']);
        }

        return $bottleneck;
    }

    /**
     * Checks if high performance mode can be enabled for current migration.
     *
     * @param bool $high_performance_transfers
     * @param bool $force_performance_transfers
     * @return bool
     */
    private function canUseHighPerformanceTransfers($high_performance_transfers, $force_performance_transfers)
    {
        return (true === $high_performance_transfers || true === $force_performance_transfers) && ! $this->dynamic_props->doing_cli_migration;
    }
}

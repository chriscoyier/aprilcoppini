<?php


namespace DeliciousBrains\WPMDB\Pro\Transfers\Files;

use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\Scramble;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\MigrationState\StateDataContainer;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DeliciousBrains\WPMDB\Common\Transfers\Files\FileProcessor;
use DeliciousBrains\WPMDB\Common\Transfers\Files\Util;
use DeliciousBrains\WPMDB\Common\Queue\Manager;
use DeliciousBrains\WPMDB\Pro\Transfers\Receiver;
use DeliciousBrains\WPMDB\Pro\Transfers\Sender;

class PluginHelper extends \DeliciousBrains\WPMDB\Common\Transfers\Files\PluginHelper
{

    /**
     * @var Sender
     */
    protected $sender;
    /**
     * @var Receiver
     */
    protected $receiver;

    public function __construct(
        Filesystem $filesystem,
        Properties $properties,
        Http $http,
        Helper $http_helper,
        Settings $settings,
        MigrationStateManager $migration_state_manager,
        Scramble $scramble,
        FileProcessor $file_processor,
        Util $transfer_util,
        Manager $queue_manager,
        Manager $manager,
        StateDataContainer $state_data_container,
        Sender $sender,
        Receiver $receiver
    ) {
        parent::__construct($filesystem, $properties, $http, $http_helper, $settings, $migration_state_manager, $scramble, $file_processor, $transfer_util, $queue_manager, $manager, $state_data_container);
        $this->sender   = $sender;
        $this->receiver = $receiver;
    }


    /**
     * @return null
     * @throws \Exception
     */
    public function respond_to_post_file()
    {
        $key_rules = array(
            'action'           => 'key',
            'remote_state_id'  => 'key',
            'stage'            => 'string',
            'intent'           => 'string',
            'folders'          => 'array',
            'theme_folders'    => 'array',
            'themes_option'    => 'string',
            'plugin_folders'   => 'array',
            'plugins_option'   => 'string',
            'muplugin_folders' => 'array',
            'muplugins_option' => 'string',
            'other_folders'    => 'array',
            'others_option'    => 'string',
            'sig'              => 'string',
        );

        if(!isset($_POST['state_data'])) {
            throw new \Exception(__('Failed to respond to payload post, empty state data.', 'wp-migrate-db'));
        }

        $decoded_json_state = json_decode(base64_decode($_POST['state_data']), true);

        //Sending ALL local state data, probably too much data and should be paired down
        $state_data = Persistence::setRemotePostData($key_rules, __METHOD__, 'wpmdb_remote_migration_state', $decoded_json_state);

        $filtered_post = $this->http_helper->filter_post_elements(
            $state_data,
            array(
                'action',
                'remote_state_id',
                'stage',
                'intent',
            )
        );

        $settings = $this->settings;

        if (!isset($_FILES['content']) || !$this->http_helper->verify_signature($filtered_post, $settings['key'])) {
            throw new \Exception(__('Failed to respond to payload post.', 'wp-migrate-db'));
        }

        $payload_content = fopen($_FILES['content']['tmp_name'], 'r');
        $receiver        = $this->receiver;

        try {
            $receiver->receive_post_data($state_data, $payload_content);
        } catch (\Exception $e) {
            return $this->transfer_util->catch_general_error($e->getMessage());
        }
    }

    /**
     *
     * Fired off a nopriv AJAX hook that listens to pull requests for file batches
     *
     * @return mixed
     */
    public function respond_to_request_files()
    {
        $key_rules = array(
            'action'          => 'key',
            'remote_state_id' => 'key',
            'stage'           => 'string',
            'intent'          => 'string',
            'bottleneck'      => 'numeric',
            'sig'             => 'string',
        );

        $state_data    = $this->migration_state_manager->set_post_data($key_rules, 'remote_state_id');
        $filtered_post = $this->http_helper->filter_post_elements(
            $state_data,
            array(
                'action',
                'remote_state_id',
                'stage',
                'intent',
                'bottleneck',
            )
        );

        $settings = $this->settings;

        if (!$this->http_helper->verify_signature($filtered_post, $settings['key'])) {
            return $this->transfer_util->ajax_error($this->properties->invalid_content_verification_error . ' (#100tp)', $filtered_post);
        }

        try {
            $this->sender->respond_to_send_file($state_data);
        } catch (\Exception $e) {
            $this->transfer_util->catch_general_error($e->getMessage());
        }
    }
}

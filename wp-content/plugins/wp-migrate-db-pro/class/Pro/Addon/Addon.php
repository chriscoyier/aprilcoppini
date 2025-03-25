<?php

namespace DeliciousBrains\WPMDB\Pro\Addon;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DeliciousBrains\WPMDB\Pro\Api;
use DeliciousBrains\WPMDB\Pro\Download;
use DeliciousBrains\WPMDB\WPMDBDI;

class Addon extends \DeliciousBrains\WPMDB\Common\Addon\Addon {

    /**
     * @var Download
     */
    private $download;

    public function __construct(
        ErrorLog $log,
        Settings $settings,
        Properties $properties,
        Download $download
    ) {
        parent::__construct($log, $settings, $properties);
        $this->download = $download;
    }
    public function register()
    {
        $api = WPMDBDI::getInstance()->get(Api::class);

        $api->dbrains_api_url = $api->get_dbrains_api_base() . '/?wc-api=delicious-brains';

        parent::register();
        // Adds a custom error message to the plugin install page if required (licence expired / invalid)
        add_filter('http_response', array($this->download, 'verify_download'), 10, 3);
        add_filter('wpmdb_notification_strings', array($this, 'version_update_notice'));
    }

    public function version_update_notice($notifications)
    {

        $str = '';
        // We don't want to show both the "Update Required" and "Update Available" messages at the same time
        if (isset($this->addons[$this->props->plugin_basename]) && true === $this->is_addon_outdated($this->props->plugin_basename)) {
            return;
        }

        // To reduce UI clutter we hide addon update notices if the core plugin has updates available
        if (isset($this->addons[$this->props->plugin_basename])) {
            $core_installed_version = $GLOBALS['wpmdb_meta'][$this->props->core_slug]['version'];
            $core_latest_version    = $this->get_latest_version($this->props->core_slug);
            // Core update is available, don't show update notices for addons until core is updated
            if (version_compare($core_installed_version, $core_latest_version, '<')) {
                return;
            }
        }

        $update_url = wp_nonce_url(network_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->props->plugin_basename)), 'upgrade-plugin_' . $this->props->plugin_basename);

        // If pre-1.1.2 version of Media Files addon, don't bother getting the versions
        if (!isset($GLOBALS['wpmdb_meta'][$this->props->plugin_slug]['version'])) {
            ?>
            <div style="display: block;" class="updated warning inline-message">
                <strong><?php _ex('Update Available', 'A new version of the plugin is available', 'wp-migrate-db'); ?></strong> &mdash;
                <?php printf(__('A new version of %1$s is now available. %2$s', 'wp-migrate-db'), $this->props->plugin_title, sprintf('<a href="%s">%s</a>', $update_url, _x('Update Now', 'Download and install a new version of the plugin', 'wp-migrate-db'))); ?>
            </div>
            <?php
        } else {
            $installed_version = $GLOBALS['wpmdb_meta'][$this->props->plugin_slug]['version'];
            $latest_version    = $this->get_latest_version($this->props->plugin_slug);

            if (version_compare($installed_version, $latest_version, '<')) {
                $str = \DeliciousBrains\WPMDB\Pro\Beta\BetaManager::is_beta_version($latest_version)
                    ? '<strong>' . __('Beta Update Available', 'A new version of the plugin is available', 'wp-migrate-db') . '</strong> &mdash;'
                    : '<strong>' . __('Update Available', 'A new version of the plugin is available', 'wp-migrate-db') . '</strong> &mdash;';

                $str .= sprintf(__('%1$s %2$s is now available. You currently have %3$s installed. <a href="%4$s">%5$s</a>', 'wp-migrate-db'), $this->props->plugin_title, $latest_version, $installed_version, $update_url, __('Update Now', 'Download and install a new version of the plugin', 'wp-migrate-db'));
            }
        }

        if (empty($str)) {
            return $notifications;
        }

        $notifications['mdb_update-notice'] = [
            'message' => $str,
            'link'    => false,
            'id'      => 'mdb_update-notice',
        ];

        return $notifications;
    }

}

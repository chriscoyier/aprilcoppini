<?php

namespace DeliciousBrains\WPMDB\Pro\TPF\Cli;

use DeliciousBrains\WPMDB\Common\TPF\ThemePluginFilesAddon;
use DeliciousBrains\WPMDB\Common\TPF\ThemePluginFilesLocal;
use DeliciousBrains\WPMDB\Common\TPF\ThemePluginFilesFinalize;
use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Profile\ProfileManager;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Common\Addon\Addon;
use DeliciousBrains\WPMDB\Common\Transfers\Files\PluginHelper;
use DeliciousBrains\WPMDB\Pro\Transfers\Receiver;
use DeliciousBrains\WPMDB\Pro\UI\Template;
use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDB\Common\Cli\Cli;

class ThemePluginFilesCli extends ThemePluginFilesAddon
{
    /**
     * @var Cli
     */
    private $cli;

    public function __construct(
        Addon $addon,
        Properties $properties,
        Template $template,
        Filesystem $filesystem,
        ProfileManager $profile_manager,
        Util $util,
        \DeliciousBrains\WPMDB\Common\Transfers\Files\Util $transfers_util,
        Receiver $transfers_receiver,
        ThemePluginFilesFinalize $tp_addon_finalize,
        PluginHelper $transfers_plugin_helper,
        Cli $cli
    ) {
        parent::__construct(
            $addon,
            $properties,
            $filesystem,
            $profile_manager,
            $util,
            $transfers_util,
            $tp_addon_finalize,
            $transfers_plugin_helper
        );

        $this->cli = $cli;
    }

    public function register()
    {
        // Accepted profile fields exclusive to Theme & Plugin Files.
        add_filter('wpmdb_accepted_profile_fields', [$this, 'accepted_profile_fields']);

        // Announce extra args for Theme & Plugin Files.
        add_filter('wpmdb_cli_filter_get_extra_args', [$this, 'filter_extra_args'], 10, 1);

        // Add extra args for Theme & Plugin Files migrations.
        add_filter('wpmdb_cli_filter_get_profile_data_from_args', [$this, 'add_tpf_profile_args'], 11, 3);

        // Add the Theme & Plugin Files stage.
        add_filter('wpmdb_cli_profile_before_migration', [$this, 'add_tpf_stages'], PHP_INT_MAX);

        // Check for remote themes and plugins after the connection has been made.
        add_filter('wpmdb_cli_filter_tpf_profile_args', [$this, 'extend_tpf_profile_args'], 10, 2);

        // Initialize the CLI Migration.
        add_filter('wpmdb_pro_cli_finalize_migration', [$this, 'cli_migration'], 10, 4);

        $this->tpf_local = WPMDBDI::getInstance()->get(ThemePluginFilesLocal::class);
    }

    /**
     * Gets the TPF stages, if any.
     *
     * @param array $profile
     *
     * @return array
     */
    public function get_tpf_stages($profile)
    {
        $stages = [];

        if (!isset($profile['theme_plugin_files'], $profile['theme_plugin_files']['available'])) {
            return $stages;
        }

        $tpf = $profile['theme_plugin_files'];

        if (true !== $tpf['available']) {
            return $stages;
        }

        if (isset($tpf['theme_files'], $tpf['theme_files']['enabled']) && true === $tpf['theme_files']['enabled']) {
            $stages[] = 'themes';
        }

        if (isset($tpf['plugin_files'], $tpf['plugin_files']['enabled']) && true === $tpf['plugin_files']['enabled']) {
            $stages[] = 'plugins';
        }

        if (isset($tpf['muplugin_files'], $tpf['muplugin_files']['enabled']) && true === $tpf['muplugin_files']['enabled']) {
            $stages[] = 'muplugins';
        }

        if (isset($tpf['other_files'], $tpf['other_files']['enabled']) && true === $tpf['other_files']['enabled']) {
            $stages[] = 'others';
        }

        return $stages;
    }

    /**
     * Adds extra profile fields used by the Theme & Plugin Files addon.
     * Hooks on: wpmdb_accepted_profile_fields
     *
     * @param array $fields
     *
     * @return array
     */
    public function accepted_profile_fields($fields)
    {
        $fields[] = 'theme_plugin_files';

        return $fields;
    }

    /**
     * Add extra CLI args used by the Theme & Plugin Files addon.
     * Hooks on: wpmdb_cli_filter_get_extra_args
     *
     * @param array $args
     *
     * @return array
     */
    public function filter_extra_args($args)
    {
        $args[] = 'theme-files';
        $args[] = 'plugin-files';
        $args[] = 'mu-plugin-files';
        $args[] = 'other-files';
        $args[] = 'exclude-theme-plugin-files';

        return $args;
    }

    /**
     * Adds extra args for Theme & Plugin Files migrations.
     * Hooks on: wpmdb_cli_filter_get_profile_data_from_args
     *
     * @param array $profile
     * @param array $args
     * @param array $assoc_args
     *
     * @return array|WP_Error
     */
    public function add_tpf_profile_args($profile, $args, $assoc_args)
    {
        if (!isset($assoc_args['theme-files'])
            && !isset($assoc_args['plugin-files'])
            && !isset($assoc_args['mu-plugin-files'])
            && !isset($assoc_args['other-files'])
        ) {
            return $profile;
        }

        $theme_plugin_files = [
            'available'          => true,
            'theme_files'        => ['enabled' => false],
            'themes_selected'    => [],
            'themes_excludes'    => [],
            'plugin_files'       => ['enabled' => false],
            'plugins_selected'   => [],
            'plugins_excludes'   => [],
            'muplugin_files'     => ['enabled' => false],
            'muplugins_selected' => [],
            'muplugins_excludes' => [],
            'other_files'        => ['enabled' => false],
            'others_selected'    => [],
            'others_excludes'    => [],
        ];

        if (isset($assoc_args['theme-files']) && $assoc_args['theme-files']) {
            $theme_plugin_files['theme_files']     = ['enabled' => true];
            $theme_plugin_files['themes_selected'] = $assoc_args['theme-files'];
        }

        if (isset($assoc_args['plugin-files']) && $assoc_args['plugin-files']) {
            $theme_plugin_files['plugin_files']     = ['enabled' => true];
            $theme_plugin_files['plugins_selected'] = $assoc_args['plugin-files'];
        }

        if (isset($assoc_args['mu-plugin-files']) && $assoc_args['mu-plugin-files']) {
            $theme_plugin_files['muplugin_files']     = ['enabled' => true];
            $theme_plugin_files['muplugins_selected'] = $assoc_args['mu-plugin-files'];
        }

        if (isset($assoc_args['other-files']) && $assoc_args['other-files']) {
            $theme_plugin_files['other_files']     = ['enabled' => true];
            $theme_plugin_files['others_selected'] = $assoc_args['other-files'];
        }

        if (isset($assoc_args['exclude-theme-plugin-files'])) {
            $theme_plugin_files['themes_excludes']    = str_replace(',', "\n", $assoc_args['exclude-theme-plugin-files']);
            $theme_plugin_files['plugins_excludes']   = str_replace(',', "\n", $assoc_args['exclude-theme-plugin-files']);
            $theme_plugin_files['muplugins_excludes'] = str_replace(',', "\n", $assoc_args['exclude-theme-plugin-files']);
            $theme_plugin_files['others_excludes']    = str_replace(',', "\n", $assoc_args['exclude-theme-plugin-files']);
        }

        $profile['theme_plugin_files'] = $theme_plugin_files;

        return $profile;
    }

    /**
     * Extends the T&P profile args after a connection has been made.
     * Hooks on: wpmdb_cli_filter_tpf_profile_args
     *
     * @param array $profile
     * @param array $remote
     *
     * @return array $profile
     */
    public function extend_tpf_profile_args($profile, $remote)
    {
        $stages = $this->get_tpf_stages($profile);
        $intent = $profile['action'];

        if ('pull' === $intent) {
            $themes    = isset($remote['site_details'], $remote['site_details']['themes']) ? $remote['site_details']['themes'] : [];
            $plugins   = isset($remote['site_details'], $remote['site_details']['plugins']) ? $remote['site_details']['plugins'] : [];
            $muplugins = isset($remote['site_details'], $remote['site_details']['muplugins']) ? $remote['site_details']['muplugins'] : [];
            $others    = isset($remote['site_details'], $remote['site_details']['others']) ? $remote['site_details']['others'] : [];
        } else {
            $themes    = $this->get_local_themes();
            $plugins   = $this->filesystem->get_local_plugins(true);
            $muplugins = $this->get_local_muplugin_files();
            $others    = $this->get_local_other_files();
        }

        if (in_array('themes', $stages)) {
            $themes = $this->get_themes_to_migrate($profile, $themes);

            if (is_wp_error($themes)) {
                return $themes;
            }

            $profile['theme_plugin_files']['themes_selected'] = $themes;
        }

        if (in_array('plugins', $stages)) {
            $plugins = $this->get_plugins_to_migrate($profile, $plugins);

            if (is_wp_error($plugins)) {
                return $plugins;
            }

            $profile['theme_plugin_files']['plugins_selected'] = $plugins;
        }

        if (in_array('muplugins', $stages)) {
            $muplugins = $this->get_muplugins_to_migrate($profile, $muplugins);

            if (is_wp_error($muplugins)) {
                return $muplugins;
            }

            $profile['theme_plugin_files']['muplugins_selected'] = $muplugins;
        }

        if (in_array('others', $stages)) {
            $others = $this->get_others_to_migrate($profile, $others);

            if (is_wp_error($others)) {
                return $others;
            }

            $profile['theme_plugin_files']['others_selected'] = $others;
        }

        return $profile;
    }

    /**
     * Gets the themes to migrate.
     *
     * @param array $profile The current migration profile.
     * @param array $themes  The themes that can be migrated.
     *
     * @return array|WP_Error
     */
    public function get_themes_to_migrate($profile, $themes)
    {
        $themes_option     = isset($profile['theme_plugin_files']['themes_option'])
            ? $profile['theme_plugin_files']['themes_option']
            : null;
        $themes_selected   = isset($profile['theme_plugin_files']['themes_selected'])
            ? $profile['theme_plugin_files']['themes_selected']
            : null;
        $themes_excluded   = isset($profile['theme_plugin_files']['themes_excluded'])
            ? $profile['theme_plugin_files']['themes_excluded']
            : null;
        $themes_to_migrate = [];

        if ('all' === $themes_selected || 'all' === $themes_option) {
            $theme_paths = array_map(function ($theme) {
                return $theme[0]['path'];
            }, $themes);

            return array_values($theme_paths);
        }

        if ('active' === $themes_option) {
            $active_themes = [];
            foreach($themes as $theme) {
                if ($theme[0]['active']) {
                    $active_themes[] = $theme[0]['path'];
                }
            }
            return array_values($active_themes);
        }

        if ('except' === $themes_option) {
            $filtered_excluded = [];
            foreach($themes as $theme) {
                if(in_array($theme[0]['path'], $themes_excluded)){
                    continue;
                }
                $filtered_excluded[] = $theme[0]['path'];
            }
            return array_values($filtered_excluded);
        }

        if (!is_array($themes_selected)) {
            $themes_selected = explode(',', $themes_selected);
        } else {
            $themes_selected = array_map('wp_basename', $themes_selected);
        }

        foreach ($themes_selected as $theme) {
            $theme = trim($theme);

            if (!$theme) {
                continue;
            }

            if (!isset($themes[$theme])) {
                $message = sprintf(__('Theme not found on source server: %s', 'wp-migrate-db'), $theme);
                return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
            }

            $themes_to_migrate[] = $themes[$theme][0]['path'];
        }

        return $themes_to_migrate;
    }

    /**
     * Gets the plugins to migrate.
     *
     * @param array $profile The current migration profile.
     * @param array $plugins The plugins that can be migrated.
     *
     * @return array|WP_Error
     */
    public function get_plugins_to_migrate($profile, $plugins)
    {
        $plugins_option     = isset($profile['theme_plugin_files']['plugins_option'])
            ? $profile['theme_plugin_files']['plugins_option']
            : null;
        $plugins_selected   = isset($profile['theme_plugin_files']['plugins_selected'])
            ? $profile['theme_plugin_files']['plugins_selected']
            : null;
        $plugins_excluded   = isset($profile['theme_plugin_files']['plugins_excluded'])
            ? $profile['theme_plugin_files']['plugins_excluded']
            : null;
        $plugins_to_migrate = [];

        $plugins = $this->exclude_migrate_plugins($plugins);
        if ('all' === $plugins_selected || 'all' === $plugins_option) {
            $plugin_paths = array_map(function ($plugin) {
                return $plugin[0]['path'];
            }, $plugins);

            return array_values($plugin_paths);
        }

        if ('active' === $plugins_option) {
            $active_plugins = [];
            foreach($plugins as $plugin) {
                if ($plugin[0]['active']) {
                    $active_plugins[] = $plugin[0]['path'];
                }
            }
            return array_values($active_plugins);
        }

        if ('except' === $plugins_option) {
            $filtered_excluded = [];
            foreach($plugins as $plugin) {
                if(in_array($plugin[0]['path'], $plugins_excluded)){
                    continue;
                }
                $filtered_excluded[] = $plugin[0]['path'];
            }
            return array_values($filtered_excluded);
        }


        if (!is_array($plugins_selected)) {
            $plugins_selected = explode(',', $plugins_selected);
        } else {
            $plugins_selected = array_map(function ($plugin) {
                return wp_basename($plugin, '.php');
            }, $plugins_selected);
        }

        $plugin_slugs     = [];

        // Get things into a format we can work with.
        foreach ($plugins as $key => $value) {
            $slug                = preg_replace('/\/(.*)\.php|\.php/i', '', $key);
            $plugin_slugs[$slug] = $value;
        }

        foreach ($plugins_selected as $plugin) {
            $plugin = trim($plugin);

            if (!$plugin) {
                continue;
            }

            if (!isset($plugin_slugs[$plugin])) {
                $message = sprintf(__('Plugin not found on source server: %s', 'wp-migrate-db'), $plugin);

                return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
            }

            $plugins_to_migrate[] = $plugin_slugs[$plugin][0]['path'];
        }

        return $plugins_to_migrate;
    }

    /**
     * Exclude the Migrate Plugins from the plugins list
     *
     * @param array $plugins
     * @return array
     **/
    protected function exclude_migrate_plugins($plugins)
    {
        $filtered_plugins = [];
        foreach ($plugins as $key => $plugin) {
            if (0 === strpos($key, 'wp-migrate-db')) {
                continue;
            }
            $filtered_plugins[$key] = $plugin;
        }
        return $filtered_plugins;
    }

    /**
     * Gets the must-use plugins to migrate.
     *
     * @param array $profile The current migration profile.
     * @param array $plugins The plugins that can be migrated.
     *
     * @return array|WP_Error
     */
    public function get_muplugins_to_migrate($profile, $muplugins)
    {
        $muplugins_option     = isset($profile['theme_plugin_files']['muplugins_option'])
            ? $profile['theme_plugin_files']['muplugins_option']
            : '';
        $muplugins_selected   = isset($profile['theme_plugin_files']['muplugins_selected'])
            ? $profile['theme_plugin_files']['muplugins_selected']
            : [];
        $muplugins_to_migrate = [];

        if ('all' === $muplugins_selected || 'all' === $muplugins_option) {
            $muplugin_paths = array_map(function ($muplugin) {
                return $muplugin[0]['path'];
            }, $muplugins);

            return array_values($muplugin_paths);
        }

        if (!is_array($muplugins_selected)) {
            $muplugins_selected = explode(',', $muplugins_selected);
        }

        $muplugin_slugs = [];

        // Get things into a format we can work with.
        foreach ($muplugins as $key => $value) {
            $muplugin_slugs[$value[0]['path']] = $value;
        }

        foreach ($muplugins_selected as $muplugin) {
            if (strpos($muplugin, WPMU_PLUGIN_DIR) === false) {
                $muplugin = $this->get_path_from_name($muplugins, $muplugin);
            }
            $muplugin = trim($muplugin);

            if (!$muplugin) {
                continue;
            }

            if (!isset($muplugin_slugs[$muplugin])) {
                $message = sprintf(__('Must-Use Plugin not found on source server: %s', 'wp-migrate-db'), $muplugin);

                return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
            }

            $muplugins_to_migrate[] = $muplugin_slugs[$muplugin][0]['path'];
        }

        return $muplugins_to_migrate;
    }


    /**
     * Gets the other filess to migrate.
     *
     * @param array $profile The current migration profile.
     * @param array $others The other files that can be migrated.
     *
     * @return array|WP_Error
     */
    public function get_others_to_migrate($profile, $others)
    {
        $others_option     = isset($profile['theme_plugin_files']['others_option'])
            ? $profile['theme_plugin_files']['others_option']
            : '';
        $others_selected   = isset($profile['theme_plugin_files']['others_selected'])
            ? $profile['theme_plugin_files']['others_selected']
            : [];
        $others_to_migrate = [];

        if ('all' === $others_selected || 'all' === $others_option) {
            $other_paths = array_map(function ($other) {
                return $other[0]['path'];
            }, $others);

            return array_values($other_paths);
        }

        if (!is_array($others_selected)) {
            $others_selected = explode(',', $others_selected);
        }

        $other_slugs = [];

        // Get things into a format we can work with.
        foreach ($others as $key => $value) {
            $other_slugs[$value[0]['path']] = $value;
        }

        foreach ($others_selected as $other) {
            if (strpos($other, WP_CONTENT_DIR) === false) {
                $other = $this->get_path_from_name($others, $other);
            }
            $other = trim($other);

            if (!$other) {
                continue;
            }

            if (!isset($other_slugs[$other])) {
                $message = sprintf(__('Other file not found on source server: %s', 'wp-migrate-db'), $other);

                return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
            }

            $others_to_migrate[] = $other_slugs[$other][0]['path'];
        }

        return $others_to_migrate;
    }

     /**
     * Get file path to be used for array key
     *
     * @param array $files_array
     *
     * @param string $name
     *
     * @return string
     */
    public function get_path_from_name($files_array, $name)
    {
        foreach ($files_array as $key => $val) {
            if ($val[0]['name'] === $name || $val[0]['path'] === $name) {
                return $val[0]['path'];
            }
        }
        return '';
    }

    /**
     * Adds the Theme & Plugin Files stages to the current migration.
     * Hooks on: wpmdb_cli_profile_before_migration
     *
     * @param array $profile
     *
     * @return array
     */
    public function add_tpf_stages($profile)
    {
        if (is_wp_error($profile)) {
            return $profile;
        }

        $stages = $this->get_tpf_stages($profile);

        if (in_array('themes', $stages)) {
            $profile['current_migration']['stages'][] = 'theme_files';
        }

        if (in_array('plugins', $stages)) {
            $profile['current_migration']['stages'][] = 'plugin_files';
        }

        if (in_array('muplugins', $stages)) {
            $profile['current_migration']['stages'][] = 'muplugin_files';
        }

        if (in_array('others', $stages)) {
            $profile['current_migration']['stages'][] = 'other_files';
        }

        return $profile;
    }

    /**
     * Initialize the TPF stage.
     *
     * @param array  $profile
     * @param array  $post_data
     * @param string $stage
     * @return array|WP_Error
     */
    public function initialize_tpf_migration($profile, $post_data, $stage)
    {
        $_POST = [
            'action'             => $profile['action'],
            'migration_state_id' => $profile['current_migration']['migration_id'],
            'stage'              => $stage,
            'is_cli_migration'   => 1
        ];

        if ('themes' === $stage) {
            $initiate_msg           = __('Initiating themes migration...', 'wp-migrate-db');
            $_POST['folders']       = json_encode($profile['theme_plugin_files']['themes_selected']);
            $_POST['theme_folders'] = json_encode($profile['theme_plugin_files']['themes_selected']);
            $_POST['themes_option'] = isset($profile['theme_plugin_files']['themes_option'])
                ? $profile['theme_plugin_files']['themes_option']
                : '';
            if (isset($profile['theme_plugin_files']['themes_excludes'])) {
                $_POST['themes_excludes'] = json_encode($profile['theme_plugin_files']['themes_excludes']);
            }
        } elseif('others' === $stage){
            $initiate_msg            = __('Initiating other files migration...', 'wp-migrate-db');
            $_POST['folders']        = json_encode($profile['theme_plugin_files']['others_selected']);
            $_POST['other_folders']  = json_encode($profile['theme_plugin_files']['others_selected']);
            $_POST['others_option']  = isset($profile['theme_plugin_files']['others_option'])
                ? $profile['theme_plugin_files']['others_option']
                : '';
            if (isset($profile['theme_plugin_files']['others_excludes'])) {
                $_POST['others_excludes'] = json_encode($profile['theme_plugin_files']['others_excludes']);
            }
        } elseif('muplugins' === $stage){
            $initiate_msg              = __('Initiating must-use files migration...', 'wp-migrate-db');
            $_POST['folders']          = json_encode($profile['theme_plugin_files']['muplugins_selected']);
            $_POST['muplugin_folders'] = json_encode($profile['theme_plugin_files']['muplugins_selected']);
            $_POST['muplugins_option'] = isset($profile['theme_plugin_files']['muplugins_option'])
                ? $profile['theme_plugin_files']['muplugins_option']
                : '';
            if (isset($profile['theme_plugin_files']['muplugins_excludes'])) {
                $_POST['muplugins_excludes'] = json_encode($profile['theme_plugin_files']['muplugins_excludes']);
            }
        } else {
            $initiate_msg            = __('Initiating plugins migration...', 'wp-migrate-db');
            $_POST['folders']        = json_encode($profile['theme_plugin_files']['plugins_selected']);
            $_POST['plugin_folders'] = json_encode($profile['theme_plugin_files']['plugins_selected']);
            $_POST['plugins_option'] = isset($profile['theme_plugin_files']['plugins_option'])
                ? $profile['theme_plugin_files']['plugins_option']
                : '';
            if (isset($profile['theme_plugin_files']['plugins_excludes'])) {
                $_POST['plugins_excludes'] = json_encode($profile['theme_plugin_files']['plugins_excludes']);
            }
        }

        \WP_CLI::log($initiate_msg);

        $response = $this->tpf_local->ajax_initiate_file_migration();

        return $this->cli->verify_cli_response($response, 'initialize_tpf_migration()');
    }

    /**
     * Transfers files during the TPF stage.
     *
     * @param array  $profile
     * @param array  $post_data
     * @param string $stage
     *
     * @return array|WP_Error
     */
    public function tpf_transfer_files($profile, $post_data, $stage)
    {
        $_POST = [
            'action'             => $profile['action'],
            'migration_state_id' => $profile['current_migration']['migration_id'],
            'stage'              => $stage,
        ];

        $response = $this->tpf_local->ajax_transfer_files();

        return $this->cli->verify_cli_response($response, 'tpf_transfer_files()');
    }

    /**
     * Run the TPF migration from the CLI.
     * Hooks on: wpmdb_pro_cli_finalize_migration
     *
     * @param bool  $outcome
     * @param array $profile
     * @param array $verify_connection_response
     * @param array $post_data
     *
     * @return bool|WP_Error
     */
    public function cli_migration($outcome, $profile, $verify_connection_response, $post_data)
    {
        $stages = $this->get_tpf_stages($profile);
        if (true !== $outcome || empty($stages)) {
            return $outcome;
        }

        foreach ($stages as $stage) {
            $init = $this->initialize_tpf_migration($profile, $post_data, $stage);
            if (is_wp_error($init)) {
                return $init;
            }

            $queue_status = $init['queue_status'];
            $total_size   = isset($queue_status['size']) ? (int) $queue_status['size'] : 0;
            $intent       = $profile['action'];
            $migrate_bar  = $this->make_progress_bar($this->get_string('cli_migrating_' . $intent), 0);
            $migrate_bar->setTotal($total_size);

            $result = ['status' => 0];

            while (!is_wp_error($result) && $result['status'] !== 'complete') {
                // Delay between requests
                do_action('wpmdb_theme_plugin_files_cli_before_migrate_files');

                // Migrate the files.
                $result = $this->tpf_transfer_files($profile, $post_data, $stage);

                if (isset($result['status']['error'])) {
                    return new \WP_Error('wpmdb_cli_tpf_migration_failed', $result['status']['message']);
                }

                $batch_size = is_array($result['status']) ? array_sum(array_column($result['status'], 'batch_size')) : 0;

                // Update progress.
                $migrate_bar->tick($batch_size);
            }

            if (is_wp_error($result)) {
                return $result;
            }

            // Finish things up.
            $migrate_bar->finish();
        }

        return true;
    }

    /**
     * Like WP_CLI\Utils\make_progress_bar, but uses our own wrapper classes
     *
     * @param $message
     * @param $count
     *
     * @return ThemePluginFilesCliBar|ThemePluginFilesCliBarNoOp
     */
    public function make_progress_bar($message, $count)
    {
        if (method_exists('cli\Shell', 'isPiped') && \cli\Shell::isPiped()) {
            return new ThemePluginFilesCliBarNoOp();
        }

        return new  ThemePluginFilesCliBar($message, $count);
    }
}

<?php

namespace DeliciousBrains\WPMDB\Pro\Addon;

use DeliciousBrains\WPMDB\Pro\License;
use DeliciousBrains\WPMDB\Common\Util\Util;

class AddonsFacade extends \DeliciousBrains\WPMDB\Common\Addon\AddonsFacade
{

    /**
     * @var License
     */
    private $license;

    /**
     * @param License $license
     * @param array $addons
     */
    public function __construct(License $license, array $addons = [])
    {
        $this->license = $license;
        parent::__construct($addons);
    }

    public function register()
    {
        if (false === self::$initialized) {
            add_action('plugins_loaded', [$this, 'upgrade_routine'], PHP_INT_MAX);
        }

        parent::register();
    }

    /**
     * Initializes registered addons
     *
     * @return void
     */
    public function initialize_addons()
    {
        $licensed_addons_list = $this->license->get_available_addons_list(get_current_user_id());
        if (false === $licensed_addons_list) {
            $this->license->check_license_status();
            $licensed_addons_list = $this->license->get_available_addons_list(get_current_user_id());
        }
        $licensed_array = $licensed_addons_list ? array_keys($licensed_addons_list) : [];
        $addons_list = array_unique(array_merge(self::GLOBAL_ADDONS, $licensed_array));
        if (is_array($addons_list)) {
            foreach ($this->addons as $addon) {
                if (in_array($addon->get_license_response_key(), $addons_list)) {
                    $licensed = in_array($addon->get_license_response_key(), $licensed_array);
                    $addon->register($licensed);
                }
            }
        }
    }


    /**
     * Deactivates legacy addons on upgrade
     *
     * @return void
     */
    public static function disable_legacy_addons()
    {
        Util::disable_legacy_addons();
    }

    /**
     * Prevents legacy addons from being activated
     *
     * @return void
     */
    public function prevent_legacy_addon_activation($plugin)
    {
        if (in_array($plugin, self::LEGACY_ADDONS)) {
            $redirect = self_admin_url('plugins.php?legacyaddon=1');
            wp_redirect($redirect);
            exit;
        }
    }

    /**
     * Notice when trying to activate addon
     *
     * @return void
     */
    public function legacy_addon_notice()
    {
        if (isset($_GET['legacyaddon'])) {
            $message = __('Legacy addons cannot be activated alongside WP Migrate version 2.3.0 or above. These features have been moved to WP Migrate.', 'wp-migrate-db');
            echo '<div class="updated" style="border-left: 4px solid #ffba00;"><p>' . $message . '</p></div>';
        }
    }

    /**
     * Executes upgrade routines for the addons
     *
     * @return void
     */
    public function upgrade_routine()
    {
        $addons_schema_version = get_option($this->addons_schema_option, 0);
        if ((int)$addons_schema_version !== $this->current_schema_version) {
            $this->license->check_licence($this->license->get_licence_key());
            update_option($this->addons_schema_option, $this->current_schema_version);
        }
    }
}

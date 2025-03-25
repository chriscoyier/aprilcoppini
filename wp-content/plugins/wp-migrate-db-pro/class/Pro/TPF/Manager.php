<?php
namespace DeliciousBrains\WPMDB\Pro\TPF;

use DeliciousBrains\WPMDB\Common\TPF\ThemePluginFilesAddon;
use DeliciousBrains\WPMDB\Common\TPF\ThemePluginFilesLocal;
use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDB\Pro\TPF\Cli\ThemePluginFilesCli;

class Manager extends \DeliciousBrains\WPMDB\Common\TPF\Manager {

    public function register($licensed)
    {
        global $wpmdbpro_theme_plugin_files;

        if (!is_null($wpmdbpro_theme_plugin_files) ) {
            return $wpmdbpro_theme_plugin_files;
        }


        $container = WPMDBDI::getInstance();
        $theme_plugin = $container->get(ThemePluginFilesAddon::class);
        $theme_plugin->register();
        $theme_plugin->set_licensed($licensed);

        $container->get(ThemePluginFilesLocal::class)->register();
        $container->get(ThemePluginFilesRemote::class)->register();
        $container->get(ThemePluginFilesCli::class)->register();

        add_filter('wpmdb_addon_registered_tpf', '__return_true');

        return $theme_plugin;
    }
}

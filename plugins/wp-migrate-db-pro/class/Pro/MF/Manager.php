<?php

namespace DeliciousBrains\WPMDB\Pro\MF;

use DeliciousBrains\WPMDB\Common\MF\MediaFilesAddon;
use DeliciousBrains\WPMDB\Common\MF\MediaFilesLocal;
use DeliciousBrains\WPMDB\Pro\MF\CliCommand\MediaFilesCli;
use DeliciousBrains\WPMDB\WPMDBDI;

class Manager extends \DeliciousBrains\WPMDB\Common\MF\Manager
{
    public function register($licensed)
    {
        global $wpmdbpro_media_files;

        if ( ! is_null($wpmdbpro_media_files) ) {
            return $wpmdbpro_media_files;
        }

        $container = WPMDBDI::getInstance();
        $media_files = $container->get(MediaFilesAddon::class);
        $media_files->register();
        $media_files->set_licensed($licensed);

        $container->get(MediaFilesLocal::class)->register();
        $container->get(MediaFilesRemote::class)->register();
        $container->get(MediaFilesCli::class)->register();

        add_filter('wpmdb_addon_registered_mf', '__return_true');

        return $media_files;
    }
}

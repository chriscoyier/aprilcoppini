<?php

namespace DeliciousBrains\WPMDB\Pro\Compatibility\Layers\Platforms;

class Flywheel extends AbstractPlatform
{
    public function filter_platform($platform)
    {
        if (defined('FLYWHEEL_CONFIG_DIR')) {
            return 'flywheel';
        }

        return $platform;
    }
}

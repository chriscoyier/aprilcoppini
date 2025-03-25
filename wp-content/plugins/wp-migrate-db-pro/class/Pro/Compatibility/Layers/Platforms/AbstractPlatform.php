<?php

namespace DeliciousBrains\WPMDB\Pro\Compatibility\Layers\Platforms;

abstract class AbstractPlatform implements PlatformInterface
{
    public function register()
    {
        add_filter('wpmdb_hosting_platform', [$this, 'filter_platform']);
    }

    public function filter_platform($platform)
    {
        return $platform;
    }
}

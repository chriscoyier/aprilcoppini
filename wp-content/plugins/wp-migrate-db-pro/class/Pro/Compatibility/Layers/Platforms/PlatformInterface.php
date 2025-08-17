<?php

namespace DeliciousBrains\WPMDB\Pro\Compatibility\Layers\Platforms;

interface PlatformInterface
{
    public function register();

    public function filter_platform($platform);
}

<?php

namespace DeliciousBrains\WPMDB\Pro\Compatibility\Layers\Platforms;

use DeliciousBrains\WPMDB\Common\Util\Util;

class WPEngine extends AbstractPlatform
{
    public function filter_platform($platform)
    {
        $wpe_cookie = Util::get_wpe_cookie();

        if ( ! empty($wpe_cookie)) {
            return 'wp_engine';
        }

        return $platform;
    }
}

<?php

namespace DeliciousBrains\WPMDB\Pro\Compatibility\Layers\Platforms;

use DeliciousBrains\WPMDB\WPMDBDI;

class Platforms
{
    /**
     * @var string[] List of classes implementing PlatformInterface
     */
    private $supported_platform = [WPEngine::class, Flywheel::class];

    /**
     * Platforms constructor.
     * Registers wpmdb_hosting_platform filter for each supported platform
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'register']);
    }

    /**
     * Register each supported platform
     *
     * @return void
     */
    public function register()
    {
        foreach ($this->supported_platform as $platform) {
            $platform = new $platform;
            $platform->register();
        }
    }
}

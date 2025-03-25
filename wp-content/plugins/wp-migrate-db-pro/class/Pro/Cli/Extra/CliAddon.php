<?php

namespace DeliciousBrains\WPMDB\Pro\Cli\Extra;

use DeliciousBrains\WPMDB\Common\Addon\AddonAbstract;

class CliAddon extends AddonAbstract
{
    public function register()
	{
		$this->plugin_slug    = $this->properties->plugin_slug;
		$this->plugin_version = $this->properties->plugin_version;
		$this->addon_name     = 'WP Migrate CLI';
	}
}

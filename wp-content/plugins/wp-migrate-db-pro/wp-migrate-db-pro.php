<?php
/*
Plugin Name: WP Migrate
Plugin URI: https://deliciousbrains.com/wp-migrate-db-pro/
Description: Migrate between any two environments. Push, pull, and export full sites. Find and replace content including serialized data. Import and back up the database.
Author: WP Engine
Version: 2.6.1
Author URI: https://deliciousbrains.com
Network: True
Text Domain: wp-migrate-db
Domain Path: /languages/
Update URI: https://deliciousbrains.com/wp-migrate-db-pro/
*/

// Copyright (c) 2013 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

defined( 'ABSPATH' ) || exit;

$wpmdb_base_path = dirname(__FILE__);
require_once 'version.php';
$GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['folder']  = basename(plugin_dir_path(__FILE__));
$GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['abspath'] = $wpmdb_base_path;

define('WPMDB_PRO', true);

$plugin_root = '/';

if(!defined('WPMDB_VENDOR_DIR')){
    define('WPMDB_VENDOR_DIR', __DIR__ . $plugin_root."vendor");
}

require WPMDB_VENDOR_DIR . '/autoload.php';

require 'setup-plugin.php';

if (version_compare(PHP_VERSION, WPMDB_MINIMUM_PHP_VERSION, '>=')) {
    require_once $wpmdb_base_path . '/class/autoload.php';
    require_once $wpmdb_base_path . '/setup-mdb-pro.php';
}

function wpmdb_pro_remove_mu_plugin()
{
    do_action('wp_migrate_db_remove_compatibility_plugin');
}


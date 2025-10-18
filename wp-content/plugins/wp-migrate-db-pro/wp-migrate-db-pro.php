<?php
/*
Plugin Name: WP Migrate
Plugin URI: https://deliciousbrains.com/wp-migrate-db-pro/
Description: Migrate between any two environments. Push, pull, and export full sites. Find and replace content including serialized data. Import and back up the database.
Author: WP Engine
Version: 2.7.6
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

require_once 'version.php';

if ( ! defined( 'WPMDBPRO_FILE' ) ) {
	// Defines the path to the main plugin file.
	define( 'WPMDBPRO_FILE', __FILE__ );

	// Defines the path to be used for includes.
	define( 'WPMDBPRO_PATH', plugin_dir_path( WPMDBPRO_FILE ) );
}

$GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['folder']  = basename( WPMDBPRO_PATH );
$GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['abspath'] = untrailingslashit( WPMDBPRO_PATH );

define( 'WPMDB_PRO', true );

// TODO: Replace with checked-in prefixed libraries >>>
// NOTE: This path is updated during the build process.
$plugin_root = '/';

if ( ! defined( 'WPMDB_VENDOR_DIR' ) ) {
	define( 'WPMDB_VENDOR_DIR', __DIR__ . $plugin_root . "vendor" );
}

require WPMDB_VENDOR_DIR . '/autoload.php';
// TODO: Replace with checked-in prefixed libraries <<<

require 'setup-plugin.php';

if ( version_compare( PHP_VERSION, WPMDB_MINIMUM_PHP_VERSION, '>=' ) ) {
	require_once WPMDBPRO_PATH . 'class/autoload.php';
	require_once WPMDBPRO_PATH . 'setup-mdb-pro.php';
}

function wpmdb_pro_remove_mu_plugin() {
	do_action( 'wp_migrate_db_remove_compatibility_plugin' );
}

<?php
/*
Plugin Name: Tidy Images – Used & Unused Image Cleaner
Description: Find used and unused images in WordPress, see where images are used, and safely clean unnecessary media files.
Version: 1.0.0
Requires at least: 6.0
Requires PHP: 7.4
Author: Pushpasharmila S
Text Domain: tidy-images
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TIDYIMAGES_VERSION', '1.0.0' );
define( 'TIDYIMAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIDYIMAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TIDYIMAGES_PLUGIN_DIR . 'includes/class-ti-loader.php';

function tidyimages_run_plugin() {
    $plugin = new TIDYIMAGES_Loader();
    $plugin->run();
}

tidyimages_run_plugin();
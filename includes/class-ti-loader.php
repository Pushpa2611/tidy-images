<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TIDYIMAGES_Loader {

    public function run() {
        require_once TIDYIMAGES_PLUGIN_DIR . 'includes/class-ti-image-scanner.php';
        require_once TIDYIMAGES_PLUGIN_DIR . 'includes/class-ti-admin.php';

        new TIDYIMAGES_Admin();
    }
}
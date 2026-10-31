<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TIDYIMAGES_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'handle_single_delete' ] );
        add_action( 'admin_init', [ $this, 'handle_bulk_delete' ] );

        add_filter(
            'plugin_action_links_' . plugin_basename( TIDYIMAGES_PLUGIN_DIR . 'tidy-images.php' ),
            [ $this, 'add_plugin_action_links' ]
        );
    }

    public function add_menu() {
        add_management_page(
            __( 'Tidy Images', 'tidy-images' ),
            __( 'Tidy Images', 'tidy-images' ),
            'manage_options',
            'tidy-images',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'tools_page_tidy-images' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ti-admin',
            TIDYIMAGES_PLUGIN_URL . 'assets/css/style.css',
            [],
            TIDYIMAGES_VERSION
        );

        wp_enqueue_script(
            'ti-admin',
            TIDYIMAGES_PLUGIN_URL . 'assets/js/script.js',
            [ 'jquery' ],
            TIDYIMAGES_VERSION,
            true
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require TIDYIMAGES_PLUGIN_DIR . 'admin/admin-page.php';
    }

    public function handle_single_delete() {
        if (
            empty( $_GET['page'] ) ||
            'tidy-images' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ||
            empty( $_GET['ti_delete'] )
        ) {
            return;
        }

        $attachment_id = absint( $_GET['ti_delete'] );

        check_admin_referer( 'ti_delete_image_' . $attachment_id );

        if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
            wp_die( esc_html__( 'You do not have permission to delete this image.', 'tidy-images' ) );
        }

        $attachment = get_post( $attachment_id );

        if (
            ! $attachment ||
            'attachment' !== $attachment->post_type ||
            ! wp_attachment_is_image( $attachment_id )
        ) {
            wp_die( esc_html__( 'Invalid image attachment.', 'tidy-images' ) );
        }

        wp_delete_attachment( $attachment_id, true );

        $redirect = remove_query_arg(
            [ 'ti_delete', '_wpnonce' ],
            wp_get_referer() ?: admin_url( 'tools.php?page=tidy-images' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_bulk_delete() {
        if ( empty( $_POST['ti_bulk_delete'] ) ) {
            return;
        }

        check_admin_referer( 'ti_bulk_delete_action', 'ti_bulk_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'tidy-images' ) );
        }

        $attachment_ids = isset( $_POST['attachments'] )
            ? array_map( 'absint', (array) wp_unslash( $_POST['attachments'] ) )
            : [];

        foreach ( $attachment_ids as $attachment_id ) {
            if (
                $attachment_id &&
                current_user_can( 'delete_post', $attachment_id ) &&
                wp_attachment_is_image( $attachment_id )
            ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }

        wp_safe_redirect(
            wp_get_referer() ?: admin_url( 'tools.php?page=tidy-images&tab=unused' )
        );
        exit;
    }

    public function add_plugin_action_links( $links ) {
        $manage_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'tools.php?page=tidy-images' ) ),
            esc_html__( 'Manage Images', 'tidy-images' )
        );

        array_unshift( $links, $manage_link );

        return $links;
    }
}
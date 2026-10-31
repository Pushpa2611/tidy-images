<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TIDYIMAGES_Image_Scanner {

    private $usage_map = null;

    public function get_images( $status = 'unused', $page = 1, $per_page = 25 ) {
        $this->build_usage_map();

        $attachment_ids = get_posts(
            [
                'post_type'              => 'attachment',
                'post_mime_type'         => 'image',
                'post_status'            => 'inherit',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        $filtered_ids = [];

        foreach ( $attachment_ids as $attachment_id ) {
            $is_used = isset( $this->usage_map[ $attachment_id ] );

            if ( ( 'used' === $status && $is_used ) || ( 'unused' === $status && ! $is_used ) ) {
                $filtered_ids[] = $attachment_id;
            }
        }

        $total       = count( $filtered_ids );
        $total_pages = max( 1, (int) ceil( $total / $per_page ) );
        $page        = min( max( 1, (int) $page ), $total_pages );
        $offset      = ( $page - 1 ) * $per_page;
        $page_ids    = array_slice( $filtered_ids, $offset, $per_page );

        $images = [];

        if ( ! empty( $page_ids ) ) {
            $images = get_posts(
                [
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'post__in'       => $page_ids,
                    'orderby'        => 'post__in',
                    'posts_per_page' => count( $page_ids ),
                ]
            );
        }

        return [
            'images' => $images,
            'total'  => $total,
            'pages'  => $total_pages,
            'page'   => $page,
        ];
    }

    public function get_counts() {
        $this->build_usage_map();

        $attachment_ids = get_posts(
            [
                'post_type'              => 'attachment',
                'post_mime_type'         => 'image',
                'post_status'            => 'inherit',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        $used = 0;

        foreach ( $attachment_ids as $attachment_id ) {
            if ( isset( $this->usage_map[ $attachment_id ] ) ) {
                $used++;
            }
        }

        return [
            'used'   => $used,
            'unused' => count( $attachment_ids ) - $used,
        ];
    }

    public function get_image_usage( $attachment_id ) {
        $this->build_usage_map();

        return isset( $this->usage_map[ $attachment_id ] )
            ? array_values( $this->usage_map[ $attachment_id ] )
            : [];
    }

    private function build_usage_map() {
        if ( null !== $this->usage_map ) {
            return;
        }

        $this->usage_map = [];

        $post_types = get_post_types( [ 'public' => true ], 'names' );
        unset( $post_types['attachment'] );

        $post_ids = get_posts(
            [
                'post_type'              => array_values( $post_types ),
                'post_status'            => [ 'publish', 'private', 'draft', 'pending', 'future' ],
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        foreach ( $post_ids as $post_id ) {
            $this->scan_post( $post_id );
        }

        $this->add_global_usage();
    }

    private function scan_post( $post_id ) {
        $post_type = get_post_type( $post_id );
        $title     = get_the_title( $post_id );

        if ( '' === $title ) {
            /* translators: %d: Post ID. */
            $title = sprintf( __( 'Untitled #%d', 'tidy-images' ), $post_id );
        }

        $edit_link = get_edit_post_link( $post_id, 'raw' );

        $featured_id = (int) get_post_thumbnail_id( $post_id );
        if ( $featured_id ) {
            $this->add_usage( $featured_id, $post_id, $title, $edit_link, $post_type . ' — Featured image' );
        }

        $content = (string) get_post_field( 'post_content', $post_id );
        foreach ( $this->extract_attachment_ids_from_value( $content ) as $attachment_id ) {            
            $this->add_usage( $attachment_id, $post_id, $title, $edit_link, $post_type . ' — Content' );
        }

        $all_meta = get_post_meta( $post_id );
        foreach ( $all_meta as $meta_key => $values ) {
            if ( '_thumbnail_id' === $meta_key ) {
                continue;
            }

            foreach ( $values as $value ) {
                foreach ( $this->extract_attachment_ids_from_value( $value ) as $attachment_id ) {                    
                    $label = '_elementor_data' === $meta_key ? 'Elementor' : 'Custom field';
                    $this->add_usage( $attachment_id, $post_id, $title, $edit_link, $post_type . ' — ' . $label );
                }
            }
        }

        if ( 'product' === $post_type && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post_id );

            if ( $product ) {
                foreach ( $product->get_gallery_image_ids() as $attachment_id ) {
                    $this->add_usage( (int) $attachment_id, $post_id, $title, $edit_link, 'product — Gallery' );
                }

                if ( $product->is_type( 'variable' ) ) {
                    foreach ( $product->get_children() as $variation_id ) {
                        $variation_image_id = (int) get_post_thumbnail_id( $variation_id );

                        if ( $variation_image_id ) {
                            $this->add_usage(
                                $variation_image_id,
                                $post_id,
                                $title,
                                $edit_link,
                                'product — Variation image'
                            );
                        }
                    }
                }
            }
        }
    }

    private function extract_attachment_ids_from_value( $value ) {
        $ids = [];

        /*
        * Direct attachment ID.
        *
        * Example:
        * 123
        */
        if ( is_numeric( $value ) ) {
            $attachment_id = (int) $value;

            if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
                return [ $attachment_id ];
            }

            return [];
        }

        /*
        * Unserialize WordPress meta values.
        */
        if ( is_serialized( $value ) ) {
            $value = maybe_unserialize( $value );
        }

        /*
        * Scan arrays and objects recursively.
        */
        if ( is_array( $value ) || is_object( $value ) ) {
            $this->extract_attachment_ids_recursive( $value, $ids );

            $value = wp_json_encode( $value );
        }

        if ( ! is_string( $value ) || '' === $value ) {
            return array_values( $ids );
        }

        /*
        * Gutenberg / Classic Editor.
        *
        * Example:
        * wp-image-123
        */
        preg_match_all(
            '/wp-image-(\d+)/i',
            $value,
            $class_matches
        );

        /*
        * JSON / Elementor / ACF.
        *
        * Examples:
        * "id": 123
        * "ID": 123
        */
        preg_match_all(
            '/["\']id["\']\s*:\s*"?(\d+)"?/i',
            $value,
            $id_matches
        );

        /*
        * Other image structures.
        *
        * Example:
        * "image": 123
        */
        preg_match_all(
            '/["\']image["\']\s*:\s*"?(\d+)"?/i',
            $value,
            $image_matches
        );

        $candidate_ids = array_merge(
            $class_matches[1] ?? [],
            $id_matches[1] ?? [],
            $image_matches[1] ?? []
        );

        foreach ( $candidate_ids as $candidate_id ) {
            $candidate_id = (int) $candidate_id;

            if ( $candidate_id && wp_attachment_is_image( $candidate_id ) ) {
                $ids[ $candidate_id ] = $candidate_id;
            }
        }

        /*
        * Detect direct attachment URL.
        */
        $attachment_urls = $this->get_attachment_urls();

        foreach ( $attachment_urls as $attachment_id => $url ) {
            if ( $url && false !== strpos( $value, $url ) ) {
                $ids[ $attachment_id ] = $attachment_id;
            }
        }

        return array_values( $ids );
    }

    private function extract_attachment_ids_recursive( $value, &$ids ) {
        if ( is_object( $value ) ) {
            $value = (array) $value;
        }

        if ( ! is_array( $value ) ) {
            if ( is_numeric( $value ) ) {
                $attachment_id = (int) $value;

                if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
                    $ids[ $attachment_id ] = $attachment_id;
                }
            }

            return;
        }

        foreach ( $value as $key => $item ) {
            $image_keys = [
                'id',
                'image',
                'image_id',
                'attachment_id',
            ];

            if (
                in_array( strtolower( (string) $key ), $image_keys, true )
                && is_numeric( $item )
            ) {
                $attachment_id = (int) $item;

                if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
                    $ids[ $attachment_id ] = $attachment_id;
                }
            }

            if ( is_array( $item ) || is_object( $item ) ) {
                $this->extract_attachment_ids_recursive( $item, $ids );
            }
        }
    }

    private function get_attachment_urls() {
        static $urls = null;

        if ( null !== $urls ) {
            return $urls;
        }

        $urls = [];

        $attachment_ids = get_posts(
            [
                'post_type'              => 'attachment',
                'post_mime_type'         => 'image',
                'post_status'            => 'inherit',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        foreach ( $attachment_ids as $attachment_id ) {
            $url = wp_get_attachment_url( $attachment_id );

            if ( $url ) {
                $urls[ $attachment_id ] = $url;
            }
        }

        return $urls;
    }

    private function add_global_usage() {
        $globals = [
            'site_icon'                     => 'WordPress — Site Icon',
            'custom_logo'                   => 'WordPress — Custom Logo',
            'woocommerce_placeholder_image' => 'WooCommerce — Placeholder Image',
        ];

        foreach ( $globals as $option_name => $label ) {
            $attachment_id = (int) get_option( $option_name );

            if ( ! $attachment_id ) {
                continue;
            }

            $link = admin_url( 'options-general.php' );

            if ( 'woocommerce_placeholder_image' === $option_name ) {
                $link = admin_url( 'admin.php?page=wc-settings&tab=products' );
            }

            $this->usage_map[ $attachment_id ]['global-' . $option_name] = [
                'title' => $label,
                'link'  => $link,
                'type'  => 'Global setting',
            ];
        }
    }

    private function add_usage( $attachment_id, $post_id, $title, $link, $type ) {
        $attachment_id = (int) $attachment_id;

        if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        $key = $post_id . '-' . sanitize_key( $type );

        $this->usage_map[ $attachment_id ][ $key ] = [
            'title' => $title,
            'link'  => $link,
            'type'  => $type,
        ];
    }
}

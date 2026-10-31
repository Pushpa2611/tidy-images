<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameters.

$tidyimages_tab = isset( $_GET['tab'] ) && 'used' === sanitize_key( wp_unslash( $_GET['tab'] ) )
    ? 'used'
    : 'unused';

$tidyimages_current_page = isset( $_GET['paged'] )
    ? max( 1, absint( $_GET['paged'] ) )
    : 1;

// phpcs:enable WordPress.Security.NonceVerification.Recommended

$tidyimages_per_page = 25;

$tidyimages_scanner = new TIDYIMAGES_Image_Scanner();
$tidyimages_counts  = $tidyimages_scanner->get_counts();
$tidyimages_result  = $tidyimages_scanner->get_images(
    $tidyimages_tab,
    $tidyimages_current_page,
    $tidyimages_per_page
);
?>

<div class="wrap ti-wrap">

    <div class="ti-header">
        <h1><?php esc_html_e( 'Tidy Images', 'tidy-images' ); ?></h1>
        <p><?php esc_html_e( 'Find where images are used and permanently remove unused media files.', 'tidy-images' ); ?></p>
    </div>

    <nav class="nav-tab-wrapper ti-tabs">
        <a
            class="nav-tab <?php echo 'used' === $tidyimages_tab ? 'nav-tab-active' : ''; ?>"
            href="<?php echo esc_url( admin_url( 'tools.php?page=tidy-images&tab=used' ) ); ?>"
        >
            <?php echo esc_html( sprintf( 'Used Images (%d)', $tidyimages_counts['used'] ) ); ?>
        </a>

        <a
            class="nav-tab <?php echo 'unused' === $tidyimages_tab ? 'nav-tab-active' : ''; ?>"
            href="<?php echo esc_url( admin_url( 'tools.php?page=tidy-images&tab=unused' ) ); ?>"
        >
            <?php echo esc_html( sprintf( 'Unused Images (%d)', $tidyimages_counts['unused'] ) ); ?>
        </a>
    </nav>

    <?php if ( empty( $tidyimages_result['images'] ) ) : ?>

        <div class="ti-empty">
            <p>
                <?php
                echo 'used' === $tidyimages_tab
                    ? esc_html__( 'No used images found.', 'tidy-images' )
                    : esc_html__( 'Great! No unused images found.', 'tidy-images' );
                ?>
            </p>
        </div>

    <?php else : ?>

        <form method="post" id="ti-bulk-form">

            <?php if ( 'unused' === $tidyimages_tab ) : ?>

                <?php wp_nonce_field( 'ti_bulk_delete_action', 'ti_bulk_nonce' ); ?>

                <div class="ti-bulk-actions">
                    <label>
                        <input type="checkbox" id="select-all">
                        <?php esc_html_e( 'Select all on this page', 'tidy-images' ); ?>
                    </label>

                    <button
                        type="submit"
                        name="ti_bulk_delete"
                        value="1"
                        class="button button-primary"
                        id="ti-bulk-delete"
                        disabled
                    >
                        <?php esc_html_e( 'Delete Selected Permanently', 'tidy-images' ); ?>
                    </button>
                </div>

            <?php endif; ?>

            <table class="widefat striped ti-image-table">

                <thead>
                    <tr>
                        <?php if ( 'unused' === $tidyimages_tab ) : ?>
                            <th class="check-column"></th>
                        <?php endif; ?>

                        <th><?php esc_html_e( 'Image', 'tidy-images' ); ?></th>
                        <th><?php esc_html_e( 'Title / File', 'tidy-images' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'tidy-images' ); ?></th>

                        <?php if ( 'used' === $tidyimages_tab ) : ?>
                            <th><?php esc_html_e( 'Used In', 'tidy-images' ); ?></th>
                        <?php else : ?>
                            <th><?php esc_html_e( 'Action', 'tidy-images' ); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ( $tidyimages_result['images'] as $tidyimages_image ) : ?>

                        <?php
                        $tidyimages_thumb = wp_get_attachment_image_src(
                            $tidyimages_image->ID,
                            'thumbnail'
                        );

                        $tidyimages_meta = wp_get_attachment_metadata(
                            $tidyimages_image->ID
                        );

                        $tidyimages_file = get_attached_file(
                            $tidyimages_image->ID
                        );

                        $tidyimages_filename = $tidyimages_file
                            ? wp_basename( $tidyimages_file )
                            : wp_basename( $tidyimages_image->guid );

                        $tidyimages_filesize = $tidyimages_file && file_exists( $tidyimages_file )
                            ? filesize( $tidyimages_file )
                            : ( $tidyimages_meta['filesize'] ?? 0 );
                        ?>

                        <tr>

                            <?php if ( 'unused' === $tidyimages_tab ) : ?>
                                <th class="check-column">
                                    <input
                                        type="checkbox"
                                        class="image-checkbox"
                                        name="attachments[]"
                                        value="<?php echo esc_attr( $tidyimages_image->ID ); ?>"
                                    >
                                </th>
                            <?php endif; ?>

                            <td class="ti-image-cell">
                                <?php if ( $tidyimages_thumb ) : ?>
                                    <img
                                        src="<?php echo esc_url( $tidyimages_thumb[0] ); ?>"
                                        alt=""
                                        class="ti-thumbnail"
                                    >
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo esc_html( $tidyimages_image->post_title ?: 'Untitled' ); ?>
                                </strong>
                                <br>
                                <small><?php echo esc_html( $tidyimages_filename ); ?></small>
                            </td>

                            <td>
                                <?php echo esc_html( size_format( (int) $tidyimages_filesize ) ); ?>
                            </td>

                            <?php if ( 'used' === $tidyimages_tab ) : ?>

                                <td>
                                    <?php
                                    $tidyimages_usages = $tidyimages_scanner->get_image_usage(
                                        $tidyimages_image->ID
                                    );
                                    ?>

                                    <?php if ( $tidyimages_usages ) : ?>

                                        <?php
                                        $tidyimages_visible_usage_count = 2;
                                        $tidyimages_total_usages        = count( $tidyimages_usages );
                                        $tidyimages_remaining_usages    = max(
                                            0,
                                            $tidyimages_total_usages - $tidyimages_visible_usage_count
                                        );
                                        ?>

                                        <div class="ti-usage-wrapper">

                                            <ul class="ti-usage-list">

                                                <?php foreach ( $tidyimages_usages as $tidyimages_index => $tidyimages_usage ) : ?>

                                                    <li
                                                        class="<?php echo $tidyimages_index >= $tidyimages_visible_usage_count ? 'ti-usage-extra' : ''; ?>"
                                                    >
                                                        <a href="<?php echo esc_url( $tidyimages_usage['link'] ); ?>">
                                                            <?php echo esc_html( $tidyimages_usage['title'] ); ?>
                                                        </a>

                                                        <small>
                                                            <?php echo esc_html( $tidyimages_usage['type'] ); ?>
                                                        </small>
                                                    </li>

                                                <?php endforeach; ?>

                                            </ul>

                                            <?php if ( $tidyimages_remaining_usages > 0 ) : ?>

                                                <button
                                                    type="button"
                                                    class="ti-toggle-usages"
                                                    data-more-text="<?php echo esc_attr(
                                                        sprintf(
                                                            /* translators: %d: Number of additional image usage locations. */
                                                            _n(
                                                                '+ %d more location',
                                                                '+ %d more locations',
                                                                $tidyimages_remaining_usages,
                                                                'tidy-images'
                                                            ),
                                                            $tidyimages_remaining_usages
                                                        )
                                                    ); ?>"
                                                    data-less-text="<?php esc_attr_e( 'Show less', 'tidy-images' ); ?>"
                                                >
                                                    <?php
                                                    echo esc_html(
                                                        sprintf(
                                                            /* translators: %d: Number of additional image usage locations. */
                                                            _n(
                                                                '+ %d more location',
                                                                '+ %d more locations',
                                                                $tidyimages_remaining_usages,
                                                                'tidy-images'
                                                            ),
                                                            $tidyimages_remaining_usages
                                                        )
                                                    );
                                                    ?>
                                                </button>

                                            <?php endif; ?>

                                        </div>

                                    <?php else : ?>

                                        <em>
                                            <?php esc_html_e( 'Usage detected.', 'tidy-images' ); ?>
                                        </em>

                                    <?php endif; ?>

                                </td>

                            <?php else : ?>

                                <td>
                                    <?php
                                    $tidyimages_delete_url = wp_nonce_url(
                                        admin_url(
                                            'tools.php?page=tidy-images&tab=unused&paged=' .
                                            $tidyimages_result['page'] .
                                            '&ti_delete=' .
                                            $tidyimages_image->ID
                                        ),
                                        'ti_delete_image_' . $tidyimages_image->ID
                                    );
                                    ?>

                                    <a
                                        href="<?php echo esc_url( $tidyimages_delete_url ); ?>"
                                        class="button button-link-delete ti-delete-single"
                                    >
                                        <?php esc_html_e( 'Delete Permanently', 'tidy-images' ); ?>
                                    </a>
                                </td>

                            <?php endif; ?>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </form>

        <?php if ( $tidyimages_result['pages'] > 1 ) : ?>

            <div class="tablenav">
                <div class="tablenav-pages">

                    <?php
                    echo wp_kses_post(
                        paginate_links(
                            [
                                'base'      => add_query_arg(
                                    [
                                        'page'  => 'tidy-images',
                                        'tab'   => $tidyimages_tab,
                                        'paged' => '%#%',
                                    ],
                                    admin_url( 'tools.php' )
                                ),
                                'current'   => $tidyimages_result['page'],
                                'total'     => $tidyimages_result['pages'],
                                'prev_text' => '‹',
                                'next_text' => '›',
                            ]
                        )
                    );
                    ?>

                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>
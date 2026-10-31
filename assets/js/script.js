jQuery(function ($) {
    'use strict';

    var $selectAll = $('#select-all');
    var $bulkForm = $('#ti-bulk-form');
    var $bulkButton = $('#ti-bulk-delete');
    var $checkboxes = $('.image-checkbox');

    /**
     * "Select all on this page" -> checks/unchecks every row checkbox.
     */
    $selectAll.on('change', function () {
        $checkboxes.prop('checked', this.checked);
        updateBulkButtonState();
    });

    /**
     * Keep "Select all" in sync when a row checkbox is changed by hand:
     * - all checked  -> select-all checked
     * - none checked -> select-all unchecked
     * - some checked -> select-all shown as indeterminate (visual only)
     */
    $checkboxes.on('change', function () {
        syncSelectAllState();
        updateBulkButtonState();
    });

    /**
     * Confirm before permanently deleting a single image.
     */
    $('.ti-delete-single').on('click', function (event) {
        if (!window.confirm('Are you sure? This will permanently delete the image and its generated sizes. This action cannot be undone.')) {
            event.preventDefault();
        }
    });

    /**
     * Confirm before bulk-deleting selected images.
     */
    $bulkForm.on('submit', function (event) {
        var selected = $checkboxes.filter(':checked').length;

        if (!selected) {
            event.preventDefault();
            window.alert('Select at least one image.');
            return;
        }

        if (!window.confirm('Permanently delete ' + selected + ' selected image(s)? This action cannot be undone.')) {
            event.preventDefault();
        }
    });

    function syncSelectAllState() {
        if (!$selectAll.length) {
            return;
        }

        var total = $checkboxes.length;
        var checked = $checkboxes.filter(':checked').length;

        $selectAll.prop('checked', total > 0 && checked === total);
        $selectAll.prop('indeterminate', checked > 0 && checked < total);
    }

    function updateBulkButtonState() {
        if (!$bulkButton.length) {
            return;
        }

        var hasSelection = $checkboxes.filter(':checked').length > 0;
        $bulkButton.prop('disabled', !hasSelection);
    }

    /**
     * Expand or collapse additional image usage locations.
     */
    $('.ti-toggle-usages').on('click', function () {
        var $button = $(this);
        var $wrapper = $button.closest('.ti-usage-wrapper');
        var $extraUsages = $wrapper.find('.ti-usage-extra');
        var isExpanded = $button.attr('aria-expanded') === 'true';

        if (isExpanded) {
            $extraUsages.removeClass('is-visible');

            $button
                .attr('aria-expanded', 'false')
                .text($button.data('more-text'));
        } else {
            $extraUsages.addClass('is-visible');

            $button
                .attr('aria-expanded', 'true')
                .text($button.data('less-text'));
        }
    });

    // Initial state on page load.
    syncSelectAllState();
    updateBulkButtonState();
});
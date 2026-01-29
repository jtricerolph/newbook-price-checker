/**
 * NewBook Price Checker - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var siteIndex = $('#nbpc-sites-container .nbpc-site-row').length;

        // Add new site
        $('#nbpc-add-site').on('click', function() {
            var template = $('#nbpc-site-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, siteIndex);
            $('#nbpc-sites-container').append(template);

            // Expand the newly added site
            var newRow = $('#nbpc-sites-container .nbpc-site-row').last();
            newRow.find('.nbpc-site-fields').show();
            newRow.find('.nbpc-toggle-site').text('Collapse');

            siteIndex++;
        });

        // Toggle site fields
        $(document).on('click', '.nbpc-toggle-site', function() {
            var row = $(this).closest('.nbpc-site-row');
            var fields = row.find('.nbpc-site-fields');

            if (fields.is(':visible')) {
                fields.slideUp(200);
                $(this).text('Expand');
            } else {
                fields.slideDown(200);
                $(this).text('Collapse');
            }
        });

        // Remove site
        $(document).on('click', '.nbpc-remove-site', function() {
            if (confirm('Are you sure you want to remove this site?')) {
                $(this).closest('.nbpc-site-row').remove();
            }
        });

        // Update site title when name changes
        $(document).on('input', '.nbpc-site-name-input', function() {
            var row = $(this).closest('.nbpc-site-row');
            var title = $(this).val() || 'New Site';
            row.find('.nbpc-site-title').text(title);
        });

        // Ensure only one primary site
        $(document).on('change', '.nbpc-primary-checkbox', function() {
            if ($(this).is(':checked')) {
                $('.nbpc-primary-checkbox').not(this).prop('checked', false);
            }
        });
    });

})(jQuery);

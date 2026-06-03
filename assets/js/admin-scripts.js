jQuery(document).ready(function($){
    // Localized i18n strings (rdAdminScripts.i18n), with en-US fallbacks if absent
    var rdI18n = (window.rdAdminScripts && window.rdAdminScripts.i18n) || {};

    // .on('click', ...) instead of .click(fn) — the shorthand was removed in jQuery 4
    // (jquery-migrate only warns for now, but it'll break once WP updates)
    $('.rd-upload-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var input_id = button.data('input-id');

        var custom_uploader = wp.media({
            title: rdI18n.selectImage || 'Select fallback image',
            button: { text: rdI18n.useImage || 'Use this image' },
            multiple: false
        }).on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $('#' + input_id).val(attachment.url); // Store the URL in the input
            $('#' + input_id + '_preview').html('<img src="'+attachment.url+'" style="max-width:200px; height:auto; border:1px solid #ccc; display:block;">');
            button.siblings('.rd-remove-button').show();
        }).open();
    });

    $('.rd-remove-button').on('click', function() {
        var input_id = $(this).data('input-id');
        $('#' + input_id).val('');
        $('#' + input_id + '_preview').html('');
        $(this).hide();
    });
});
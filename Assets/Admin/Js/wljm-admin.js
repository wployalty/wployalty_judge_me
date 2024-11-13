if (typeof (wljm_jquery) == 'undefined') {
    wljm_jquery = jQuery.noConflict();
}
wljm_jquery(document).on('click', '#wljm-main-page #wljm-webhook-delete', function () {
    let webhook_key = wljm_jquery(this).data('webhook-key');
    let button = wljm_jquery(this);
    wljm_jquery.ajax({
        data: {
            webhook_key: webhook_key,
            action: 'wljm_webhook_delete',
            wljm_nonce: wljm_localize_data.delete_nonce
        },
        type: 'post',
        url: wljm_localize_data.ajax_url,
        beforeSend: function () {
            let confirm_status = confirm(wljm_localize_data.confirm_label);
            if (confirm_status === true) {
                button.attr('disabled', true);
                button.html(wljm_localize_data.deleting_button_label);
            } else {
                button.attr('disabled', false);
                button.html(wljm_localize_data.delete_button_label);
                return false;
            }
        },
        error: function (request, error) {
        },
        success: function (json) {
            button.attr('disabled', false);
            button.html(wljm_localize_data.delete_button_label);
            if (json.success == true) {
                createToast(json.message,'wlr-success');
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else if (json.success == false) {
                createToast(json.message,'wlr-error');
            }
        }
    });
});

wljm_jquery(document).on('click', '#wljm-main-page #wljm-webhook-create', function () {
    let webhook_key = wljm_jquery(this).data('webhook-key');
    let button = wljm_jquery(this);
    wljm_jquery(this).attr('disabled', true);
    wljm_jquery(this).html(wljm_localize_data.creating_button_label);
    wljm_jquery.ajax({
        data: {
            webhook_key: webhook_key,
            action: 'wljm_webhook_create',
            wljm_nonce: wljm_localize_data.create_nonce
        },
        type: 'post',
        url: wljm_localize_data.ajax_url,
        error: function (request, error) {
        },
        success: function (json) {
            button.attr('disabled', false);
            button.html(wljm_localize_data.create_button_label);
            if (json.success == true) {
                createToast(json.message,'wlr-success'  );
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else if (json.success == false) {
                createToast(json.message,'wlr-error' );
            }
        }
    });
});
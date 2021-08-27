jQuery(document).ready(function ($) {
    $("#woocommerce_woocommerce-smsapi_marketing_sms_user_consent").change(function () {
        if ($(this).is(':checked')) {
            $("#woocommerce_woocommerce-smsapi_checkbox_text, #woocommerce_woocommerce-smsapi_checkbox_position").removeAttr('disabled').closest('tr').show();
        } else {
            $("#woocommerce_woocommerce-smsapi_checkbox_text, #woocommerce_woocommerce-smsapi_checkbox_position").attr('disabled', 'disabled').closest('tr').hide();
        }
    });

    $("#woocommerce_woocommerce-smsapi_processing_order_sms_enabled").change(function () {
        if ($(this).is(':checked')) {
            $("#woocommerce_woocommerce-smsapi_processing_order_sms_text").removeAttr('disabled').closest('tr').show();
        } else {
            $("#woocommerce_woocommerce-smsapi_processing_order_sms_text").attr('disabled', 'disabled').closest('tr').hide();
        }
    });

    $("#woocommerce_woocommerce-smsapi_completed_order_sms_enabled").change(function () {
        if ($(this).is(':checked')) {
            $("#woocommerce_woocommerce-smsapi_completed_order_sms_text").removeAttr('disabled').closest('tr').show();
        } else {
            $("#woocommerce_woocommerce-smsapi_completed_order_sms_text").attr('disabled', 'disabled').closest('tr').hide();
        }
    });

    $("#woocommerce_woocommerce-smsapi_marketing_sms_user_consent, #woocommerce_woocommerce-smsapi_processing_order_sms_enabled, #woocommerce_woocommerce-smsapi_completed_order_sms_enabled").trigger("change");


    var api_ver_select = jQuery("#woocommerce_woocommerce-smsapi_api_ver");
});
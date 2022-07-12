let $ = jQuery;
if( $ ){
    $(function (){
        var test_model = $('#woocommerce_'+ ab_admin_params.id +'_use_test_mode');

        verificationModel(test_model.is(':checked'));

        test_model.click(function () {
            verificationModel($(this).is(":checked"));
        });

        function verificationModel(bool) {
            $('#woocommerce_'+ ab_admin_params.id +'_gateway_no').attr('readonly',bool);
            $('#woocommerce_'+ ab_admin_params.id +'_signkey_code').attr('readonly',bool);
            $('#woocommerce_'+ ab_admin_params.id +'_test_gateway_no').attr('readonly',!bool);
            $('#woocommerce_'+ ab_admin_params.id +'_test_signkey_code').attr('readonly',!bool);
        }

        if( ab_admin_params.id === 'wc_asiabill_creditcard' ){

            var checkout_mode = $("#woocommerce_wc_asiabill_creditcard_checkout_mode");
            var from_style_tr = $("#woocommerce_wc_asiabill_creditcard_form_style").parents("tr");
            var save_card_tr = $("#woocommerce_wc_asiabill_creditcard_save_cards").parents("tr");

            verificationCheckout(checkout_mode.val());

            checkout_mode.change(function () {
                verificationCheckout(checkout_mode.val());
            });

            function verificationCheckout(val) {
                if( val === '1' ){
                    from_style_tr.show();
                    save_card_tr.show();
                }else {
                    from_style_tr.hide();
                    save_card_tr.hide();
                }
            }

        }

    })
}
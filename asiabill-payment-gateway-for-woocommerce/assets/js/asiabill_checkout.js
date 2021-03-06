jQuery( function( $ ) {
    'use strict';

    try {
        var ab = AsiabillPay(wc_asiabill_params.token);
    }catch( error ) {
        console.log( error );
        return;
    }

    let initAsiabillPaymentSdk =  {

        is_init : false,
        form : null,
        checkPage : wc_asiabill_params.checkoutPayPage,

        init : function () {

            this.form = this.checkPage === '1' ? $( '#order_review' ) : $( 'form.woocommerce-checkout' );

            if( this.checkPage === '1' ){
                initAsiabillPaymentSdk.createElements();
            }else{
                $( document.body ).on( 'updated_checkout', function() {
                    initAsiabillPaymentSdk.createElements();
                });
            }

        },

        createElements: function () {
            ab.elementInit("payment_steps", {
                formId: 'asiabill-card',
                formWrapperId: 'asiabill-card-element',
                frameId: 'asiabill-card-frame',
                customerId: '',
                autoValidate:false,
                layout: wc_asiabill_params.layout
            }).then((res) => {
                console.log("initRES", res)
            }).catch((err) => {
                console.log("initERR", err)
            });

            let btn = $('#place_order');
            btn.on('click',function () {
                return initAsiabillPaymentSdk.checkoutOrder();
            });
        },

        checkoutOrder : function () {

            if( this.checkPage === '1' ){
                var billing = wc_asiabill_params.billing;
            }else {

                var billing = {
                    "address": {
                        "city": $( '#billing_city' ).val(),
                        "country": $( '#billing_country' ).val(),
                        "line1": $( '#billing_address_1' ).val(),
                        "line2": $( '#billing_address_2' ).val(),
                        "postalCode": $( '#billing_postcode' ).val(),
                        "state": $( '#billing_state' ).val()
                    },
                    "email": $( '#billing_email' ).val(),
                    "firstName": $( '#billing_first_name' ).val(),
                    "lastName": $( '#billing_last_name' ).val() ,
                    "phone": $( '#billing_phone' ).val()
                };
            }

            let paymentMethodObj = {
                "billingDetail": billing,
                "card": {
                    "cardNo": "",
                    "cardExpireMonth": "",
                    "cardExpireYear": "",
                    "cardSecurityCode": "",
                    "issuingBank": ""
                },
                "customerId": '',
            };

            this.errorMessage('');

            if($('#payment_method_wc_asiabill_creditcard').is( ':checked' )){

                let token = $('input[name=wc-wc_asiabill_creditcard-payment-token]:checked').val();

                if( token != undefined && token!== 'new' ){
                    // ??????token??????
                    return true
                }
                else {
                    // ???????????????
                    $( '.asiabill-payment' ).remove();
                    ab.confirmPaymentMethod({
                        apikey: wc_asiabill_params.token,
                        trnxDetail: paymentMethodObj
                    }).then((result) => {
                        if( result.data.code === "0" ){
                            // ????????????
                            initAsiabillPaymentSdk.form.append(
                                $( '<input type="hidden" />' )
                                    .addClass( 'asiabill-payment' )
                                    .attr( 'name', 'asiabill_payment' )
                                    .val( result.data.data.customerPaymentMethodId )
                            );
                            initAsiabillPaymentSdk.form.append(
                                $( '<input type="hidden" />' )
                                    .addClass( 'asiabill-payment' )
                                    .attr( 'name', 'asiabill_check_page' )
                                    .val( initAsiabillPaymentSdk.checkPage )
                            );
                            initAsiabillPaymentSdk.form.trigger( 'submit' );
                        }
                        else {
                            // ????????????
                            initAsiabillPaymentSdk.errorMessage(result.data.message);
                        }
                    });
                    return false;
                }

            }
        },

        errorMessage : function (message = ''){
            if( message.trim() !== '' ){
                $('#asiabill-card-error').html(message).removeClass('hide');
            }else{
                $('#asiabill-card-error').html('').addClass('hide');
            }
        }


    };

    window.addEventListener("getErrorMessage", e => {
        initAsiabillPaymentSdk.errorMessage(e.detail.errorMessage);
    });

    initAsiabillPaymentSdk.init();


});













jQuery( function( $ ) {
    'use strict';

    function getCookie (name) {
        let strcookie = document.cookie;
        let arrcookie = strcookie.split("; ");
        for ( var i = 0; i < arrcookie.length; i++) {
            var arr = arrcookie[i].split("=");
            if (arr[0] == name){
                return arr[1];
            }
        }
        return "";
    }

    try {
        var ab = AsiabillPay(getCookie('AsiabillSessionToken'));
    }catch( error ) {
        console.log( error );
        return;
    }

    let initAsiabillPaymentSdk =  {

        is_init : false,
        form : null,
        checkPage : wc_asiabill_params.checkoutPayPage,
        card_error : $('#asiabill-card-error'),

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
                mode: wc_asiabill_params.mode,
                customerId: wc_asiabill_params.customer_id,
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
                let customerObj = {
                    description: '',
                    email: $( '#billing_email' ).val(),
                    firstName: $( '#billing_first_name' ).val(),
                    lastName: $( '#billing_last_name' ).val(),
                    phone: $( '#billing_phone' ).val()
                };

                var billing = {
                    "address": {
                        "city": $( '#billing_city' ).val(),
                        "country": $( '#billing_country' ).val(),
                        "line1": $( '#billing_address_1' ).val(),
                        "line2": $( '#billing_address_2' ).val(),
                        "postalCode": $( '#billing_postcode' ).val(),
                        "state": $( '#billing_state' ).val()
                    },
                    "email": customerObj.email,
                    "firstName": customerObj.firstName,
                    "lastName": customerObj.lastName ,
                    "phone": customerObj.phone
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
                //"customerId": wc_asiabill_params.customer_id,
                //"signInfo": ''
            };

            this.errorMessage('');

            if($('#payment_method_wc_asiabill_creditcard').is( ':checked' )){

                let token = $('input[name=wc-wc_asiabill_creditcard-payment-token]:checked').val();

                if( token != undefined && token!== 'new' ){
                    // 使用token支付
                    return true
                }
                else {
                    // 使用卡支付
                    $( '.asiabill-payment' ).remove();
                    ab.confirmPaymentMethod({
                        apikey: getCookie('AsiabillSessionToken'),
                        trnxDetail: paymentMethodObj
                    }).then((result) => {
                        if( result.data.code === "0" ){
                            // 保存成功
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
                            // 保存失败
                            initAsiabillPaymentSdk.errorMessage(result.data.message);
                        }
                    });
                    return false;
                }

            }
        },

        errorMessage : function (message = ''){
            if( message.trim() !== '' ){
                this.card_error.html(message).removeClass('hide');
            }else{
                this.card_error.html('').addClass('hide');
            }
        }


    };

    window.addEventListener("getErrorMessage", e => {
        initAsiabillPaymentSdk.errorMessage(e.detail.errorMessage);
    });

    initAsiabillPaymentSdk.init();


});













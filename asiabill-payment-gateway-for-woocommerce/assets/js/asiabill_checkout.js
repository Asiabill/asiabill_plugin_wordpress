

    let $ = jQuery;


    let initAsiabillPaymentSdk = () => {

        let is_init = false;

        let getCookie = (name) => {
            let strcookie = document.cookie;
            let arrcookie = strcookie.split("; ");
            for ( var i = 0; i < arrcookie.length; i++) {
                var arr = arrcookie[i].split("=");
                if (arr[0] == name){
                    return arr[1];
                }
            }
            return "";
        };

        let form = $( 'form.woocommerce-checkout' );

        let ab = AsiabillPay(getCookie('AsiabillSessionToken'));

        ab.elementInit("payment_steps", {
            formId: 'asiabill-card-element', // 页面表单id
            frameId: 'asiabill-card-frame', // 生成的IframeId
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
            return checkoutOrder();
        });

        let checkoutOrder = () => {

            let customerObj = {
                description: '',
                email: $( '#billing_email' ).val(),
                firstName: $( '#billing_first_name' ).val(),
                lastName: $( '#billing_last_name' ).val(),
                phone: $( '#billing_phone' ).val()
            };
            let paymentMethodObj = {
                "billingDetail": {
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
                },
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

            errorMessage('');

            if($('#payment_method_wc_asiabill_creditcard').is( ':checked' )){

                let token = $('input[name=wc-wc_asiabill_creditcard-payment-token]:checked').val();

                //console.log('token => '+token);
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
                            form.append(
                                $( '<input type="hidden" />' )
                                    .addClass( 'asiabill-payment' )
                                    .attr( 'name', 'asiabill_payment' )
                                    .val( result.data.data.customerPaymentMethodId )
                            );
                            form.trigger( 'submit' );
                        }
                        else {
                            // 保存失败
                            errorMessage(result.data.message);
                        }
                    });
                    return false;
                }

            }
        };

        window.addEventListener("getErrorMessage", e => {
            errorMessage(e.detail.errorMessage);
        });

        let card_error = $('#card-error');
        let errorMessage = (message = '') => {
            if( message.trim() != '' ){
                card_error.html(message).removeClass('hide');
            }else{
                card_error.html('').addClass('hide');
            }
        }


    };













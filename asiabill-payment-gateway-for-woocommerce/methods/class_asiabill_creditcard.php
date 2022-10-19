<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Creditcard extends WC_Asiabill_Payment_Gateway {

    public $id;
    public $method_title       = 'AsiaBill Credit Card Payment';
    public $method_description = 'Credit Card 信用卡支付';
    protected $is_inline;

	public function __construct() {

	    parent::__construct('wc_asiabill_creditcard');

        $this->is_inline = $this->get_option('checkout_mode') === '1'?'yes':'no';
        $this->has_fields = $this->is_inline == 'yes';

        if( $this->is_inline == 'yes' ){
            $this->supports[] = 'tokenization';
        }


        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );


        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Credit Card';

        $this->form_fields['checkout_mode'] = array(
            'title' => __ ( 'Checkout mode', 'asiabill' ),
            'type' => 'select',
            'default' => '1',
            'desc_tip'    => true,
            'options'     => [
                '1' => __( 'In-page Checkout', 'asiabill' ),
                '2'  => __( 'Hosted Payment Page', 'asiabill' ),
            ],
        );
        $this->form_fields['form_style'] = array(
            'title' => __ ( 'Form style', 'asiabill' ),
            'type' => 'select',
            'default' => 'inner',
            'desc_tip'    => true,
            'options'     => [
                'inner' => __( 'One row', 'asiabill' ),
                'block'  => __( 'Two rows', 'asiabill' ),
            ],
        );
        $this->form_fields['save_cards'] = array(
            'title' => __ ( 'Saved Cards', 'asiabill' ),
            'label'=> __ ( 'Enable Payment via Saved Cards', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );

        $this->form_fields['visa_logo'] = array(
            'title' => __ ( 'Visa', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
        $this->form_fields['master_card_logo'] = array(
            'title' => __ ( 'MasterCard', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
        $this->form_fields['jcb_logo'] = array(
            'title' => __ ( 'JCB', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
        $this->form_fields['ae_logo'] = array(
            'title' => __ ( 'American Express', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
        $this->form_fields['discover_logo'] = array(
            'title' => __ ( 'Discover', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
        $this->form_fields['diners_logo'] = array(
            'title' => __ ( 'Diners', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
	}

	public function process_admin_options(){
	    if( $this->get_option('gateway_no') != sanitize_text_field($_POST['woocommerce_'.$this->id.'_gateway_no']) ){
            Wc_Asiabill_Customer::dump_customer();
        }
        parent::process_admin_options();
    }

    public function payment_fields(){

        if( is_add_payment_method_page() ){
            echo apply_filters( 'wc_'.$this->id.'_description', __('New payment methods can only be added during checkout'), $this->id );
        }else{
            parent::payment_fields();
            if( $this->is_inline == 'yes' ){
                $this->elements_form();
            }
        }

    }

    public function get_icon(){
        if( $this->get_option('visa_logo') == 'yes' ){
            $this->icon[] = 'visa_card';
        }
        if( $this->get_option('master_card_logo') == 'yes' ){
            $this->icon[] = 'master_card';
        }
        if( $this->get_option('jcb_logo') == 'yes' ){
            $this->icon[] = 'jcb_card';
        }
        if( $this->get_option('ae_logo') == 'yes' ){
            $this->icon[] = 'ae_card';
        }
        if( $this->get_option('diners_logo') == 'yes' ){
            $this->icon[] = 'diners_card';
        }
        if( $this->get_option('discover_logo') == 'yes' ){
            $this->icon[] = 'discover_card';
        }

        $img = '';
        if( count($this->icon) > 0 ){
            foreach ($this->icon as $icon){
                $img .= '<img class="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/'.$icon.'.png" alt="'.$icon.'" />';
            }
        }

        return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
    }

	public function process_payment($order_id) {

        if( $this->is_inline == 'no' ){
            return parent::process_payment($order_id);
        }

        $order = new WC_Order ( $order_id );
        $customer = new Wc_Asiabill_Customer(get_current_user_id());

        $save_card = isset($_POST['wc-'.$this->id.'-new-payment-method']);
        $use_token = isset($_POST['wc-'.$this->id.'-payment-token']) && $_POST['wc-'.$this->id.'-payment-token'] != 'new';
        $new_card = isset($_POST['asiabill_payment']);


        $payment_method_id = '';
        // token id 支付
        if( $use_token ){
            $token_id = sanitize_text_field($_POST['wc-'.$this->id.'-payment-token']);
            $wc_token = WC_Payment_Tokens::get( $token_id );
            $wc_token->set_default('true');
            $payment_method_id = $wc_token->get_token();

            $payment_method = $customer->get_payment_method($this->id,$payment_method_id);

            $billing_address = $this->billing_address($order);
            if($billing_address !== $payment_method['billingDetail']){
                $response = $customer->update_payment_method($this->id,[
                    'billingDetail' => $billing_address,
                    'customerPaymentMethodId' => $payment_method_id
                ]);
                $this->logger->info('update payment method : '.json_encode($response));
            }

            $wc_token->save();
        }
        // 新卡号支付
        else if( $new_card ){
            $payment_method_id = sanitize_text_field($_POST['asiabill_payment']);
        }


        // 创建AsiaBill id
        if( $save_card && !$customer->has_customer() ){

            $user = new WP_User(get_current_user_id());

            $response = $this->api()->request('customers',['body'=>[
                'description' => 'wordpress customer',
                'email' => $user->user_email,
                'firstName' => $user->user_firstname,
                'lastName' => $user->user_lastname,
                'phone' => ''
            ]]);

            $this->logger->info('create customer response : '.json_encode($response));

            if( $response['code'] == '00000' ){
                $customer->create_customer($response['data']['customerId']);
            }
        }

        // 无效的method_id
        if( empty($payment_method_id) ){
            $this->logger->debug('payment method id is empty');
            throw new Exception(  __ ( 'Invalid payment method', 'asiabill' ) );
        }

        $parameter = $this->order_parameter($order);
        $parameter['customerPaymentMethodId'] = $payment_method_id;
        $parameter['customerId'] = $save_card ? $customer->get_id() : '';
        $parameter['shipping'] = [
            'address' => [
                'line1' => $order->get_shipping_address_1(),
                'line2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'country' => $order->get_shipping_country(),
                'state' => $order->get_shipping_state(),
                'postCode' => $order->get_shipping_postcode()
            ],
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_shipping_first_name(),
            'lastName' => $order->get_shipping_last_name(),
            'phone' => $order->get_shipping_phone(),
        ];


        $this->logger->info('confirm charge : '.json_encode($parameter));

        update_post_meta($order->get_id(), '_related_number', $parameter['orderNo']);

        $response = $this->api()->request('confirmCharge',['body' => $parameter]);

        $this->logger->info('confirm response : '.json_encode($response));

        if( is_array($response) && isset($response['code']) ){


            if( $response['code'] == '00000' ){

                if( $response['data']['orderStatus'] == 'success' ){
                    $order->add_meta_data('_asiabill_payment_method_id',$payment_method_id);
                    $order->add_meta_data('_asiabill_customer_id',$customer->get_id());
                    $order->save();
                }

                $this->process_response($response['data'],$order);

                if( !empty($response['data']['redirectUrl']) ){
                    // 3ds
                    return array (
                        'result' => 'success',
                        'redirect' =>  $response['data']['redirectUrl']
                    );
                }

                if($response['data']['orderStatus'] == 'fail'){
                    throw new Exception($response['data']['orderInfo']);
                }

                // 支付成功，关联客户卡
                if( $save_card && $new_card && $customer->has_customer() ){
                    $response = $this->api()->request('paymentMethods_attach',['path' => [
                        'customerPaymentMethodId' => $payment_method_id,
                        'customerId' => $customer->get_id()
                    ]]);
                    $this->logger->info('payment method attach response :'.json_encode($response));
                    // 清除客户缓存记录
                    $customer->clear_cache();
                }

                WC()->cart->empty_cart();

                return array (
                    'result' => 'success',
                    'redirect' => $order->get_checkout_order_received_url()
                );

            }
            else{
                throw new Exception($response['message']);
            }
        }
        else{
            $this->logger->error('request error:'.json_encode($response));
            return array (
                'result' => 'fail',
                'redirect' => $response
            );
        }

	}

    public function elements_form() {

        // 保存的卡
        if( is_user_logged_in() && is_checkout() && $this->get_option('save_cards') == 'yes' && $this->get_tokens() ){
            $this->saved_payment_methods();
        }

        echo '<div id="wc-'.esc_attr( $this->id ).'-cc-form" class="wc-credit-card-form wc-payment-form" style="">
        <div id="asiabill-card" class="wc-asiabill-elements-field">
            <div id="asiabill-card-element" class="ab-elemen"></div>
            <div id="asiabill-card-error" class="woocommerce-error hide" role="alert"></div>
        </div>
        </div>';

        // 保存的卡选项
        if( is_user_logged_in() && is_checkout() && $this->get_option('save_cards') == 'yes' ){
            $this->save_payment_method_checkbox();
        }

    }

    public function get_saved_payment_method_option_html( $token ) {

        $display = sprintf(
            __( '%1$s ending in %2$s', 'woocommerce' ),
            wc_get_credit_card_type_label( $token->get_card_type() ),
            $token->get_last4(),
        );

        $html = sprintf(
            '<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
            esc_attr( $this->id ),
            esc_attr( $token->get_id() ),
            esc_html( $display ),
            checked( $token->is_default(), true, false )
        );

        return apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this );
    }

    public function save_payment_method_checkbox() {
        printf(
            '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
            esc_attr( $this->id ),
            esc_html( apply_filters( 'save_card_text', __ ( 'Save Card.', 'asiabill' ) ) )
        );
    }

    public function payment_scripts(){

        if ( 'no' === $this->enabled || $this->is_inline != 'yes' ) {
            return;
        }

        wp_register_style( 'asiabill_styles', ASIABILL_PAYMENT_URL.'/assets/css/asiabilll.css', [], ASIABILL_OL_PAYMENT_VERSION );

        wp_enqueue_script('asiabill_payment', $this->api()->getJsScript(), [], ASIABILL_OL_PAYMENT_VERSION, true);

        wp_enqueue_script('asiabill_checkout', ASIABILL_PAYMENT_URL.'/assets/js/asiabill_checkout.js', ['jquery', 'jquery-payment','asiabill_payment' ], ASIABILL_OL_PAYMENT_VERSION, true);

        wp_localize_script(
            'asiabill_checkout',
            'wc_asiabill_params',
            apply_filters( 'wc_asiabill_params', $this->javascript_params() )
        );
        $this->tokenization_script();

    }

    public function javascript_params(){

        global $wp;

        $script_params = [
            'token' => $this->api()->request('sessionToken'),
            'checkoutPayPage' => ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no',
            'billing' => null,
            'layout' => [
                'pageMode' => $this->get_option('form_style'),// 页面风格模式  inner | block
                'style' => [
                    'frameMaxHeight' => $this->get_option('form_style') === 'inner' ? '47': '104', //  iframe最大高度
                    'input' => [
                        'FontSize' => '14', // 收集页面字体大小
                        'FontFamily' => '',  // 收集页面字体名称
                        'FontWeight' => '', // 收集页面字体粗细
                        'Color' => '', // 收集页面字体颜色
                        'ContainerBorder' => '1px solid #ddd;', // 收集页面字体边框
                        'ContainerBg' => '', // 收集页面字体粗细
                        'ContainerSh' => '' // 收集页面字体颜色
                    ]
                ],
            ],
        ];

		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {

            $order_id = wc_clean( $wp->query_vars['order-pay'] );
            $order    = wc_get_order( $order_id );

            if ( is_a( $order, 'WC_Order' ) ) {
                $script_params['billing'] = $this->billing_address($order);
            }

        }

        return $script_params;
    }

    protected function billing_address($order){
        return [
            'address' => [
                'city' => $order->get_billing_city(),
                'country' => $order->get_billing_country(),
                'line1' => $order->get_billing_address_1(),
                'line2' => $order->get_billing_address_2(),
                'postalCode' => $order->get_billing_postcode(),
                'state' => $order->get_billing_state()
            ],
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone()

        ];
    }

}

?>

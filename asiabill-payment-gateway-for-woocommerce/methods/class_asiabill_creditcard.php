<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Creditcard extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'Asiabill Credit Card Payment';
    var $method_description = 'Credit Card 信用卡支付';
    var $logger;
    var $is_inline;
    var $test;

	public function __construct() {

	    parent::__construct('wc_asiabill_creditcard');

        $this->is_inline = $this->get_option('inline')?:'no';
        $this->has_fields = $this->is_inline == 'yes'?true:false;
        $this->test = $this->get_option('use_test_model');
        $this->supports = [
            'products',
        ];

        if( $this->is_inline == 'yes' ){
            $this->supports[] = 'tokenization';
        }

        if( is_checkout() ){
            add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
        }

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Credit Card';

        $this->form_fields['inline'] = array(
            'title' => __ ( 'Inline Credit Card Form', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
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
                $img .= '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/'.$icon.'.png" alt="'.$icon.'" />';
            }
        }

        return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
    }

	public function process_payment($order_id) {

        if( $this->is_inline == 'no' ){
            return parent::process_payment($order_id);
        }

        $order = new WC_Order ( $order_id );
        $gateway = new Wc_Asiabill_Gateway($order,$this->id);
        $customer = new Wc_Asiabill_Customer(get_current_user_id());

        $save_card = isset($_POST['wc-'.$this->id.'-new-payment-method'])? true: false;
        $use_token = ( isset($_POST['wc-'.$this->id.'-payment-token']) && $_POST['wc-'.$this->id.'-payment-token'] != 'new' )? true: false;
        $new_card = isset($_POST['asiabill_payment'])? true: false;

        // 创建Asiabill客服id
        if( $save_card && !$customer->has_customer() ){
            $request_data = $gateway->get_billing();
            $request_data['description'] = '';
            unset($request_data['address']);
            // 获取接口api
            $api = Wc_Asiabill_Api::get_api_v3($this->test,'customers');
            $response = Wc_Asiabill_Api::request($request_data,$api,'POST',true);
            $this->logger->info('create customer: '.json_encode($response));
            if( $response['code'] == '0' ){
                $customer->create_customer($response['data']['customerId']);
            }else{
                $this->logger->debug('create customer error: '.$response['message']);
                throw new Exception($response['message']);
            }
        }

        $payment_method_id = '';
        // token id 支付
        if( $use_token ){
            $token_id = sanitize_text_field($_POST['wc-'.$this->id.'-payment-token']);
            $wc_token = WC_Payment_Tokens::get( $token_id );
            $wc_token->set_default('true');
            $payment_method_id = $wc_token->get_token();
            $gateway->check_token_method($payment_method_id);
            $wc_token->save();
        }
        // 新卡号支付
        else if( $new_card ){
            $payment_method_id = sanitize_text_field($_POST['asiabill_payment']);
        }

        // 无效的method_id
        if( empty($payment_method_id) ){
            $this->logger->debug('payment method id is empty');
            throw new Exception(  __ ( 'Invalid payment method', 'asiabill' ) );
        }

        // 确认扣款
        $response = $gateway->confirm_charge($payment_method_id);

        // 重新获取token
        $this->save_token($gateway);

        if( is_array($response) && isset($response['code']) ){
            if( $response['code'] == '0' ){

                // 3D交易
                if( $response['data']['threeDsType'] == '1' &&  $response['data']['threeDsUrl'] != null ){
                    $this->logger->info('redirect to 3d page');
                    $redirect = $response['data']['threeDsUrl'];
                }
                // 成功，待处理
                elseif( in_array($response['data']['orderStatus'],['1','-1','-2']) ) {
                    $this->logger->info('redirect to thank you page');
                    $redirect = $this->get_return_url( $order );
                }
                else{
                    $this->logger->debug('error info ：'.$response['data']['orderInfo']);
                    throw new Exception($response['data']['orderInfo']);
                }

                // 支付成功，关联客户卡
                if( $save_card && $new_card && $customer->has_customer() ){
                    $api = Wc_Asiabill_Api::get_api_v3($this->test,'payment_methods/'.$payment_method_id.'/'.$customer->get_id().'/attach');
                    $response = Wc_Asiabill_Api::request([],$api,'GET',true);
                    $this->logger->info('attach method:'.json_encode($response));
                    // 清除客户缓存记录
                    $customer->clear_cache();
                }

                if( $redirect == $this->get_return_url( $order ) ){
                    if ( isset( WC()->cart ) ) {
                        WC()->cart->empty_cart();
                    }
                }

                return array (
                    'result' => 'success',
                    'redirect' =>  $redirect
                );

            }
            else{
                $this->logger->debug('confirm error:'.$response['message']);
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

        $gateway = new Wc_Asiabill_Gateway('',$this->id);
        $session_token = $this->save_token($gateway);

        if( $session_token ){

            // 保存的卡
            if( is_user_logged_in() && is_checkout() && $this->get_option('save_cards') == 'yes' ){
                $this->saved_payment_methods();
            }

            echo '<div id="wc-'.esc_attr( $this->id ).'-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;margin-top:1em">
            <div id="asiabill-card-element" class="wc-asiabill-elements-field">
                <div id="card-element" class="ab-elemen" style="max-height: 44px;margin: 5px 0;"></div>
                <div id="card-error" class="woocommerce-error hide" role="alert"></div>
            </div>
           
            <script>if(typeof initAsiabillPaymentSdk != "undefined"){initAsiabillPaymentSdk();}</script>
            </div>';

            // 保存的卡选项
            if( is_user_logged_in() && is_checkout() && $this->get_option('save_cards') == 'yes' ){
                $this->save_payment_method_checkbox();
            }

        }

    }

    public function elements_error($info = ''){
        echo '<div class="woocommerce-error">'. esc_html($info) .'</div>';
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

    public function save_token($gateway){
        $response = $gateway->get_token();
        if( is_array($response) &&  isset($response['code']) ){ /*获取sessionToken成功*/
            if( $response['code'] == '0' ){
                setcookie('AsiabillSessionToken',esc_html($response['data']['sessionToken']),(time()+60*25),'/');
                return $response['data']['sessionToken'];
            }else{
                $this->elements_error($response['code'].' '.$response['message']); /*错误信息*/
            }
        }else{
            $this->elements_error($response); /*接口请求失败*/
        }
        return null;

    }

    public function payment_scripts(){

        if ( 'no' === $this->enabled ) {
            return;
        }

        wp_register_style( 'asiabill_styles', ASIABILL_PAYMENT_URL.'/assets/css/asiabilll.css', [], ASIABILL_OL_PAYMENT_VERSION );

        $src = ($this->test == 'yes'?Wc_Asiabill_Api::SANDBOX:Wc_Asiabill_Api::DOMAIN).'/static/v3/js/AsiabillPayment.min.js';
        wp_enqueue_script('asiabill_payment', $src, [], ASIABILL_OL_PAYMENT_VERSION, true);

        wp_enqueue_script('asiabill_checkout', ASIABILL_PAYMENT_URL.'/assets/js/asiabill_checkout.min.js', ['jquery', 'jquery-payment','asiabill_payment' ], ASIABILL_OL_PAYMENT_VERSION, true);

        wp_localize_script(
            'asiabill_checkout',
            'wc_asiabill_params',
            apply_filters( 'wc_asiabill_params', $this->javascript_params() )
        );
        $this->tokenization_script();

    }

    public function javascript_params(){

        $script_params = [
            'merNo' => $this->get_option('merchant_no'),
            'gatewayNo' => $this->get_option('gateway_no'),
            'mode' => $this->test == 'yes'? 'uat': 'pro',
            'layout' => [
                'pageMode' => 'inner',// 页面风格模式  inner | block
                'style' => [
                    'frameMaxHeight' => '44', //  iframe最大高度
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

        return $script_params;
    }

    public function asiabill_callback(){

        if( $this->is_inline == 'no' || isset($_POST['notifyType']) ){
            parent::asiabill_callback();
        }

        if ( ! isset( $_SERVER['REQUEST_METHOD'] )
            || ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
            || ! isset( $_GET['wc-api'] )
            || ( $this->id.'_callback' !== $_GET['wc-api'] )
        ) {
            echo 'success';
            die();
        }

        $input = @file_get_contents('php://input');

        $this->logger->debug('Incoming callback json : ' . $input );

        $result_data = json_decode($input,true);

        if( !isset($result_data['merNo'])
            || !isset($result_data['gatewayNo'])
            || $this->get_option('merchant_no') != $result_data['merNo']
            || $this->get_option('gateway_no') != $result_data['gatewayNo']
        ){
            echo 'success';
            die();
        }

        $gateway = new Wc_Asiabill_Gateway('',$this->id);
        $signInfo = $result_data['signInfo'];
        unset($result_data['signInfo']);

        if( strtoupper($signInfo) === $gateway->get_v3_sign($result_data) ){
            $order_status = $this->get_order_status(esc_attr($result_data['orderStatus']));
            if( $order_status ){
                $this->change_order_status($result_data,$order_status);
            }
        }
        echo 'success';
        exit();

    }

}

?>
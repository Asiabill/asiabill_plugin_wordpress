<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class WC_Asiabill_Payment_Gateway extends WC_Payment_Gateway_CC {

    public $icon = array();
    protected $logger;
    protected $confirm_status = ['processing', 'completed', 'refunded', 'shipping','shipped','close'];

    function __construct($id){

        $this->id = esc_attr($id);
        $this->logger = new Wc_Asiabill_logger($id);
        $this->title = $this->get_option ( 'title' );
        $this->description = $this->get_option ( 'description' );

        // 支付页面
        add_action( 'woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));

        // 保存设置
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array ($this,'process_admin_options') );
        add_action( 'woocommerce_api_'.$this->id.'_callback', array( $this, 'asiabill_callback' ) );

        // 加载表单字段
        $this->init_form_fields ();
        // 加载设置
        $this->init_settings ();

    }

    public function admin_options() {

        wp_enqueue_script('ab_admin', ASIABILL_PAYMENT_URL.'/assets/js/ab_admin.js', ['jquery'], MONEYCOLLECT_VERSION, true);
        wp_localize_script(
            'ab_admin',
            'ab_admin_params',
            apply_filters( 'ab_admin_params', ['id' => $this->id] ));
        $this->tokenization_script();
        parent::admin_options();
    }

    /**
     * 配置参数
     */
    function init_form_fields(){
        $this->form_fields = [
            'enabled' => [
                'title' => __ ( 'Enable/Disable', 'asiabill' ),
                'type' => 'checkbox',
                'label' => __ ( 'Enable Payment', 'asiabill' ),
                'default' => 'no'
            ],
            'title' =>[
                'title' => __ ( 'Payment Method', 'asiabill' ),
                'type' => 'text',
                'description' => __ ( 'This controls the title which the user sees during checkout.', 'asiabill' ),
                'default' => '',
                'css' => 'width:400px'
            ],
            'use_test_mode' => [
                'title' => __ ( 'Use Test Mode', 'asiabill' ),
                'type' => 'checkbox',
                'label' => '使用测试模式',
                'default' => 'no',
            ],
            'gateway_no' => [
                'title' => __ ( 'Gateway No', 'asiabill' ),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width:400px'
            ],
            'signkey_code' => [
                'title' => __ ( 'Signkey', 'asiabill' ),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width:400px'
            ],
            'test_gateway_no' => [
                'title' => __ ( 'Test Gateway No', 'asiabill' ),
                'type' => 'text',
                'description' => '',
                'default' => '12246002',
                'css' => 'width:400px'
            ],
            'test_signkey_code' => [
                'title' => __ ( 'Test Signkey', 'asiabill' ),
                'type' => 'text',
                'description' => '',
                'default' => '12H4567r',
                'css' => 'width:400px'
            ],
            'description' => [
                'title' => __ ( 'Description', 'asiabill' ),
                'type' => 'textarea',
                'description' => __ ( 'This controls the description which the user sees during checkout.', 'asiabill' ),
                'default' => '',
                //'desc_tip' => true ,
                'css' => 'width:400px'
            ],
            'debug_log' => [
                'title' => __ ( 'Debug Log', 'asiabill' ),
                'type' => 'checkbox',
                'label' => __ ( 'Log Debug Messages', 'asiabill'),
                'description' => __ ( 'Debug Path:', 'asiabill' ).'<code>'.wp_upload_dir()['path'].'/wc-logs/woocommerce-gateway-asiabill-*</code>',
                'default' => 'no',
            ]
        ];
    }

    /**
     * 支付字段描述
     */
    function payment_fields(){
        echo apply_filters( 'wc_'.$this->id.'_description', wpautop( wp_kses_post( $this->description ) ), $this->id );
    }

    /**
     * 支付请求响应
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id){

        $order = new WC_Order ( $order_id );

        $parameter = $this->order_parameter($order);

        $parameter['deliveryAddress'] = array(
            'address' => $order->get_shipping_address_1().' '.$order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'country' => $order->get_shipping_country(),
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_shipping_first_name(),
            'lastName' => $order->get_shipping_last_name(),
            'phone' => $order->get_shipping_phone(),
            'state' => $order->get_shipping_state(),
            'zip' => $order->get_shipping_postcode()
        );

        $parameter['billingAddress'] = array(
            'address' => $order->get_billing_address_1().' '.$order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'country' => $order->get_billing_country(),
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode()
        );

        $this->logger->info('checkoutPayment : '.json_encode($parameter));
        $res = $this->api()->request('checkoutPayment',array('body' => $parameter));

        if( $res['code'] == '0000'){
            return [
                'result' => 'success',
                'redirect' => $res['data']['redirectUrl']
            ];
        }else{
            throw new Exception($res['message']);
        }

    }

    /**
     * 异步回调处理
     * @return void
     */
    function asiabill_callback(){

        if ( ! isset( $_SERVER['REQUEST_METHOD'] )
            || ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
            || ! isset( $_GET['wc-api'] )
            || ( $this->id.'_callback' !== $_GET['wc-api'] )
        ) {
            echo 'success';
            die();
        }

        $asiabill_api = $this->api();

        $webhook_data = $asiabill_api->getWebhookData();

        if( !preg_match("/^transaction|pre-authorized/",$webhook_data['type']) ){
            echo 'success';
            die();
        }

        $this->logger->info('webhook: ' . json_encode( $webhook_data) );

        if( $asiabill_api->verification() ){
            $this->change_order_status($webhook_data['data']);
        }

        echo 'success';
        die();

    }

    protected function change_order_status($result_data){

        $order_id = $result_data['orderNo'] ;
        $order = wc_get_order( $order_id );

        if( ! is_object($order) ){
            throw new Exception('order is not object !');
        }

        $get_status = $result_data['tradeStatus'] ?? $result_data['orderStatus'];

        switch ( $get_status ){
            case 'success':
                $order_status = 'processing';
                break;
            case 'pending':
                $order_status = 'on-hold';
                break;
            case 'fail':
                $order_status = 'failed';
                break;
            default:
                $order_status = 'pending';
                break;
        }

        if( ! $order->has_status( $this->confirm_status ) ){

            $note = '<b>Transaction No</b> : '.$result_data['tradeNo'] ."<br/>";
            $note .= '<b>Status</b> : '.$order_status ."<br/>";
            $note .= '<b>Order Info</b> : '.$result_data['orderInfo'] ;

            $order->add_order_note($note."\r\n");

            // 交易号
            update_post_meta($order->get_id(), 'tradeNo', $result_data['tradeNo']);

            // 更新订单
            $order->update_status($order_status);

            $this->logger->info('change order status: '.$order_status);

            if( $order_status == 'processing' ){
                $order->payment_complete(  $result_data['tradeNo']  );
            }

        }

    }

    protected function sanitize_post(){
        $data = [];
        if($_SERVER['REQUEST_METHOD'] == 'GET'){
            $data['signInfo'] = wc_clean($_GET['signInfo']);
            $data['orderNo'] = wc_clean($_GET['orderNo']);
            $data['orderAmount'] = wc_clean($_GET['orderAmount']);
            $data['code'] = wc_clean($_GET['code']);
            $data['merNo'] = wc_clean($_GET['merNo']);
            $data['gatewayNo'] = wc_clean($_GET['gatewayNo']);
            $data['tradeNo'] = wc_clean($_GET['tradeNo']);
            $data['orderCurrency'] = wc_clean($_GET['orderCurrency']);
            $data['orderInfo'] = wc_clean($_GET['orderInfo']);
            $data['orderStatus'] = wc_clean($_GET['orderStatus']);
            $data['message'] = wc_clean($_GET['message']);
        }
        return $data;
    }

    protected function order_parameter($order){


        $order_line  = (array)$order->get_data()['line_items'];

        $goods_details = array();
        foreach ($order_line as $item) {

            $data = array_values((array)$item);
            $product = wc_get_product( $data[1]['product_id'] );

            if( empty($product) ){
                continue;
            }

            $goods_details[] = [
                'goodsTitle' => $product->get_name(),
                'goodsCount' => $data['1']['quantity'],
                'goodsPrice' => $data['1']['quantity'] > 0 ? sprintf('%.2f', $data['1']['subtotal'] / $data['1']['quantity']) : 0
            ];
        }


        $customer_ip = $order->get_customer_ip_address();
        if( empty($customer_ip) ){
            $customer_ip = \Asiabill\Classes\AsiabillIntegration::clientIP();
        }

        return array(
            'customerId' => '',
            'customerIp' => $customer_ip,
            'orderNo' => $order->get_id(),
            'orderAmount' => sprintf('%.2f', $order->get_total()),
            'orderCurrency' => $order->get_currency(),
            'goodsDetails' => $goods_details,
            'paymentMethod' => ASIABILL_METHODS[str_replace('wc_','',$this->id)],
            'returnUrl' => $order->get_checkout_order_received_url(),
            'callbackUrl' => get_site_url().'/?wc-api='.$this->id.'_callback' ,
            'isMobile' => \Asiabill\Classes\AsiabillIntegration::isMobile(),
            'platform' => 'wordpress',
            'remark' => '', // 订单备注信息
            'webSite' => get_site_url()
        );


    }

    protected function api(){
        return Wc_Asiabill_Api::load_asiabill($this->id);
    }

}
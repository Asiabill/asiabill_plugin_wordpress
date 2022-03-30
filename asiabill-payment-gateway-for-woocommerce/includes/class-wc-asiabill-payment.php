<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class WC_Asiabill_Payment_Gateway extends WC_Payment_Gateway_CC {

    var $id;
    var $logger;

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
            'use_test_model' => [
                'title' => __ ( 'Use Test Model', 'asiabill' ),
                'type' => 'checkbox',
                'label' => '使用测试模式',
                'default' => 'no',
            ],
            'merchant_no' => [
                'title' => __ ( 'Mer No', 'asiabill' ),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width:400px'
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
            'test_merchant_no' => [
                'title' => __ ( 'Test Mer No', 'asiabill' ),
                'type' => 'text',
                'description' => '',
                'default' => '12246',
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
                'description' => __ ( 'Debug Path:', 'asiabill' ).'<code>'.wp_upload_dir().'/wc-logs/woocommerce-gateway-asiabill-*</code>',
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
        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url ( true )
        ];
    }


    /**
     * 支付重定向
     * @param $order_id
     */
    function receipt_page($order_id){

        $order = new WC_Order($order_id);

        if( !$order || !$order->needs_payment() ){
            wp_redirect($this->get_return_url());
            die();
        }

        $gateway = new Wc_Asiabill_Gateway($order,$this->id);
        $gateway->pay_method();
    }

    function asiabill_callback(){

        if ( ! isset( $_SERVER['REQUEST_METHOD'] )
            || ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
            || ! isset( $_GET['wc-api'] )
            || ( $this->id.'_callback' !== $_GET['wc-api'] )
            || !isset($_POST['merNo'])
            || !isset($_POST['gatewayNo'])
            || !isset($_POST['notifyType'])
        ) {
            echo 'success';
            die();
        }

        if( !in_array($_POST['notifyType'],['PaymentResult','OrderStatusChanged','Void','Capture']) ){
            echo 'success';
            die();
        }

        if( $this->get_option('merchant_no') != $_POST['merNo']
            || $this->get_option('gateway_no') != $_POST['gatewayNo']
        ){
            echo 'success';
            die();
        }

        try{

            $result_data = $this->sanitize_post();

            $this->logger->info('Incoming callback post : ' . json_encode($result_data));

            $gateway = new Wc_Asiabill_Gateway('',$this->id);

            if( in_array($_POST['notifyType'],['Void','Capture']) ){
                $validation_sign = $gateway->get_notify_sign($result_data);
            }else{
                $validation_sign = $gateway->get_result_sign($result_data);
            }

            if( isset($_POST['signInfo']) && strtoupper($_POST['signInfo']) === $validation_sign ){
                $code = $result_data['orderStatus'];
                $order_status = $this->get_order_status($code);
                if( $order_status ){
                    $this->change_order_status($result_data,$order_status);
                }
            }
            else{
                $this->logger->debug('Incoming callback failed validation: '.$validation_sign);
            }

        }catch (\Exception $e){
            $this->logger->error($e->getMessage());
        }

        echo 'success';
        die();

    }

    function get_order_status($code){
        $order_status = false;
        switch ( $code ){
            case 1:
                $order_status = 'processing';
                break;
            case -1:
            case -2:
                $order_status = 'on-hold';
                break;
            case 0:
                if( substr($_POST['orderInfo'],0,5) == 'I0061' ){
                    // 重复支付订单
                    $order_status = 'processing';
                }else{
                    $order_status = 'failed';
                }
                break;
        }
        return $order_status;
    }

    function change_order_status($result_data,$order_state){

        $order_id = $result_data['orderNo'] ;
        $order = wc_get_order( $order_id );

        if( ! is_object($order) ){
            throw new Exception('order is not object !');
        }

        if( ! $order->has_status( [ 'processing', 'completed', 'refunded' ] ) ){

            $note = 'tradeNo:'.$result_data['tradeNo'].';orderInfo:'.$result_data['orderInfo'];

            $order->add_order_note($note."\r\n");

            // 交易号
            update_post_meta($order->get_id(), 'tradeNo', $result_data['tradeNo']);

            // 更新订单
            $order->update_status($order_state);

            $this->logger->debug('change order status: '.$order_state);

            if( $order_state == 'processing' ){
                $order->payment_complete(  $result_data['tradeNo']  );
            }

        }

    }

    function sanitize_post(){
        $data = [];
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            foreach ($_POST as $key => $value){
                $data[$key] = wc_clean($value);
            }
        }
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

}
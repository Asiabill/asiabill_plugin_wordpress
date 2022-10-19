<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class WC_Asiabill_Payment_Gateway extends WC_Payment_Gateway_CC {

    public $icon = array();
    protected $logger;
    protected $confirm_status = ['processing', 'completed', 'refunded', 'shipping','shipped','close','wfocu-pri-order'];

    function __construct($id){

        $this->id = esc_attr($id);
        $this->logger = new Wc_Asiabill_logger($id);
        $this->title = $this->get_option ( 'title' );
        $this->description = $this->get_option ( 'description' );

        $this->supports = [
            'products',
            'refunds'
        ];

        // 支付页面
        add_action( 'woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));

        // 保存设置
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array ($this,'process_admin_options') );

        // 加载表单字段
        $this->init_form_fields ();
        // 加载设置
        $this->init_settings ();

    }

    public function admin_options() {

        wp_enqueue_script('ab_admin', ASIABILL_PAYMENT_URL.'/assets/js/ab_admin.js', ['jquery'], ASIABILL_OL_PAYMENT_VERSION, true);
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
     * icon
     * @return mixed|string|void
     */
    public function get_icon(){
        if( $this->get_option('show_logo') == 'yes' ){
            $file = str_replace('wc_asiabill_','',$this->id);
            $img = '<img class="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/'. $file .'.png" alt="'. $file .'"/>';
            return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
        }
    }

    /**
     * 支付字段描述
     */
    function payment_fields(){
        if( $this->get_option('show_logo') == 'yes' ){
            $img = '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/alipay.png" alt="alipay"/>';
            echo  apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
        }

        echo apply_filters( 'wc_'.$this->id.'_description', wpautop( wp_kses_post( $this->description ) ), $this->id );
    }

    /**
     * 支付请求响应
     * @param int $order_id
     * @return array
     * @throws Exception
     */
    function process_payment($order_id){

        $order = wc_get_order( $order_id );

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

        update_post_meta($order->get_id(), '_related_number', $parameter['orderNo']);

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

    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $order = wc_get_order( $order_id );

        if ( ! $order )  return false;

        if( $amount <= 0 ) throw new Exception( __( 'There was a problem initiating a refund: This value must be greater than 0.' ) );

        $refund_items = wc_get_orders( array(
            'type'   => 'shop_order_refund',
            'parent' => $order_id,
            'limit'  => -1,
            'return' => 'ids',
        ) );

        $refund_id = current($refund_items);

        $refund_type = $amount == $order->get_total() ? '1' : '2';

        $refund_data = [
            'merTrackNo' => $refund_id,
            'refundAmount' => $amount,
            'refundReason' => $reason,
            'refundType' => $refund_type,
            'remark' => 'Order #'.$order->get_order_number(),
            'tradeNo' => $order->get_transaction_id()
        ];

        $this->logger->info('refund request : '.json_encode($refund_data));

        $res = $this->api()->openapi()->request('refund',['body' => $refund_data]);

        $this->logger->info('refund result : '.json_encode($res));

        if($res['code'] != '0000'){
            throw new Exception( __( $res['message'] ) );
        }

        $res['data']['amount'] = $amount;
        $this->add_refund_note($res['data'],$order);

        return true;
    }

    public function process_response($result_data,$order){

        $get_status = $result_data['orderStatus'];

        if( !$order->get_transaction_id() ){

            if( $order->get_meta('_trade_no') !== $result_data['tradeNo']
                || ( $order->get_status() === 'on-hold' && $get_status !== 'pending' )
            ){

                $order->add_meta_data('_trade_no',$result_data['tradeNo'],true);

                if( $get_status === 'success' ){
                    $order->payment_complete(  $result_data['tradeNo'] );
                    /* translators: transaction id */
                    $message = sprintf( __( 'AsiaBill payment complete (Ref No: %s)', 'asiabill' ), $result_data['tradeNo'] );
                    $order->add_order_note( $message );
                }elseif( $get_status === 'pending' ){
                    $order->update_status( 'on-hold', sprintf( __( 'AsiaBill payment awaiting : %s.', 'asiabill' ), $result_data['tradeNo'] ) );
                }elseif( $get_status === 'fail' ){
                    $localized_message = __( 'AsiaBill payment failed (Ref No: '. $result_data['tradeNo'] .'. Reason: '.$result_data['orderInfo'].')', 'asiabill' );
                    if( $order->get_status() === 'on-hold' ){
                        $order->update_status('pending',$localized_message );
                    }else{
                        $order->add_order_note( $localized_message );
                    }

                }

            }

            $order->save();
        }

    }

    protected function get_order_by_number($number){
        global $wpdb;

        if ( empty( $number ) ) {
            return false;
        }

        $order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $number, '_related_number' ) );

        if ( ! empty( $order_id ) ) {
            return wc_get_order( $order_id );
        }

        return false;

    }

    protected function add_refund_note($result_data,$order){

        $refund_message = 'Refund ID: <b>'.$result_data['batchNo'].'</b><br>';
        $refund_message .= 'Amout: <b>'.$result_data['amount'].'</b><br>';
        $refund_message .= 'Status: <b>'.$result_data['refundStatus'].'</b>';

        $order->add_order_note( $refund_message );
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
            'orderNo' => $order->get_order_number(),
            'orderAmount' => sprintf('%.2f', $order->get_total()),
            'orderCurrency' => $order->get_currency(),
            'goodsDetails' => $goods_details,
            'paymentMethod' => ASIABILL_METHODS[str_replace('wc_','',$this->id)],
            'returnUrl' => $order->get_checkout_order_received_url(),
            'callbackUrl' => get_site_url().'/?wc-api=asiabill_callback' ,
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
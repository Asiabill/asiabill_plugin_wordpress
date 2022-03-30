<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wc_Asiabill_Gateway
{
    var $order;
    var $gateway_id;
    private $return_url;
    private $callback_url;
    private $settings;
    private $logger;
    private $test = '';

    function __construct($order,$gateway_id) {
        $this->order = $order;
        $this->gateway_id = $gateway_id;
        if ( $order ) {
            $this->return_url = $order->get_checkout_order_received_url();
        } else {
            $this->return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
        }
        $this->callback_url = get_site_url().'/?wc-api='.$gateway_id.'_callback';
        $this->settings = get_option( 'woocommerce_'.$gateway_id.'_settings' );
        $this->logger = new Wc_Asiabill_logger($gateway_id);

        if( $this->settings['use_test_model'] === 'yes' ){
            $this->test = 'test_';
        }

    }

    function pay_method(){

        $gateway_info = $this->get_gateway();

        $order_info = $this->get_order();
        unset($order_info['goodsDetail']);

        $billing_info = $this->get_billing();
        $billing_info['country'] = $billing_info['address']['country'];
        $billing_info['state'] = $billing_info['address']['state'];
        $billing_info['city'] = $billing_info['address']['city'];
        $billing_info['zip'] = $billing_info['address']['postalCode'];
        $billing_info['address'] = $billing_info['address']['line1'].' '.$billing_info['address']['line2'];

        $shipping_info = $this->get_shipping();
        $shipping = [];
        $shipping['shipFirstName'] = $shipping_info['firstName'];
        $shipping['shipLastName'] = $shipping_info['lastName'];
        $shipping['shipPhone'] = $shipping_info['phone'];
        $shipping['shipCountry'] = $shipping_info['address']['country'];
        $shipping['shipState'] = $shipping_info['address']['state'];
        $shipping['shipCity '] = $shipping_info['address']['city'];
        $shipping['shipAddress'] = $shipping_info['address']['line1'].' '.$shipping_info['address']['line2'];
        $shipping['shipZip'] = $shipping_info['address']['postalCode'];

        $parameter = array_merge($gateway_info,$order_info,$billing_info,$shipping);

        $parameter['paymentMethod'] = $this->get_payment_method();
        $parameter['returnUrl'] = $this->return_url;
        $parameter['callbackUrl'] = $this->callback_url;
        $parameter['interfaceInfo'] = 'wordpress';
        $parameter['isMobile'] = $this->is_mobile();
        $parameter['signInfo'] = $this->get_v2_sign($parameter);
        $parameter['remark'] = '';

        $this->logger->debug($this->get_payment_method().' test['.$this->settings['use_test_model'].']: '.json_encode($parameter));

        Wc_Asiabill_Api::form($parameter,$this->settings['use_test_model']);

    }

    function check_token_method($payment_method_id){
        $customer = new Wc_Asiabill_Customer(get_current_user_id());
        $method = $customer->get_payment_method($this->gateway_id,$payment_method_id);
        unset($method['created']);
        $new_method = [
            'customerPaymentMethodId' => $payment_method_id,
            'billingDetail' => $this->get_billing(),
            'card' => $method['card'],
            'customerId' => $method['customerId']
        ];
        if( !($method == $new_method) ){
            $response = $customer->update_payment_method($this->gateway_id,$new_method);
            $this->logger->info('update payment method result : '.json_encode($response));
            if( $response['code'] != '0' ){
                $this->logger->debug('update error : '.$response['message']);
            }
        }

    }

    function confirm_charge($payment_method_id){

        $gateway_info = $this->get_gateway();

        $order_info = $this->get_order();
        unset($order_info['goods_detail']);

        $shipping_info = $this->get_shipping();

        $parameter = array_merge($gateway_info,$order_info);

        $parameter['customerPaymentMethodId'] = $payment_method_id;
        $parameter['shipping'] = $shipping_info;
        $parameter['ip'] = $this->get_ip();
        $parameter['returnUrl'] = $this->return_url;
        $parameter['callbackUrl'] = $this->callback_url;
        $parameter['platform'] = 'wordpress';
        $parameter['isMobile'] = $this->is_mobile();
        $parameter['tradeType'] = 'web';
        $parameter['webSite'] = get_site_url();
        $parameter['signInfo'] = $this->get_confirm_sign($parameter);

        $this->logger->info('confirm charge test['.$this->settings['use_test_model'].']: '.json_encode($parameter));

        $api = Wc_Asiabill_Api::get_api_v3($this->settings['use_test_model'],'confirmCharge');
        $response = Wc_Asiabill_Api::request($parameter,$api,'POST',true);
        $this->logger->info('confirm charge api: '.$api);
        $this->logger->info('confirm charge result: '.json_encode($response));

        return $response;
    }

    function get_gateway(){
        $settings = $this->settings;
        return [
            'merNo' => trim($settings[$this->test.'merchant_no']),
            'gatewayNo' => trim($settings[$this->test.'gateway_no']),
        ];
    }

    function get_order(){

        $order = $this->order;
        $order_data   = (array)$order->get_data()['line_items'];

        $product_data = [];
        $goods_detail = [];

        $count = 0;
        foreach ($order_data as $key => $val) {
            if ($count == 10) break;
            $val   = array_values((array)$val);
            $name  = $val['1']['name'];
            $price = $val['1']['quantity'] > 0 ? sprintf('%.2f', $val['1']['subtotal'] / $val['1']['quantity']) : 0;
            if (strlen($val['1']['name']) > 130) {
                $name = substr($val['1']['name'], 0, 130);
            }
            $product_data[] = [
                'productName' => htmlspecialchars($name,ENT_QUOTES),
                'quantity'    => $val['1']['quantity'],
                'price'       => $price
            ];
            $goods_detail[] = [
                'goodstitle' => htmlspecialchars($name,ENT_QUOTES),
                'goodscount' => $val['1']['quantity'],
                'goodsprice' => $price
            ];

            $count++;
        }

        return [
            'orderNo' =>  $order->get_id(),
            'orderCurrency' => $order->get_currency(),
            'orderAmount' => round ($order->get_total(), 2 ),
            'goods_detail' => htmlspecialchars(json_encode($product_data)),
            'goodsDetail' => $goods_detail
        ];


    }

    function get_billing(){
        $order = $this->order;
        return [
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'address' => [
                'country' => $order->get_billing_country(),
                'state'=> $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'line1' => $order->get_billing_address_1(),
                'line2' => $order->get_billing_address_2(),
                'postalCode' => $order->get_billing_postcode()
            ]
        ];
    }

    function get_shipping(){
        $order = $this->order;
        return [
            'firstName' => $order->get_shipping_first_name(),
            'lastName' => $order->get_shipping_last_name(),
            'phone' => $order->get_billing_phone(),
            'address' => [
                'country' => $order->get_shipping_country(),
                'state'=> $order->get_shipping_state(),
                'city' => $order->get_shipping_city(),
                'line1' => $order->get_shipping_address_1(),
                'line2' => $order->get_shipping_address_2(),
                'postalCode' => $order->get_shipping_postcode()
            ]
        ];
    }

    function get_v2_sign($data){
        $string = $data['merNo'] . $data['gatewayNo'] . $data['orderNo'] . $data['orderCurrency'] . $data['orderAmount'] . htmlspecialchars($data['returnUrl']);
        return self::sign_info($string);
    }

    function get_v3_sign($data,$sign=true){

        // 排序
        ksort($data);

        $string = '';
        foreach ($data as $k => $value){
            if( is_array($value) ){
                $value = $this->get_v3_sign($value,false);
            }
            if( $value !== '' && $value !== null && $value !== false ){
                // 拼接参数,参与加密的字符转为小写
                $str = trim($value);
                $string .= $str;
            }
        }
        if( !$sign ){
            return $string;
        }
        $signInfo = $this->sign_info($string,true);
        return $signInfo;
    }

    function get_result_sign($data){
        $string = $data['merNo'] . $data['gatewayNo'] . $data['tradeNo'] . $data['orderNo'] . $data['orderCurrency'] . $data['orderAmount'] . $data['orderStatus'] . $data['orderInfo'];
        return self::sign_info($string);
    }

    function get_notify_sign($data){
        $string = $data['notifyType'] . $data['operationResult'] .  $data['merNo'] . $data['gatewayNo'] . $data['tradeNo'] . $data['orderNo'] . $data['orderCurrency'] . $data['orderAmount'] . $data['orderStatus'] ;
        return self::sign_info($string);
    }

    function get_confirm_sign($data){
        $string = $data['merNo'] . $data['gatewayNo'] . $data['orderNo'] . $data['orderCurrency'] . $data['orderAmount'] .  $data['customerPaymentMethodId'] ;
        return self::sign_info($string,true);
    }

    function get_query_sign($data){
        $string = $data['merNo'] . $data['gatewayNo'];
        return self::sign_info(strtolower($string));
    }

    function get_token(){

        $request = [
            'merNo' => $this->settings[$this->test.'merchant_no'],
            'gatewayNo' => $this->settings[$this->test.'gateway_no'],
        ];
        $request['signInfo'] = $this->get_v3_sign($request);
        $api = Wc_Asiabill_Api::get_api_v3($this->settings['use_test_model'],'sessionToken');
        return Wc_Asiabill_Api::request($request,$api,'POST');
    }

    private function get_payment_method(){
        $key = str_replace('wc_','',$this->gateway_id);
        return ASIABILL_METHODS[$key];
    }

    private function sign_info($string,$lower = false){
        if( $lower ){
            $sign_info = strtoupper(hash("sha256" , strtolower($string.$this->settings[$this->test.'signkey_code'] )) );
        }else{
            $sign_info = strtoupper(hash("sha256" , $string.$this->settings[$this->test.'signkey_code'] ));
        }
        return $sign_info;
    }

    private function is_mobile(){
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        $isMobile = 0;
        // 苹果机
        $is_iphone = (strpos($agent, 'iphone')) ? true : false;
        // ipad
        $is_ipad = (strpos($agent, 'ipad')) ? true : false;
        // 安卓机
        $is_android = (strpos($agent, 'android')) ? true : false;

        if($is_iphone || $is_ipad || $is_android){
            $isMobile = 1;
        }
        return $isMobile;
    }

    private function get_ip(){

        if(!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $cip = $_SERVER["HTTP_CLIENT_IP"];
        } else if(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if(!empty($_SERVER["REMOTE_ADDR"])) {
            $cip = $_SERVER["REMOTE_ADDR"];
        } else {
            $cip = '';
        }

        preg_match("/[\d\.]{7,15}/", $cip, $cips);
        $cip = isset($cips[0]) ? $cips[0] : 'unknown';

        unset($cips);
        return $cip;
    }

}
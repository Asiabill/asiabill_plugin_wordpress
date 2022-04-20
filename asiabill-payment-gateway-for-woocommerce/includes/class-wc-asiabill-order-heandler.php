<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Asiabill_Order_Handler extends WC_Asiabill_Payment_Gateway
{

    var $id;

    function __construct(){
        add_action( 'wp', [ $this, 'maybe_process_redirect_order' ] );
    }


    function maybe_process_redirect_order(){

        if ( ! is_order_received_page()
            || empty( $_REQUEST['orderNo'] )
            || empty( $_REQUEST['tradeNo'] )
            || empty( $_REQUEST['merNo'] )
            || empty( $_REQUEST['gatewayNo'] )
        ) {
            return;
        }

        $order_id = isset( $_REQUEST['orderNo'] ) ? wc_clean( wp_unslash( $_REQUEST['orderNo'] ) ) : '';

        if ( empty( $order_id ) ) {
            return;
        }

        $this->process_redirect_payment( $order_id );
    }

    function process_redirect_payment( $order_id ){

        $this->logger = new Wc_Asiabill_logger($this->id);

        $order = wc_get_order( $order_id );

        if ( ! is_object( $order ) ) {
            return;
        }

        $this->id = $order->get_payment_method();

        if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
            $this->logger->debug('order is complete');
            return;
        }

        try{

            $fail_message = false;

            $result_data = $this->sanitize_post();

            $order_state = $result_data['orderStatus'];
            $order_info = $result_data['orderInfo'];

            if( $this->verification($result_data) ){

                if( in_array($this->id,['wc_asiabill_ali','wc_asiabill_wechat']) && $order_state == '-1' ){
                    $fail_message = __( 'Unable to process this payment, please try again or use alternative method.', 'asiabill' );
                }

                if( $order_state == '0'  ){
                    $fail_message = __( 'Unable to process this payment, ', 'asiabill').$order_info;
                }

            }else{
                $this->logger->debug('verification is Fail');
                $fail_message = __( 'Unable to process this payment, ', 'asiabill').$order_info;
            }


            if( $fail_message ){
                $this->logger->debug('pay for '.$this->id.' status : '.$order_state.' fail : '.$order_info);
                wc_add_notice( $fail_message, 'error' );
                wp_safe_redirect( $order->get_checkout_payment_url() );
                die();
            }

        } catch (\Exception $e){
            $this->logger->error('System error:'.$e->getMessage());
            wc_add_notice( __( 'System error', 'asiabill'), 'error' );
            wp_safe_redirect( $order->get_checkout_payment_url() );
            die();
        }

        if( $order->get_status() !== 'processing' ){
            $this->confirm_order($result_data);
        }

        global $woocommerce;
        $woocommerce->cart->empty_cart();

    }

    function verification($result_data){

        $gateway = new Wc_Asiabill_Gateway('',$this->id);

        if( $this->id == 'wc_asiabill_creditcard' && $this->get_option('inline') == 'yes' ){
            unset($result_data['signInfo']);
            $check_sign = $gateway->get_v3_sign($result_data);
        }else{
            $check_sign = $gateway->get_result_sign($result_data);
        }

        if( isset($_REQUEST['signInfo']) && strtoupper($_REQUEST['signInfo']) === $check_sign ){
            return true;
        }

        return false;
    }

    function confirm_order($result_data){
        $gateway = new Wc_Asiabill_Gateway('',$this->id);

        $result = wp_remote_head( Wc_Asiabill_Api::QUERY, array(
            'method' => 'POST', // Request method. Accepts 'GET', 'POST', 'DELETE'
            'timeout' => '60', // How long the connection should stay open in seconds.
            'sslverify' => true,
            'headers' => ["Content-type" => "application/x-www-form-urlencoded"],
            'body' => [
                'merNo' => $result_data['merNo'],
                'gatewayNo' => $result_data['gatewayNo'],
                'orderNo' => $result_data['orderNo'],
                'signInfo' => strtolower($gateway->get_query_sign($result_data))
            ] ) );

        if( $result['response']['code'] == '200' ){

            $obj = simplexml_load_string($result['body'],"SimpleXMLElement", LIBXML_NOCDATA);
            $arr = json_decode(json_encode($obj),true);

            if( $arr['tradeinfo']['queryResult'] == '1' ){
                $this->change_order_status([
                    'orderNo' => $arr['tradeinfo']['orderNo'],
                    'tradeNo' => $arr['tradeinfo']['tradeNo'],
                    'orderInfo' => $arr['tradeinfo']['orderInfo'],
                ],'processing');

            }

        }
    }

}

new WC_Asiabill_Order_Handler();

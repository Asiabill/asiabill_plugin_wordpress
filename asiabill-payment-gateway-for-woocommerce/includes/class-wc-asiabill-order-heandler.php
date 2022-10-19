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

        $order = $this->get_order_by_number( wc_clean( wp_unslash( $_REQUEST['orderNo'] ) ) );


        if (  ! is_object( $order )  ) {
            return;
        }

        $this->process_redirect_payment( $order );
    }

    function process_redirect_payment( $order ){

        if ( $order->get_transaction_id() ) {
            return;
        }

        $this->id = $order->get_payment_method();
        $this->logger = new Wc_Asiabill_logger($this->id);

        try{

            $fail_message = false;

            $result_data = $this->sanitize_post();

            $order_state = $result_data['orderStatus'];
            $order_info = $result_data['orderInfo'];

            if( $this->api()->verification() ){

                if( $order_state == 'fail' ){
                    $fail_message = __( 'Unable to process this payment', 'asiabill').$order_info;
                }

            }else{
                $this->logger->debug('verification is Fail');
                $fail_message = __( 'Unable to process this payment', 'asiabill').$order_info;
            }
            $this->confirm_order($result_data['tradeNo'],$order);

            if( $fail_message ){
                $this->logger->debug('pay for #'.$this->id.' status : '.$order_state.' fail : '.$order_info);
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

        global $woocommerce;
        $woocommerce->cart->empty_cart();

    }

    function confirm_order($trade_no,$order){

        $response = $this->api()->openapi()->request('transactions',['query' => [
            'startTime' => date('Y-m-d').'T00:00:00',
            'endTime' => date('Y-m-d').'T23:59:59',
            'tradeNo' => $trade_no
        ]]);

        if( $response['code'] == '00000' ){

            $result_data = $response['data']['list'][0];
            $result_data['orderInfo'] = $result_data['tradeInfo'];
            $result_data['orderStatus'] = $result_data['tradeStatus'];

            $this->process_response($result_data,$order);
        }

    }

}

new WC_Asiabill_Order_Handler();

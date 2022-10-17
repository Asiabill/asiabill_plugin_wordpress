<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Asiabill_Webhook extends WC_Asiabill_Payment_Gateway
{

    var $id;

    function __construct(){

        add_action( 'woocommerce_api_asiabill_callback', array( $this, 'asiabill_callback' ) );
    }

    function asiabill_callback(){

        if ( ! isset( $_SERVER['REQUEST_METHOD'] )
            || ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
            || ! isset( $_GET['wc-api'] )
            || ( 'asiabill_callback' !== $_GET['wc-api'] )
        ) {
            echo 'success';
            die();
        }

        sleep(5);

        $webhook_data = Asiabill\Classes\AsiabillIntegration::getWebhookData();

        $order = $this->get_order_by_number($webhook_data['data']['orderNo']);

        if( !is_object( $order ) ){
            echo 'success';
            die();
        }

        $this->id = $order->get_payment_method();

        $asiabill_api = $this->api();

        // 校验信息
        if( !$asiabill_api->verification() ){
            echo 'Verification failed';
            die();
        }

        $this->logger = new Wc_Asiabill_logger($this->id);
        $this->logger->info('webhook data: ' . json_encode( $webhook_data) );

        // 交易事件
        if( preg_match("/^transaction|pre-authorized/",$webhook_data['type']) ){
            $this->process_response($webhook_data['data'],$order);
        }

        // 退款事件
        if( preg_match("/^refund/",$webhook_data['type']) ){
            $webhook_data['data']['refundStatus'] = $webhook_data['type'];
            $this->add_refund_note($webhook_data['data'],$order);

            // 退款失败
            if( $webhook_data['type'] == 'refund.fail' ){
                $refund_id = $webhook_data['data']['merTrackNo'];
                $refund = wc_get_order($refund_id);
                $order_id = $refund->get_parent_id();
                $refund->delete( true );
                do_action( 'woocommerce_refund_deleted', $refund_id, $order_id );
            }

        }

        echo 'success';
        die();

    }

}

new WC_Asiabill_Webhook();

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wc_Asiabill_Api
{

    public static function load_asiabill($gateway_id){

        $settings = get_option( 'woocommerce_'.$gateway_id.'_settings' );

        $mode = $settings['use_test_mode'] == 'yes'?'test':'live';
        if($mode == 'test'){
            $gateway_no = $settings['test_gateway_no'];
            $sign_key = $settings['test_signkey_code'];
        }else{
            $gateway_no = $settings['gateway_no'];
            $sign_key = $settings['signkey_code'];
        }

        if( !empty($gateway_no) && !empty($sign_key)){
            return new Asiabill\Classes\AsiabillIntegration($mode,$gateway_no,$sign_key);
        }

    }

}
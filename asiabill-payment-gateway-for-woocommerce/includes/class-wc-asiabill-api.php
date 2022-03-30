<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wc_Asiabill_Api
{
    const DOMAIN = 'https://safepay.asiabill.com';
    const SANDBOX = 'https://testpay.asiabill.com';
    const QUERY = 'https://api.asiabill.com/servlet/NormalCustomerCheck';
    const V3 = '/services/v3';

    private function get_action_url($test = 'yes'){
        return ($test=='yes'?self::SANDBOX:self::DOMAIN).'/Interface/V2';
    }

    public static function get_api_v3($test = 'yes',$api = ''){
        return ($test=='yes'?self::SANDBOX:self::DOMAIN).self::V3.'/'.$api;
    }

    public static function form($data,$test='yes',$method='post'){

        //$session_name = session_name();
        //header('Set-Cookie: '.esc_html($session_name).' = '.esc_attr($_COOKIE[$session_name]).'; SameSite=None; Secure',false);

        $url = self::get_action_url($test);

        $form = '<div id="block-place" class="blockUI blockOverlay" style=""></div>';
        $form .= '<form action="'.esc_url($url) .'" method="'. esc_attr($method) .'" name="asiabill_form" >';
        foreach ($data as $key => $value){
            $form .= '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_html($value).'" >';
        }
        $form .= '</form>';
        $form .= '<script type="text/javascript">document.asiabill_form.submit();</script>';
        echo $form;

    }

    public static function request($request,$api,$method = 'POST',$with_token = false){
        $headers = ["Content-type" => "application/json"];

        if( $with_token ){
            $headers['sessionToken'] = sanitize_text_field($_COOKIE['AsiabillSessionToken']);
        }

        $result = wp_remote_head( $api, array(
            'method' => $method, // Request method. Accepts 'GET', 'POST', 'DELETE'
            'timeout' => '60', // How long the connection should stay open in seconds.
            //'blocking' => false,
            'httpversion' => '1.1',
            'sslverify' => true,
            'headers' => $headers,
            'body' => json_encode($request) ) );

        if( is_object($result) && property_exists($result,'errors') ){
            return $result->errors['http_request_failed'][0];
        }

        if( $result['response']['code'] == '200' ){
            //请求成功
            return json_decode( $result['body'],true);
        }else if ( isset($result['response']['code']) ){
            return $result['response']['code'].' '.$result['response']['message'];
        }else {
            return false;
        }

    }



}
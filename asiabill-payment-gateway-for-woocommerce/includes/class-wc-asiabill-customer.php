<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wc_Asiabill_Customer
{

    /** Asiabill customer ID */
    private $id = '';

    /** WP User ID */
    private $user_id = 0;

    private static $mate_key = '_asiabill_customer_id';

    public function __construct( $user_id = 0 ) {
        if ( $user_id ) {
            $this->user_id = absint($user_id);
            $this->set_id( $this->get_id_from_meta( $user_id ) );
        }
    }

    public function has_customer()
    {
        if( $this->id == '' ){
            return false;
        }else{
            return true;
        }
    }

    public function get_id(){
        return $this->id;
    }

    public function set_id( $id ) {
        // Backwards compat for customer ID stored in array format. (Pre 3.0)
        if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
            $id = $id['customer_id'];
            $this->update_id_in_meta( $id );
        }
        $this->id = wc_clean( $id );
    }

    public function get_id_from_meta( $user_id ) {
        return get_user_option( self::$mate_key, $user_id );
    }

    public function update_id_in_meta( $id ) {
        update_user_option( $this->user_id, self::$mate_key, $id, false );
    }

    public function create_customer( $id ) {
        $this->set_id(['customer_id' => $id]);
    }

    public function delete_customer(){
        delete_user_option($this->user_id,self::$mate_key,false);
        $this->id = '';
    }


    public static function dump_customer(){
        delete_metadata('user',0,'wp_'.self::$mate_key,'',true);
    }

    public function get_payment_methods($gateway_id){

        $methods = get_transient( 'asiabill_method_' . $this->get_id() );

        if( empty($methods) ) $methods = false;

        if( $this->id == ''  )$this->clear_cache();

        if( $this->id != '' &&  $methods === false ){
            $settings = get_option( 'woocommerce_'.$gateway_id.'_settings' );
            $api = Wc_Asiabill_Api::get_api_v3($settings['use_test_model'],'payment_methods/list/'.$this->id);
            $response = Wc_Asiabill_Api::request([],$api,'GET',true);
            if( isset($response['code']) && $response['code'] == '0' ){
                $methods = $response['data'];
            }
            set_transient( 'asiabill_method_' . $this->get_id(), $methods, DAY_IN_SECONDS );
        }
        return $methods;

    }

    public function get_payment_method($gateway_id,$method_id){

        $methods = get_transient( 'asiabill_method_' . $this->get_id() );
        $method = [];
        if( $methods === false || $methods === '' ){
            $settings = get_option( 'woocommerce_'.$gateway_id.'_settings' );
            $api = Wc_Asiabill_Api::get_api_v3($settings['use_test_model'],'payment_methods/'.$method_id);
            $response = Wc_Asiabill_Api::request([],$api,'GET',true);
            if( isset($response['code']) && $response['code'] == '0' ){
                $method = $response['data'];
            }
        }else{
            $methods = array_column($methods,null,'customerPaymentMethodId');
            if( key_exists($method_id,$methods) ){
                $method = $methods[$method_id];
            }
        }
        return $method;
    }

    public function update_payment_method($gateway_id,$method){
        $settings = get_option( 'woocommerce_'.$gateway_id.'_settings' );
        $api = Wc_Asiabill_Api::get_api_v3($settings['use_test_model'],'payment_methods/update');
        $response = Wc_Asiabill_Api::request($method,$api,'POST',true);
        return $response;
    }


    public function delete_payment_methods($payment_method_id){
        $settings = get_option( 'woocommerce_asiabill_creditcard_settings' );
        $api = Wc_Asiabill_Api::get_api_v3($settings['use_test_model'],'payment_methods/'.$payment_method_id.'/detach');
        Wc_Asiabill_Api::request([],$api,'GET',true);
        $this->clear_cache();
    }

    public function clear_cache(){
        delete_transient( 'asiabill_method_' . $this->get_id() );
    }


}
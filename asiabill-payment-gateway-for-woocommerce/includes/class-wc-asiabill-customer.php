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

        if( $this->id == ''  ) $this->clear_cache();

        if( $this->id != '' && empty($methods)){

            $response = Wc_Asiabill_Api::load_asiabill($gateway_id)->request('paymentMethods_list',['path'=> ['customerId' => $this->id]]);

            if( isset($response['code']) && $response['code'] == '00000' ){
                $methods = $response['data'];
            }
            set_transient( 'asiabill_method_' . $this->get_id(), $methods, DAY_IN_SECONDS );
        }

        if( $methods === false ){
            return [];
        }

        return $methods;

    }

    public function get_payment_method($gateway_id,$method_id){

        $methods = get_transient( 'asiabill_method_' . $this->get_id() );
        $method = [];
        if( $methods === false || $methods === '' ){
            $response = Wc_Asiabill_Api::load_asiabill($gateway_id)->request('paymentMethods_query',['path'=>['customerPaymentMethodId'=>$method_id]]);
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
        return Wc_Asiabill_Api::load_asiabill($gateway_id)->request('paymentMethods_update',['body'=>$method]);
    }

    public function delete_payment_methods($payment_method_id){
        Wc_Asiabill_Api::load_asiabill('wc_asiabill_creditcard')->request('paymentMethods_detach',['path'=>['customerPaymentMethodId'=>$payment_method_id] ]);
        $this->clear_cache();
    }

    public function clear_cache(){
        delete_transient( 'asiabill_method_' . $this->get_id() );
    }


}
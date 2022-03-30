<?php

if (! defined ( 'ABSPATH' ))
    exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Directpay extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'Asiabill Directpay Payment';
    var $method_description = 'Directpay 本地支付';
    var $logger;

    public function __construct() {
        parent::__construct('wc_asiabill_directpay');
    }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Directpay';
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
        );
    }

    public function get_icon(){
        if( $this->get_option('show_logo') == 'yes' ){
            $img = '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/directpay.png" alt="directpay"/>';
            return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
        }
    }


}

?>
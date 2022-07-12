<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Kakaopay extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'AsiaBill Kakao Pay';
    var $method_description = 'Kakao Pay (Alipay+™ Partner)';
    var $logger;

	public function __construct() {
        parent::__construct('wc_asiabill_kakaopay');
        $this->title = 'Kakao Pay (Alipay+™ Partner)';
	}

    public function init_form_fields() {
        parent::init_form_fields();
        unset($this->form_fields['title']);
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
        );
	}

    public function get_icon(){

        if( $this->get_option('show_logo') == 'yes' ){
            $img = '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/kakaopay.png" alt="kakaopay" />';
            return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
        }
    }


}

?>
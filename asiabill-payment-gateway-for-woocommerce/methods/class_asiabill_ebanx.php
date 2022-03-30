<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Ebanx extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'Asiabill Ebanx Payment';
    var $method_description = 'Ebanx 本地支付';
    var $logger;


	public function __construct() {
        parent::__construct('wc_asiabill_ebanx');
	}

    /**
     * 设置参数
     */
    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Ebanx';
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
        );
	}

    public function get_icon(){
        if( $this->get_option('show_logo') == 'yes' ){
            $img = '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/ebanx.png" alt="ebanx"/>';
            return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
        }
    }


}

?>
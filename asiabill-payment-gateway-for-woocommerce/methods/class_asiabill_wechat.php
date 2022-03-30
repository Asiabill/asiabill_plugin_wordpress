<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Wechat extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'Asiabill Wechat Pay';
    var $method_description = '微信支付';
    var $logger;

	public function __construct() {
        parent::__construct('wc_asiabill_wechat');
	}

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Wechat Pay';
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
        );
	}

    public function get_icon(){
        if( $this->get_option('show_logo') == 'yes' ){
            $img = '<style></style>';
            $img .= '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/wechat.png" alt="wechat"/>';

            return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );

        }
    }


}

?>
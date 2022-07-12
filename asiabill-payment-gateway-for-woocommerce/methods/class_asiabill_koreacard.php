<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Koreacard extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'AsiaBill Korea Local cards';
    var $method_description = '韩国信用卡支付';
    var $logger;

	public function __construct() {
        parent::__construct('wc_asiabill_koreacard');
	}

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Korea cards';
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
        );
	}

    public function get_icon(){

        $img = '';

        if( $this->get_option('show_logo') == 'yes' ){

            $icons = [
                'bc_card','lotte_card','nh_card','samsung_card','shinhan_card'
            ];

            foreach ($icons as $icon){
                $img .= '<img id="asiabill_gateway_logo" src="'.ASIABILL_PAYMENT_URL.'/assets/images/'.$icon.'.svg" alt="'.$icon.'" />';
            }

            return apply_filters( 'woocommerce_gateway_icon', $img, $this->id );
        }
    }


}

?>
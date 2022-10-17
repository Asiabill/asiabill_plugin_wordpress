<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Paysafecard extends WC_Asiabill_Payment_Gateway {


    var $id;
    var $method_title       = 'AsiaBill Paysafecard Payment';
    var $method_description = 'Paysafecard 本地支付';
    var $logger;

	public function __construct() {
        parent::__construct('wc_asiabill_paysafecard');
	}

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Paysafecard';
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'no'
        );
	}

}

?>
<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class WC_Gateway_Asiabill_Crypto extends WC_Asiabill_Payment_Gateway {

    var $id;
    var $method_title       = 'AsiaBill Crypto Payment';
    var $method_description = 'Crypto 加密货币';
    var $logger;

	public function __construct() {
        parent::__construct('wc_asiabill_crypto');
	}

    /**
     * 设置参数
     */
    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = 'Crypto';
        $this->form_fields['show_logo'] = array(
            'title' => __ ( 'Show logo', 'asiabill' ),
            'type' => 'checkbox',
            'default' => 'yes'
        );
	}


}

?>
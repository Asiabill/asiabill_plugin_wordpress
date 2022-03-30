<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wc_Asiabill_logger
{
    public static $logger;
    private $logging;
    private $log_entry = '';
    const WC_LOG_FILENAME = 'woocommerce-gateway-asiabill';

    function __construct($gateway_id){
        $settings = get_option( 'woocommerce_'.$gateway_id.'_settings' );

        if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
            $this->logging = 'no';
        }else{
            $this->logging = 'yes';
        }
    }

    function log( $message ) {

        if ( ! class_exists( 'WC_Logger' ) ) {
            return '';
        }

        if ( empty( self::$logger ) ) {
            self::$logger = wc_get_logger();
        }

        $log_entry = '==== Start Log : '.print_r($message,true) .' : End Log ====';

        $this->log_entry = $log_entry;
    }

    function debug($message){
        $this->log($message);
        self::add( 'debug' );
    }

    function info($message){
        $this->log($message);
        self::add( 'info' );
    }

    function error($message){
        $this->log($message);
        self::add( 'error', true);
    }

    private function add($action,$logging = false){
        if( $this->log_entry != '' && ( $logging == true || 'yes' == $this->logging ) ){
            self::$logger->$action( $this->log_entry, [ 'source' => self::WC_LOG_FILENAME ] );
        }
    }

}
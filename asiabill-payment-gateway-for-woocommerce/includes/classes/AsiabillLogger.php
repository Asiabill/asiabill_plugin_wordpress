<?php
namespace Asiabill\Classes;


class AsiabillLogger
{
    protected $logger_dir;

    function __construct($dir)
    {
        $this->logger_dir = $dir;
    }

    function addLog($message,$filename){
        $file = self::openFile($filename);
        if( $file ){
            fwrite($file, date('Y-m-d H:i:s') . ' - ' . print_r($message, true) . "\n");
            fclose($file);
        }
    }


    protected function openFile($filename){

        if( is_dir($this->logPath()) ){
            $dir = true;
        }else{
            $dir = mkdir($this->logPath(),0777,true);
        }
        if( $dir ){
            return fopen($this->logPath() . date('d').'-'.$filename.'.log', 'a');
        }
        return false;

    }

    protected function logPath(){
        return $this->logger_dir.date('Y-m').DIRECTORY_SEPARATOR;
    }

}

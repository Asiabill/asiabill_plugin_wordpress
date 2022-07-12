<?php
namespace Asiabill\Classes;

include_once 'AsiabillHttp.php';
include_once 'AsiabillLogger.php';

class AsiabillIntegration
{
    const VERSION = '1.1';
    const PAYMENT_LIVE = 'https://safepay.asiabill.com';
    const PAYMENT_TEST = 'https://testpay.asiabill.com';
    const OPENAPI_LIVE = 'https://openapi.asiabill.com/openApi';
    const OPENAPI_TEST = 'https://api-uat.asiabill.com/openApi';
    const API_VERSION = '/V2022-03';

    protected $payment_url;
    protected $openapi_url;
    protected $url;
    protected $gateway_no;
    protected $sign_key;
    protected $logger = false;
    protected $default_dir;
    protected $receive_data = array();
    protected $request_type = array(
        'customers' => '/customers', // 操作客户
        'sessionToken' => '/sessionToken', // 获取sessionToken
        'paymentMethods' => '/payment_methods', // 创建paymentMethodId
        'paymentMethods_list' => '/payment_methods/list/{customerId}', // 查询paymentMethod列表
        'paymentMethods_update' => '/payment_methods/update', // 更新paymentMethodId信息
        'paymentMethods_query' => '/payment_methods/{customerPaymentMethodId}', // 查询paymentMethodId信息
        'paymentMethods_detach' => '/payment_methods/{customerPaymentMethodId}/detach', // 解绑paymentMethodId
        'paymentMethods_attach' => '/payment_methods/{customerPaymentMethodId}/{customerId}/attach', // 绑定paymentMethodId
        'confirmCharge' => '/confirmCharge', // 确认扣款
        'checkoutPayment' => '/checkout/payment', // 获取支付页面地址
        'Authorize' => '/AuthorizeInterface', // 预授权接口
        'chargebacks' => '/chargebacks', // 拒付查询
        'refund' => '/refund', // 退款申请
        'refund_query' => '/refund/{batchNo}', // 退款查询
        'logistics' => '/logistics', // 上传物流信息
        'transactions' => '/transactions', // 交易流水列表
        'orderInfo' => '/orderInfo/{tradeNo}', // 交易详情
    );

    /**
     * 初始化方法
     * @param $mode // test or live
     * @param $gateway_no
     * @param $sign_key
     * @throws \Exception
     */
    function __construct($mode,$gateway_no,$sign_key)
    {

        if( empty($mode) || empty($gateway_no) || empty($sign_key) ){
            throw new \Exception('Initialization error');
        }

        if( !in_array($mode,array('test','live')) ){
            throw new \Exception('The "mode" must be "test" or "live"');
        }

        $this->default_dir = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR;
        $this->payment_url = ( $mode == 'test' ? self::PAYMENT_TEST : self::PAYMENT_LIVE );
        $this->openapi_url = ( $mode == 'test' ? self::OPENAPI_TEST : self::OPENAPI_LIVE );
        $this->gateway_no = $gateway_no;
        $this->sign_key = $sign_key;

    }

    static function isMobile()
    {
        $clientkeywords = array(
            'mobile','iphone','ipod','iPad','android','HarmonyOS','wap'
        );
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return 1;
        }
        return 0;
    }

    static function clientIP(){

        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            //优先使用  HTTP_X_FORWARDED_FOR，此值是一个逗号分割的多个IP
            $ips = $_SERVER["HTTP_X_FORWARDED_FOR"];
            $ips = explode(',',$ips);
            $ip = array_shift($ips);
        }
        elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        }
        else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }

        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ||
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false){
            return $ip;
        }else{
            return $_SERVER["REMOTE_ADDR"];
        }
    }

    function startLogger($bool = true, $dir = '' )
    {
        if( !empty($dir)){
            $this->default_dir = $dir;
        }

        if( $bool ){
            $this->logger = new AsiabillLogger($this->default_dir);
        }else{
            $this->logger = false;
        }

    }

    function isLogger()
    {
        return ($this->logger && is_object($this->logger));
    }


    function initialApi()
    {
        $this->url = '';
    }

    function payment()
    {
        $this->url = $this->payment_url.self::API_VERSION;
        return $this;
    }

    function openapi()
    {
        $this->url = $this->openapi_url.self::API_VERSION;
        return $this;
    }

    function getJsScript(){
        return $this->payment_url.'/static/v3/js/AsiabillPayment.min.js';
    }

    /**
     * @param $type
     * @param array $data array('path'=>array(),'query'=>array(),'body'=>array())
     * @return mixed
     * @throws \Exception
     */
    function request($type,array $data = array())
    {

        if( empty( $this->url ) ){
            $this->url = $this->payment_url.self::API_VERSION;
        }

        if( !key_exists($type,$this->request_type) ){
            return 'the request_type is non-existent';
        }

        if( method_exists($this,$type) ){
            return $this->$type($data);
        }
        else{
            return $this->requestCommon($type,$data);
        }

    }

    /**
     * @param $request_type 请求类型，自定义
     * @param $request_path api路径，参考文档资料
     * @return $this
     * @throws \Exception
     */
    function addRequest($request_type,$request_path){
        if( isset($this->request_type[$request_type]) ){
            throw new \Exception('the request key is is exists!');
        }
        $this->request_type[$request_type] = $request_path;
        return $this;
    }

    /**
     * 校验结果签名信息
     * @return bool
     */
    function verification()
    {
        // returnUrl 接收验证签名
        if( !empty($_GET) && isset($_GET['tradeNo']) ){

            $data = array();
            $signInfo = $_GET['signInfo'];

            $data['query'] = array(
                'orderNo' => $_GET['orderNo'],
                'orderAmount' => $_GET['orderAmount'],
                'code' => $_GET['code'],
                'merNo' => $_GET['merNo'],
                'gatewayNo' => $_GET['gatewayNo'],
                'tradeNo' => $_GET['tradeNo'],
                'orderCurrency' => $_GET['orderCurrency'],
                'orderInfo' => $_GET['orderInfo'],
                'orderStatus' => $_GET['orderStatus'],
                'maskCardNo' => isset( $_GET['maskCardNo'] )?$_GET['maskCardNo']:'' ,
                'message' => $_GET['message'],
            );

            if( $this->isLogger() ){
                $this->logger->addLog('browser : '.json_encode($data),'result');
            }

            return @$signInfo == strtoupper($this->signInfo($data));
        }

        // webhook 接收验证签名
        if( $_SERVER['REQUEST_METHOD'] == 'POST' ){

            $request_header = getallheaders();

            $data = array(
                'header' =>  array(
                    'gateway-no' => @$request_header['Gateway-No'],
                    'request-time' => @$request_header['Request-Time'],
                    'request-id' => @$request_header['Request-Id'],
                    'version' => @$request_header['Version']
                ),
                'body' => $this->getWebhookData()
            );

            $check_sign_info = $this->signInfo($data);

            if( $this->isLogger() ){
                $this->logger->addLog('webhook : '.json_encode($data),'result');
                $this->logger->addLog('check sign info : '.$request_header['Sign-Info'].' & '.$check_sign_info,'result');
            }

            return @$request_header['Sign-Info'] == strtoupper( $check_sign_info );

        }

        return false;
    }

    /**
     * 获取webhook内容
     * @return mixed
     */
    function getWebhookData()
    {
        if( empty( $this->receive_data) && $_SERVER['REQUEST_METHOD'] == 'POST' ){
            $this->receive_data = json_decode(file_get_contents( 'php://input' ),true);
        }

        return $this->receive_data;
    }

    private function requestCommon($type,$data)
    {
        $method = 'POST';

        $parameters = array(
            'header' => $this->parametersHeader(),
        );

        if( is_array($data) ){

            if( isset($data['path']) ){
                $parameters['path'] = $data['path'];
            }

            if( isset($data['query']) ){
                $parameters['query'] = $data['query'];
            }

            if( isset($data['body']) ){
                $parameters['body'] = $data['body'];
            }

        }

        if( empty($parameters['body']) ){
            $method = 'GET';
        }

        $uri = $this->request_type[$type];

        if( isset($parameters['path']) ){
            foreach ($parameters['path'] as $key => $val){
                $uri = str_replace('{'.$key.'}',$val,$uri);
            }
        }

        if( isset($parameters['query']) ){
            $uri .= '?'.http_build_query($parameters['query']);
        }

        return $this->handle($uri, $parameters,$method);
    }

    private function customers($data)
    {

        $parameters = array(
            'header' => $this->parametersHeader()
        );

        $uri = $this->request_type['customers'];

        if( isset($data['body']) ){
            $parameters['body'] = $data['body'];
            return $this->handle($uri, $parameters);
        }
        else if( isset($data['path']) ){
            $parameters['path'] = $data['path'];
            $uri .= '/'.$data['path']['customerId'];
            if( isset($data['delete']) && $data['delete'] === true ){
                return $this->handle($uri, $parameters,'DELETE');
            }
            return $this->handle($uri, $parameters,'GET');
        }
        else{
            if( isset($data['query']) ){
                $parameters['query'] = $data['query'];
                $uri .= '?'.http_build_query($parameters['query']);
            }

            return $this->handle($uri, $parameters,'GET');
        }

    }

    private function sessionToken()
    {

        $parameters = array(
            'header' => $this->parametersHeader()
        );

        $result = $this->handle($this->request_type['sessionToken'], $parameters);

        if( $result['code'] == '00000' ){
            return $result['data']['sessionToken'];
        }
        return null;

    }

    private function parametersHeader()
    {

        list($t1, $t2) = explode(' ', microtime());
        $msectime = (string)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);

        return array(
            'gateway-no' => $this->gateway_no,
            'request-id' => $msectime.'-'.rand(1,9999),
            'request-time' => $msectime,
            'sign-info' => ''
        );
    }

    private function handle($uri,$parameters,$method='POST')
    {
        $parameters['header']['sign-info'] = $this->signInfo($parameters);

        if( $this->isLogger() ){
            $this->logger->addLog('request-api : '.$this->url.$uri,'request');
            $this->logger->addLog('parameters : '.json_encode($parameters),'request');
        }

        $asiabillHttp = new AsiabillHttp($this->url.$uri);

        if( $asiabillHttp->request($parameters,$method) ){ // 请求成功，返回响应体
            $this->initialApi();

            if( $this->isLogger() ){
                $this->logger->addLog('response : '.$asiabillHttp->getResponseInfo('Response'),'request');
            }
            return  $asiabillHttp->getResponsetoArr();

        }else{ // 请求失败，报错提示
            $info = $asiabillHttp->getResponseInfo();
            throw new \Exception('Request "'.$info['Request URL'].'" to status code : "'.$info['Status code'].'"');
        }
    }

    private function signInfo($data){

        $sign_arr = array();
        if( isset($data['header']) ){
            $header_str = $data['header']['gateway-no'].$data['header']['request-id'].$data['header']['request-time'];
            if( isset($data['header']['version']) ){
                $header_str .= $data['header']['version'];
            }
            $sign_arr[] = $header_str;

        }

        if( isset($data['path']) ){
            sort($data['path']);
            $sign_arr[] = implode('',$data['path']);
        }

        if( isset($data['query']) ){
            ksort($data['query']);
            $sign_arr[] = implode('',$data['query']);
        }

        if( isset($data['body']) ){
            $sign_arr[] = json_encode($data['body']);
        }

        $sign_str = implode('.',array_filter($sign_arr));

        return hash_hmac('sha256', $sign_str, $this->sign_key);

    }



}

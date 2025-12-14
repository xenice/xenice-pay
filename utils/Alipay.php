<?php

namespace xenice\pay\utils;

class Alipay
{

    public $appid = '';
    public $public_key = '';
    public $private_key = '';
    
    public function __construct($appid, $public_key, $private_key)
    {
        $this->appid = $appid;
        $this->public_key = $public_key;
        $this->private_key = $private_key;
    }
    
	public function f2fPay($args)
    {
        
        //if(!take('enable_alipay_f2f')) return;
        
        $appid = $this->appid;
        $public_key = $this->public_key;
        $private_key = $this->private_key;
        if(!$public_key || !$public_key || !$private_key) return;
        
        // 公共配置
        $params = new \Yurun\PaySDK\AlipayApp\Params\PublicParams();
        $params->appID = $appid;
        $params->appPrivateKey = $private_key;
        $params->appPublicKey = $public_key;
        //$params->sign_type = 'RSA2';
 
        // $params->isUseAES = true; // 沙箱环境可能用不了AES加密
        // $params->aesKey = $GLOBALS['PAY_CONFIG']['aesKey'];
        // $params->appPrivateKeyFile = ''; // 证书文件，如果设置则这个优先使用
        //$params->apiDomain = 'https://openapi.alipaydev.com/gateway.do'; // 设为沙箱环境，如正式环境请把这行注释
        
        // SDK实例化，传入公共配置
        $pay = new \Yurun\PaySDK\AlipayApp\SDK($params);
        
        // 支付接口
        $request = new \Yurun\PaySDK\AlipayApp\FTF\Params\QR\Request();
        $request->notify_url = $args['notify_url']; // 支付后通知地址（作为支付成功回调，这个可靠）
        $request->businessParams->out_trade_no = $args['trade_order_id']; // 商户订单号
        $request->businessParams->total_amount = $args['total_fee']; // 价格
        $request->businessParams->subject = $args['title']; // 商品标题

        // 调用接口
        try
        {
            $data = $pay->execute($request);

            if($pay->checkResult()){
                // include(__DIR__ . '/page.php');
                $order_sn = $data['alipay_trade_precreate_response']['out_trade_no'];
                $qr_code = $data['alipay_trade_precreate_response']['qr_code'];
                $rt = [
                    'order_sn'=>$order_sn,
                    'qr_code'=>$qr_code,
                    //'return_url'=>$args['return_url'],
                    'token'=>$args['token'],
                    'price'=>$args['total_fee'],
                    'title'=>$args['title'],
                    'payway'=>'alipay_f2f',
                    'way'=>'alipay',
                    'wayname'=>'支付宝支付',
                ];
                /*
                $url  = $args['page_url'];
                $url .= '?order_sn=' . $order_sn . '&qr_code=' . urlencode($qr_code) . '&return_url=' . urlencode($args['return_url']) . '&token=' . $args['token'];*/
                return $rt;
            }
            else{
                //var_dump('error:', $pay->getError(), 'error_code:', $pay->getErrorCode());
            }
        }
        catch (Exception $e)
        {
            //var_dump($pay->response->body());
        }
        exit;
    }
    
    public function phonePay($args)
    {
        $appid = $this->appid;
        $public_key = $this->public_key;
        $private_key = $this->private_key;
        if(!$public_key || !$public_key || !$private_key) return;
        
        // 公共配置
        $params = new \Yurun\PaySDK\AlipayApp\Params\PublicParams();
        $params->appID = $appid;
        $params->appPrivateKey = $private_key;
        $params->appPublicKey = $public_key;
        //$params->sign_type = 'RSA2';
 
        // $params->isUseAES = true; // 沙箱环境可能用不了AES加密
        // $params->aesKey = $GLOBALS['PAY_CONFIG']['aesKey'];
        // $params->appPrivateKeyFile = ''; // 证书文件，如果设置则这个优先使用
        $params->apiDomain = 'https://openapi.alipaydev.com/gateway.do'; // 设为沙箱环境，如正式环境请把这行注释
        
        // SDK实例化，传入公共配置
        $pay = new \Yurun\PaySDK\AlipayApp\SDK($params);
        
        // 支付接口
        $request = new \Yurun\PaySDK\AlipayApp\Wap\Params\Pay\Request();
        $request->notify_url = $args['return_url'];; // 支付后通知地址（作为支付成功回调，这个可靠）
        $request->notify_url = $args['notify_url']; // 支付后通知地址（作为支付成功回调，这个可靠）
        $request->businessParams->out_trade_no = $args['trade_order_id']; // 商户订单号
        $request->businessParams->total_amount = $args['total_fee']; // 价格
        $request->businessParams->subject = $args['title']; // 商品标题

        // 调用接口
        try
        {
            $pay->prepareExecute($request, $url);
            $rt = [
                'payway'=>'alipay_phone',
                'url'=>$url,
            ];
            return $rt;
        }
        catch (Exception $e)
        {
            echo $e->getMessage();
        }
        exit;
    }
}
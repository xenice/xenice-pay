<?php
namespace xenice\pay\utils;


class WechatPay
{

    public $appid = '';
    public $mch_id = '';
    public $key = '';
    
    public function __construct($appid, $mch_id, $key)
    {
        $this->appid = $appid;
        $this->mch_id = $mch_id;
        $this->key = $key;
    }
    
    // h5支付
    public function h5Pay($args)
    {
        
        // 公共配置
        $params = new \Yurun\PaySDK\Weixin\Params\PublicParams();
        $params->appID = $this->appid;
        $params->mch_id = $this->mch_id;
        $params->key = $this->key;
        
        // SDK实例化，传入公共配置
        $pay = new \Yurun\PaySDK\Weixin\SDK($params);
        // 支付接口
        $request = new \Yurun\PaySDK\Weixin\H5\Params\Pay\Request();
        $request->body = $args['title']; // 商品描述
        $request->out_trade_no = $args['trade_order_id']; ; // 订单号
        $request->total_fee = $args['total_fee']*100; // 订单总金额，单位为：分
        $request->spbill_create_ip = '127.0.0.1'; // 客户端ip
        $request->notify_url = $args['notify_url'];; // 异步通知地址
        $request->scene_info = new \Yurun\PaySDK\Weixin\H5\Params\SceneInfo();
        $request->scene_info->type = 'Wap'; // 可选值：IOS、Android、Wap
        // 下面参数根据type不同而不同
        $request->scene_info->wap_url = 'https://baidu.com';
        $request->scene_info->wap_name = 'test';
        
        // 调用接口
        $result = $pay->execute($request);
        if ($pay->checkResult())
        {
            // 跳转支付界面
            $rt = [
                'order_sn'=>$args['trade_order_id'],
                'payway'=>'wechat_h5',
                'url'=>$result['mweb_url'],
            ];
            return $rt;
        }
        else
        {
            var_dump($pay->getErrorCode() . ':' . $pay->getError());
        }
        exit;
    }
    
    // 公众号支付
	public function jsapiPay($args)
    {
        
        // 公共配置
        $params = new \Yurun\PaySDK\Weixin\Params\PublicParams();
        $params->appID = $this->appid;
        $params->mch_id = $this->mch_id;
        $params->key = $this->key;
        
        // SDK实例化，传入公共配置
        $pay = new \Yurun\PaySDK\Weixin\SDK($params);
        
        // 支付接口
        $request = new \Yurun\PaySDK\Weixin\JSAPI\Params\Pay\Request();
        $request->body = $args['title']; // 商品描述
        $request->out_trade_no = $args['trade_order_id']; ; // 订单号
        $request->total_fee = $args['total_fee']*100; // 订单总金额，单位为：分
        $request->spbill_create_ip = '127.0.0.1'; // 客户端ip
        $request->notify_url = $args['notify_url'];; // 异步通知地址
        $request->openid = $args['openid']; // 必须设置openid
        // 调用接口
        $result = $pay->execute($request);
        /*
        var_dump('result:', $result);
        
        var_dump('success:', $pay->checkResult());
        
        var_dump('error:', $pay->getError(), 'error_code:', $pay->getErrorCode());*/
        
        if ($pay->checkResult())
        {
            $request = new \Yurun\PaySDK\Weixin\JSAPI\Params\JSParams\Request();
            $request->prepay_id = $result['prepay_id'];
            $jsapiParams = $pay->execute($request);
            // 最后需要将数据传给js，使用WeixinJSBridge进行支付
            $rt = [
                'order_sn'=>$args['trade_order_id'],
                'payway'=>'wechat_jsapi',
                'params'=>$jsapiParams,
            ];
            return $rt;
        }
    }
    
    
    // 小程序支付
	public function miniPay($args)
    {
        
        // 公共配置
        $params = new \Yurun\PaySDK\Weixin\Params\PublicParams();
        $params->appID = $this->appid;
        $params->mch_id = $this->mch_id;
        $params->key = $this->key;
        
        // SDK实例化，传入公共配置
        $pay = new \Yurun\PaySDK\Weixin\SDK($params);
        // 支付接口
        $request = new \Yurun\PaySDK\Weixin\APP\Params\Pay\Request();
        $request->body = $args['title']; // 商品描述
        $request->out_trade_no = $args['trade_order_id']; ; // 订单号
        $request->total_fee = $args['total_fee']*100; // 订单总金额，单位为：分
        $request->spbill_create_ip = '127.0.0.1'; // 客户端ip
        $request->notify_url = $args['notify_url'];; // 异步通知地址
        $request->openid = $args['openid']; // 必须设置openid
        $request->scene_info->store_id = '门店唯一标识，选填';
        $request->scene_info->store_name = '门店名称，选填';
        
        // 调用接口
        $result = $pay->execute($request);
        if ($pay->checkResult())
        {
            $clientRequest = new \Yurun\PaySDK\Weixin\APP\Params\Client\Request();
            $clientRequest->prepayid = $result['prepay_id'];
            $pay->prepareExecute($clientRequest, $url, $data);
            // 最后需要将数据传给js，使用WeixinJSBridge进行支付
            $rt = [
                'order_sn'=>$args['trade_order_id'],
                'payway'=>'wechat_mini',
                'params'=>$data,
            ];
            return $rt;
        }
        else
        {
            var_dump($pay->getErrorCode() . ':' . $pay->getError());
        }
        exit;



        
        
        // 支付接口
        $request = new \Yurun\PaySDK\Weixin\JSAPI\Params\Pay\Request();
        $request->body = $args['title']; // 商品描述
        $request->out_trade_no = $args['trade_order_id']; ; // 订单号
        $request->total_fee = $args['total_fee']*100; // 订单总金额，单位为：分
        $request->spbill_create_ip = '127.0.0.1'; // 客户端ip
        $request->notify_url = $args['notify_url'];; // 异步通知地址
        $request->openid = $args['openid']; // 必须设置openid
        // 调用接口
        $result = $pay->execute($request);
        /*
        var_dump('result:', $result);
        
        var_dump('success:', $pay->checkResult());
        
        var_dump('error:', $pay->getError(), 'error_code:', $pay->getErrorCode());*/
        
        if ($pay->checkResult())
        {
            $request = new \Yurun\PaySDK\Weixin\JSAPI\Params\JSParams\Request();
            $request->prepay_id = $result['prepay_id'];
            $jsapiParams = $pay->execute($request);
            // 最后需要将数据传给js，使用WeixinJSBridge进行支付
            $rt = [
                'order_sn'=>$args['trade_order_id'],
                'payway'=>'wechat_jsapi',
                'params'=>$jsapiParams,
            ];
            return $rt;
        }
    }
}
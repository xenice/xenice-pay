<?php

namespace xenice\pay\controllers;


use function xenice\pay\get as get;

class PayNotifyController extends \WP_REST_Controller{
    
    protected $namespace = 'xe/v1';
    protected $rest_base = 'pay-notify';
    
    public function register_routes() {
        
        register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wechat-pay',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'wechat_pay' ),
				'permission_callback' => [$this, 'check_token'],
				'args'                => [],
			]
		);
		
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alipay',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'alipay' ),
				'permission_callback' => [$this, 'check_token'],
				'args'                => [],
			]
		);
		
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/xunhupay',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'xunhupay' ),
				'permission_callback' => [$this, 'check_token'],
				'args'                => [],
			]
		);
    }
	
	public function wechat_pay($request) {
		$request = $request->get_params();
	    $appid = get('wechat_pay_appid');
        $mch_id = get('wechat_pay_mch_id');
        $key = get('wechat_pay_key');
	    // 公共配置
        $params = new \Yurun\PaySDK\Weixin\Params\PublicParams();
        $params->appID = $appid;
        $params->mch_id = $mch_id;
        $params->key = $key;
        
        // SDK实例化，传入公共配置
        $sdk = new \Yurun\PaySDK\Weixin\SDK($params);

        $payNotify = new WechatPayNotify();
        try
        {
            $sdk->notify($payNotify);
        }
        catch (Exception $e)
        {
            file_put_contents(__DIR__ . '/notify_result.txt', $e->getMessage() . ':' . var_export($payNotify->data, true));
        }
	}
	
	public function alipay($request)
	{
	    $request = $request->get_params();
        $appid = get('alipay_appid');
        $public_key = get('alipay_public_key');
        $private_key = get('alipay_private_key');
                
        // 公共配置
        $params = new \Yurun\PaySDK\AlipayApp\Params\PublicParams();
        $params->appPublicKey = $public_key;
        $params->appPrivateKey = $private_key;
        
        // SDK实例化，传入公共配置
        $pay = new \Yurun\PaySDK\AlipayApp\SDK($params);
        
        // unset($request['action']);
        $request['fund_bill_list'] = str_replace('\"','"',$request['fund_bill_list']);
        //$content = var_export($request, true) . \PHP_EOL . 'verify:' . var_export($pay->verifyCallback($request), true);

        //file_put_contents(__DIR__ . '/notify_result.txt', $content);
        if($pay->verifyCallback($request))
        {
            do_action('xenice_pay_alipay_notify', $request);
            //Theme::use('order')->setPaid($request['out_trade_no'], $request['trade_no']);
            //Theme::use('auth')->addByOrder($request['out_trade_no']);
            // 通知验证成功，可以通过POST参数来获取支付宝回传的参数
        }
        else
        {
            //file_put_contents(__DIR__ . '/error.txt', $pay->getError());
            // 通知验证失败
        }
        rest_ensure_response('success');
	}
	
	public function xunhupay($request)
	{

        $appid = get('wechat_xunhupay_app_id');
        $secret = get('wechat_xunhupay_app_secret');

        $data = Theme::use('xunhupay', $appid, $secret)->verify();

        if($data){
           // Theme::use('order')->setPaid($data['order_sn'], $data['outer_order_sn']);
            // Theme::use('auth')->addByOrder($data['order_sn']);
        }
        
        rest_ensure_response('success');

    }
	
	public function check_token($request)
	{
	    return true;
	    //return get_current_user_id()?true:false;
	}
	
}
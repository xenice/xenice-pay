<?php

namespace xenice\pay\controllers;

use xenice\pay\utils\WechatPay;
use xenice\pay\utils\Alipay;
use xenice\pay\utils\Xunhupay;

use function xenice\pay\get as get;

class PayWaysController extends \WP_REST_Controller{
    
    protected $namespace = 'xe/v1';
    protected $rest_base = 'pay-ways';
    
    public function register_routes() {

        register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
			    [
    				'methods'             => \WP_REST_Server::READABLE,
    				'callback'            => array( $this, 'get' ),
    				'permission_callback' => [$this, 'isLogin'],
    				'args'                => $this->get_params(),
    			],
    			[
    				'methods'             => \WP_REST_Server::CREATABLE,
    				'callback'            => array( $this, 'pay' ),
    				'permission_callback' => [$this, 'isLogin'],
    				'args'                => $this->get_pay_params(),
    			],
			]
		    
		);
    }
	
	public function get_params() {
		$query_params = array(
		    'frontend'        => array(
				'description' => __( 'Frontend value.' ),
				'type'        => 'string',
				'required'    => true,
				'enum'        => array( 'h5', 'wxh5', 'weixin', 'qq', 'baidu'),
			),
		 );
		
		return $query_params;
	}
	
	public function get_pay_params() {
		$query_params = array(
		    'token'           => array(
				'description' => __( 'Pay token.' ),
				'type'        => 'string',
				'required'    => true,
				'arg_options' => array(
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'openid'           => array(
				'description' => __( 'Pay openid.' ),
				'type'        => 'string',
				'required'    => false,
				'arg_options' => array(
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'trade_order_id' => array(
    			'description' => __( 'Trade order id.' ),
    			'type'        => 'string',
    			'required'    => true,
    			'arg_options' => array(
    				'sanitize_callback' => 'sanitize_text_field',
    			),
    		),
    		
    		'total_fee' => array(
    			'description' => __( 'Total fee.' ),
    			'type'        => 'string',
    			'required'    => true,
    			'arg_options' => array(
    				'sanitize_callback' => 'sanitize_text_field',
    			),
    		),
    		'title' => array(
    			'description' => __( 'Title.' ),
    			'type'        => 'string',
    			'required'    => true,
    			'arg_options' => array(
    				'sanitize_callback' => 'sanitize_text_field',
    			),
    		),
            'return_url' => array(
    			'description' => __( 'Return URL.' ),
    			'type'        => 'string',
    			'required'    => false,
    			'arg_options' => array(
    				'sanitize_callback' => 'sanitize_text_field',
    			),
    		),
		 );
		
		return $query_params;
	}
	
	public function get($request)
	{
	    $pageways = [];
	    $frontend = $request['frontend'];
	    
	    if($frontend == 'weixin'){ // 微信小程序
	        if(get('enable_wechat_pay')){ // 微信小程序支付
                $pageways[] = [
                    'name'=>__('Wechat Pay', 'xenice-pay'),
                    'value'=>'wechat_mini',
                    'icon'=>'wechat'
                ];
            }
            if(get('enable_alipay')){
                if(get('enable_alipay_f2f')){
                    $pageways[] = [
                        'name'=>__('Alipay', 'xenice-pay'),
                        'value'=>'alipay_f2f',
                        'icon'=>'alipay'
                    ];
                }
            }
	    }
        elseif($frontend == 'wxh5'){ // 微信公众号
            if(get('enable_wechat_pay')){ // 微信jsapi支付
                $pageways[] = [
                    'name'=>__('Wechat Pay', 'xenice-pay'),
                    'value'=>'wechat_jsapi',
                    'icon'=>'wechat'
                ];
            }
            if(get('enable_wechat_xunhupay')){
                $pageways[] = [
                    'name'=>__('Wechat Pay', 'xenice-pay'),
                    'value'=>'wechat_xunhupay',
                    'icon'=>'wechat'
                ];
            }
            if(get('enable_alipay')){
                if(get('enable_alipay_f2f')){
                    $pageways[] = [
                        'name'=>__('Alipay', 'xenice-pay'),
                        'value'=>'alipay_f2f',
                        'icon'=>'alipay'
                    ];
                }
            }
        }
        elseif($frontend == 'h5'){ // H5页面
            if(get('enable_wechat_pay')){ // 微信H5支付
                $pageways[] = [
                    'name'=>__('Wechat Pay', 'xenice-pay'),
                    'value'=>'wechat_h5',
                    'icon'=>'wechat'
                ];
            }
            if(get('enable_alipay')){
                if(get('enable_alipay_f2f')){
                    $pageways[] = [
                        'name'=>__('Alipay', 'xenice-pay'),
                        'value'=>'alipay_f2f',
                        'icon'=>'alipay'
                    ];
                }
                else{
                    $pageways[] = [
                        'name'=>__('Alipay', 'xenice-pay'),
                        'value'=>'alipay_phone',
                        'icon'=>'alipay'
                    ];
                }
            }
            if(get('enable_wechat_xunhupay')){
                $pageways[] = [
                    'name'=>__('Wechat Pay', 'xenice-pay'),
                    'value'=>'wechat_xunhupay',
                    'icon'=>'wechat'
                ];
            }
        }

	    return rest_ensure_response($pageways);
    }
    
	public function pay($request)
	{
	    $data = null;

	    switch($request['pay_way']){
            case 'wechat_h5': // 微信H5支付
                $enable = get('enable_wehcat_pay');
                $appid = get('wehcat_pay_appid');
                $mch_id = get('wehcat_pay_mch_id');
                $key = get('wehcat_pay_key');
                
                if(!$enable){
                    return new \WP_Error(
        				'rest_wechat_pay_error',
        				'wechat_h5 is not enabled',
        				array( 'status' => 400 )
        			);
                }
        
                $args=[
                    'token'=> $request['token'],
                    'notify_url'=> home_url() . '/wp-json/sale/v1/pay/wepayNotify', //必须的，支付成功异步回调接口
                    //'return_url'=> $app_url . '/pages/order_detail/order_detail?order_id=' . $row['key'],//必须的，支付成功后的跳转地址
                    'trade_order_id'=> $request['trade_order_id'],
                    'total_fee' => $request['total_fee'],
                    'title'     => $request['title'],
                ];
                
                $data = (new WechatPay($appid, $mch_id, $key))->h5Pay($args);
                
                break;
            case 'wechat_jsapi': // 微信jsapi支付
                $enable = get('enable_wechat_pay');
                $appid = get('wechat_pay_appid');
                $mch_id = get('wechat_pay_mch_id');
                $key = get('wechat_pay_key');
                
                if(!$enable){
                     return new \WP_Error(
        				'rest_wechat_pay_error',
        				'wechat_jsapi is not enabled',
        				array( 'status' => 400 )
        			);
                }
        
                $args=[
                    'token'=> $request['token'],
                    'openid'=>$request['openid'],
                    'notify_url'=> home_url() . '/wp-json/sale/v1/pay/wepayNotify', //必须的，支付成功异步回调接口
                    //'return_url'=> $app_url . '/pages/order_detail/order_detail?order_id=' . $row['key'],//必须的，支付成功后的跳转地址
                    'trade_order_id'=> $request['trade_order_id'],
                    'total_fee' => $request['total_fee'],
                    'title'     => $request['title'],
                ];
                
                $data = (new WechatPay($appid, $mch_id, $key))->jsapiPay($args);
                break;
            case 'wechat_mini': // 微信小程序支付
                $enable = get('enable_wechat_pay');
                $appid = get('wechat_pay_appid');
                $mch_id = get('wechat_pay_mch_id');
                $key = get('wechat_pay_key');
                
                if(!$enable){
                     return new \WP_Error(
        				'rest_wechat_pay_error',
        				'wechat_mini is not enabled',
        				array( 'status' => 400 )
        			);
                }
        
                $args=[
                    'token'=> $request['token'],
                    'openid'=>$request['openid'],
                    'notify_url'=> home_url() . '/wp-json/sale/v1/pay/wepayNotify', //必须的，支付成功异步回调接口
                    //'return_url'=> $app_url . '/pages/order_detail/order_detail?order_id=' . $row['key'],//必须的，支付成功后的跳转地址
                    'trade_order_id'=> $request['trade_order_id'],
                    'total_fee' => $request['total_fee'],
                    'title'     => $request['title'],
                ];
                
                $data = (new WechatPay($appid, $mch_id, $key))->miniPay($args);
                break;
            case 'alipay_f2f': // 支付宝当面付
                $enable = get('enable_alipay');
                $appid = get('alipay_appid');
                $public_key = get('alipay_public_key');
                $private_key = get('alipay_private_key');
                
                if(!$enable){
                     return new \WP_Error(
        				'rest_alipay_error',
        				'alipay_f2f is not enabled',
        				array( 'status' => 400 )
        			);
                }
        
                $args=[
                    'token'=> $request['token'],
                    //'page_url'=> home_url() . '/wp-json/sale/v1/pay/page', // 扫码支付页面URL
                    'notify_url'=> home_url() . '/wp-json/xe/v1/pay-notify/alipay', //必须的，支付成功异步回调接口
                    //'return_url'=> $app_url . '/pages/order_detail/order_detail?order_id=' . $row['key'],//必须的，支付成功后的跳转地址
                    'trade_order_id'=> $request['trade_order_id'],
                    'total_fee' => $request['total_fee'],
                    'title'     => $request['title'],
                ];
                
                $data = (new Alipay($appid, $public_key, $private_key))->f2fPay($args);
                break;
            case 'alipay_phone': // 支付宝手机网站
                $enable = get('enable_alipay');
                $appid = get('alipay_appid');
                $public_key = get('alipay_public_key');
                $private_key = get('alipay_private_key');
                
                if(!$enable){
                     return new \WP_Error(
        				'rest_alipay_error',
        				'alipay_phone is not enabled',
        				array( 'status' => 400 )
        			);
                }
        
                $args=[
                    'token'=> $request['token'],
                    //'page_url'=> home_url() . '/wp-json/sale/v1/pay/page', // 扫码支付页面URL
                    'notify_url'=> home_url() . '/wp-json/xe/v1/pay-notify/alipay',//必须的，支付成功异步回调接口
                    'return_url'=> $request['return_url'],//必须的，支付成功后的跳转地址
                    'trade_order_id'=> $request['trade_order_id'],
                    'total_fee' => $request['total_fee'],
                    'title'     => $request['title'],
                ];
                
                $data = (new Alipay($appid, $public_key, $private_key))->phonePay($args);
                break;
            /*case 'alipay_phone':
                $order->insert($row);
                (new Alipay)->phonePay($data);
                break;
            case 'alipay_pc':
                $order->insert($row);
                (new Alipay)->pcPay($data);
                break;*/
            case 'xunhupay_wechat':
                
                $enable = get('enable_xunhupay_wechat');
                $appid = get('wechat_xunhupay_app_id');
                $secret = get('wechat_xunhupay_app_secret');
 
                if(!$enable){
                    return new \WP_Error(
        				'rest_alipay_error',
        				'xunhupay_wechat is not enabled',
        				array( 'status' => 400 )
        			);
                }
                $args=[
                    //'type'      => 'WAP', //H5支付固定值"WAP"，小程序支付固定值"JSAPI" （支付宝不需要此参数）
                    'plugins'   => $request['customer_id'],//必须的，根据自己需要自定义插件ID，唯一的，匹配[a-zA-Z\d\-_]+
                    'trade_order_id'=> $request['trade_order_id'], //必须的，网站订单ID，唯一的，匹配[a-zA-Z\d\-_]+
                    'payment'   => 'wechat',//必须的，支付接口标识：wechat(微信接口)|alipay(支付宝接口)
                    'total_fee' => $request['total_fee'],//人民币，单位精确到分(测试账户只支持0.1元内付款)
                    'title'     => $request['title'], //必须的，订单标题，长度32或以内
                    'notify_url'=> home_url() . '/wp-json/sale/v1/pay/xunhupayNotify', //必须的，支付成功异步回调接口
                    'return_url'=> $app_url . '/pages/order_detail/order_detail?order_id=' . $row['key'],//必须的，支付成功后的跳转地址
                    //'callback_url'=> $app_url . '/pages/order_detail/order_detail?order_id=' . $row['key'],//必须的，支付发起地址（未支付或支付失败，系统会会跳到这个地址让用户修改支付信息）
                    'nonce_str' => md5(uniqid(mt_rand(), true))//必须的，随机字符串，作用：1.避免服务器缓存，2.防止安全密钥被猜测出来*/
                ];
                
                //file_put_contents(__DIR__.'/1.txt', $args['return_url']);
                $data = (new Xunhupay($appid, $secret))->execute($args);
                break;
        }
        
        if(!$data){
            return new \WP_Error(
				'rest_pay_error',
				'can not get data',
				array( 'status' => 400 )
			);
        }
        
	    return rest_ensure_response($data);
	}
	
	public function isLogin($request)
	{
	    return get_current_user_id()?true:false;
	}
}
<?php
/**
 * @name        xenice options
 * @author      xenice <xenice@qq.com>
 * @version     1.0.0 2019-09-26
 * @link        http://www.xenice.com/
 * @package     xenice
 */
 
namespace xenice\pay;

class Config extends Options
{
    protected $key = 'pay';
    protected $name = ''; // Database option name
    protected $defaults = [];
    
    public function __construct()
    {
        $static_url = plugins_url('static/', __FILE__);
        
        $this->name = 'xenice_' . $this->key;
        $this->defaults[] = [
            'id'=>'pay',
            'name'=> __('pay','xenice-pay'),
            'submit'=>__('Save Changes','xenice-pay'),
            'title'=> __('Pay Settings', 'xenice-pay'),
            'tabs' => [
                [
                    'id' => 'wechat_pay',
                    'title' => __('Wechat Pay', 'xenice-pay'),
                    'fields'=>[
                        [
                            'name'=>__('Wechat Pay', 'xenice-pay'),
                            'desc' => sprintf(__('<a href="%s" target="_blank">Apply for</a> payment interface. <a onclick="xenice_pay_copy(this);return false;" href="%s/wp-json/xe/v1/pay/wepayNotify">Copy</a> Callback URL.', 'xenice-pay'),'https://pay.weixin.qq.com/',home_url()),
                            'fields'=>[
                                [
                                    'id'   => 'enable_wechat_pay',
                                    'type'  => 'checkbox',
                                    'value' => false,
                                    'label'  => __('Enable', 'xenice-pay')
                                ],
                                [
                                    'id'   => 'wechat_pay_appid',
                                    'label' => __('APP ID', 'xenice-pay'),
                                    'type'  => 'text',
                                    'value' => '',
                                ],
                                [
                                    'id'   => 'wechat_pay_mch_id',
                                    'label' => __('Merchant ID', 'xenice-pay'),
                                    'type'  => 'text',
                                    'value' => '',
                                ],
                                [
                                    'id'   => 'wechat_pay_mch_key',
                                    'label' =>  __('Merchant key', 'xenice-pay'),
                                    'type'  => 'text',
                                    'value' => '',
                                ],
                            ]
                        ],
                    ]
                ], #wechat pay
                [
                    'id' => 'alipay',
                    'title' => __('Alipay', 'xenice-pay'),
                    'fields'=>[
                        [
                            'name'=>__('Alipay', 'xenice-pay'),
                            'desc' => sprintf(__('<a href="%s" target="_blank">Apply for</a> payment interface. <a onclick="xenice_pay_copy(this);return false;" href="%s/wp-json/xe/v1/pay/alipayNotify">Copy</a> Callback URL.'),'https://open.alipay.com/',home_url()),
                            'fields'=>[
                                [
                                    'id'   => 'enable_alipay',
                                    'type'  => 'checkbox',
                                    'value' => false,
                                    'label'  => __('Enable', 'xenice-pay')
                                ],
                                [
                                    'id'   => 'enable_alipay_f2f',
                                    'type'  => 'checkbox',
                                    'value' => false,
                                    'label'  => __('Enable face to face payment', 'xenice-pay')
                                ],
                                [
                                    'id'   => 'alipay_appid',
                                    'label' => __('APP ID', 'xenice-pay'),
                                    'type'  => 'text',
                                    'value' => '',
                                ],
                                [
                                    'id'   => 'alipay_public_key',
                                    'label' => __('Alipay public key', 'xenice-pay'),
                                    'type'  => 'textarea',
                                    'rows' => 6,
                                    'value' => '',
                                ],
                                [
                                    'id'   => 'alipay_private_key',
                                    'label' => __('App private key', 'xenice-pay'),
                                    'type'  => 'textarea',
                                    'rows' => 6,
                                    'value' => '',
                                ],
                            ]
                        ],
                    ], 
                ], // # alipay
                [
                    'id' => 'xunhupay',
                    'title' => __('Xunhupay', 'xenice-pay'),
                    'fields'=>[
                        [
                            'name' => __('Xunhupay'),
                            'desc' => sprintf(__('<a href="%s" target="_blank">Apply for</a> xunhupay official wechat personal payment account.',  'xenice-pay'), 'https://www.xunhupay.com/'),
                            'fields'=>[
                                [
                                    'id'   => 'enable_wechat_xunhupay',
                                    'type'  => 'checkbox',
                                    'value' => false,
                                    'label'  => __('Enable', 'xenice-pay')
                                ],
                                [
                                    'id'   => 'wechat_xunhupay_app_id',
                                    'type'  => 'text',
                                    'value' => '',
                                    'label'  => __('APP ID', 'xenice-pay')
                                ],
                                [
                                    'id'   => 'wechat_xunhupay_app_secret',
                                    'type'  => 'text',
                                    'value' => '',
                                    'label'  => __('APP Secret', 'xenice-pay')
                                ],
                            ]  
                        ],
                    ]
                ],
                /*
                [
                    'id' => 'global',
                    'title' => __('Global', 'xenice-auth'),
                    'fields'=>[
                        [
                            'id'   => 'enable_email_verification',
                            'name' => __('Enable email verification', 'xenice-auth'),
                            'label' => __('Enable', 'xenice-auth'),
                            'desc' => __('An email verification code is required for registration.', 'xenice-auth'),
                            'type'  => 'checkbox',
                            'value' => true,
                        ],
                    ]
                ],*/
                
            ]
        ];
	    parent::__construct();
    }

}
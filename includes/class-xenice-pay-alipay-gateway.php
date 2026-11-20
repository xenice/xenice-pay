<?php
/**
 * 支付宝网关
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenice_Pay_Alipay_Gateway extends Xenice_Pay_Gateway {

	/**
	 * 收银台品牌
	 *
	 * @var string
	 */
	protected $brand = 'alipay';

	/**
	 * 设置网关属性
	 */
	protected function setup_properties() {
		$this->id                 = 'xenice_pay_alipay';
		$this->icon               = XENICE_PAY_URL . 'assets/images/alipay.svg';
		$this->method_title       = '支付宝';
		//$this->method_description = '支付宝扫码支付（当面付）与手机网站支付，使用公钥模式 RSA2 签名。';
		$this->order_button_text  = '前往支付';
	}

	/**
	 * 配置项
	 */
	public function init_form_fields() {
		$common = $this->common_form_fields();

		$this->form_fields = array_merge(
			array_intersect_key( $common, array_flip( array( 'enabled', 'title', 'description' ) ) ),
			array(
				'app_id'               => array(
					'title'       => 'App ID',
					'type'        => 'text',
					'description' => '支付宝开放平台应用的 APPID',
					'default'     => '',
					'desc_tip'    => true,
				),
				'merchant_private_key' => array(
					'title'       => '应用私钥',
					'type'        => 'textarea',
					'description' => '粘贴应用私钥内容（支持完整 PEM，或仅粘贴 Base64 正文）。',
					'default'     => '',
				),
				'alipay_public_key'    => array(
					'title'       => '支付宝公钥',
					'type'        => 'textarea',
					'description' => '公钥模式下从开放平台获取的「支付宝公钥」，注意不是应用公钥。',
					'default'     => '',
				),
				'payment_mode'         => array(
					'title'       => '支付模式',
					'type'        => 'select',
					'description' => '扫码支付展示二维码，适合已开通当面付；跳转收银台适合电脑网站支付与手机网站支付。',
					'default'     => 'qr',
					'options'     => array(
						'qr'       => '扫码支付（当面付）',
						'redirect' => '跳转到支付宝收银台',
					),
					'desc_tip'    => true,
				),
				'sandbox'              => array(
					'title'   => '沙箱环境',
					'type'    => 'checkbox',
					'label'   => '使用支付宝开放平台沙箱',
					'default' => 'no',
				),
			),
			array_intersect_key( $common, array_flip( array( 'exchange_rate', 'debug' ) ) )
		);
	}

	/**
	 * 创建接口实例
	 *
	 * @return Xenice_Pay_Alipay_API|null
	 */
	private function api() {
		$api = new Xenice_Pay_Alipay_API(
			$this->get_option( 'app_id' ),
			$this->get_option( 'merchant_private_key' ),
			$this->get_option( 'alipay_public_key' ),
			'yes' === $this->get_option( 'sandbox' )
		);

		return $api->is_configured() ? $api : null;
	}

	/**
	 * 发起支付
	 *
	 * @param int $order_id 订单 ID
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$api   = $this->api();

		if ( ! $order ) {
			wc_add_notice( '订单不存在。', 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		if ( ! $api ) {
			wc_add_notice( '支付宝配置不完整，暂时无法使用该支付方式。', 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		$mode     = $this->get_option( 'payment_mode', 'qr' );
		$trade_no = $this->trade_no( $order );
		$notify   = $this->notify_url();
		$return   = $this->get_return_url( $order );

		$biz = array(
			'out_trade_no' => $trade_no,
			'total_amount' => $this->amount_string( $order ),
			'subject'      => $this->order_title( $order, 120 ),
		);

		// 跳转模式：电脑网站支付 / 手机网站支付
		if ( 'redirect' === $mode ) {
			if ( wp_is_mobile() ) {
				$biz['product_code'] = 'QUICK_WAP_WAY';
				$url                 = $api->wap_pay_url( $biz, $notify, $return );
			} else {
				$biz['product_code'] = 'FAST_INSTANT_TRADE_PAY';
				$url                 = $api->page_pay_url( $biz, $notify, $return );
			}

			if ( is_wp_error( $url ) ) {
				$this->log( '跳转支付失败：' . $url->get_error_message() );
				wc_add_notice( '发起支付失败：' . $url->get_error_message(), 'error' );
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}

			return array(
				'result'   => 'success',
				'redirect' => $url,
			);
		}

		// 扫码模式下，手机端优先走手机网站支付
		if ( wp_is_mobile() ) {
			$biz['product_code'] = 'QUICK_WAP_WAY';
			$url                 = $api->wap_pay_url( $biz, $notify, $return );

			if ( ! is_wp_error( $url ) ) {
				return array(
					'result'   => 'success',
					'redirect' => $url,
				);
			}
		}

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * 生成收款二维码
	 *
	 * @param WC_Order $order 订单
	 * @param bool     $force 是否强制重新下单
	 * @return array|WP_Error
	 */
	protected function prepare_payment( $order, $force = false ) {
		$api = $this->api();

		if ( ! $api ) {
			return new WP_Error( 'xenice_pay_alipay', '支付宝配置不完整，请联系商家。' );
		}

		$trade_no = $this->trade_no( $order, $force );

		$qr = $api->precreate(
			array(
				'out_trade_no'    => $trade_no,
				'total_amount'    => $this->amount_string( $order ),
				'subject'         => $this->order_title( $order, 120 ),
				'timeout_express' => '30m',
			),
			$this->notify_url()
		);

		if ( is_wp_error( $qr ) ) {
			return $qr;
		}

		$order->update_meta_data( '_xenice_pay_qr', $qr );
		$order->save();

		$this->log( '订单 ' . $order->get_id() . ' 生成支付宝二维码成功，商户订单号 ' . $trade_no );

		return array(
			'code' => $qr,
			'tip'  => '请使用支付宝 App 扫码完成支付，支付后页面会自动跳转。',
		);
	}

	/**
	 * 异步通知
	 */
	public function notify() {
		nocache_headers();
		
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        
        wc_get_logger()->info(
            '支付宝回调参数：' . wp_json_encode( $post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            array( 'source' => $this->id )
        );
        
		$api  = $this->api();

		if ( ! $api ) {
			$this->log( '异步通知：支付宝配置不完整' );
			$this->reply( 'fail' );
		}

		if ( empty( $post['out_trade_no'] ) || empty( $post['sign'] ) ) {
			$this->log( '异步通知：缺少必要参数' );
			$this->reply( 'fail' );
		}

		if ( ! $api->verify( $post ) ) {
			$this->log( '异步通知验签失败：' . $api->get_error() );
			$this->reply( 'fail' );
		}

		if ( (string) $this->get_option( 'app_id' ) !== (string) ( $post['app_id'] ?? '' ) ) {
			$this->log( '异步通知：app_id 不匹配' );
			$this->reply( 'fail' );
		}

		$trade_status = isset( $post['trade_status'] ) ? (string) $post['trade_status'] : '';

		if ( ! in_array( $trade_status, array( 'TRADE_SUCCESS', 'TRADE_FINISHED' ), true ) ) {
			$this->log( '异步通知：交易状态为 ' . $trade_status . '，暂不处理' );
			$this->reply( 'success' );
		}

		$out_trade_no = sanitize_text_field( $post['out_trade_no'] );
		$order        = $this->order_by_trade_no( $out_trade_no );

		if ( ! $order ) {
			$this->log( '异步通知：订单不存在或支付方式不符，商户订单号 ' . $out_trade_no );
			$this->reply( 'fail' );
		}

		$paid = isset( $post['total_amount'] ) ? (float) $post['total_amount'] : 0;

		if ( abs( $paid - (float) $this->amount_string( $order ) ) > 0.01 ) {
			$this->log( '异步通知：金额不一致，应付 ' . $this->amount_string( $order ) . '，实付 ' . $paid );
			$order->update_status( 'on-hold', '支付宝通知金额与订单金额不一致，请人工核对。' );
			$this->reply( 'fail' );
		}

		if ( $order->is_paid() ) {
			$this->reply( 'success' );
		}

		$transaction_id = isset( $post['trade_no'] ) ? sanitize_text_field( $post['trade_no'] ) : '';

		$order->set_transaction_id( $transaction_id );
		$order->payment_complete( $transaction_id );
		$order->add_order_note( sprintf( '支付宝支付成功，支付宝交易号：%s', $transaction_id ) );

		$this->log( '订单 ' . $order->get_id() . ' 支付完成，交易号 ' . $transaction_id );

		$this->reply( 'success' );
	}

	/**
	 * 输出应答
	 *
	 * @param string $text 应答内容
	 */
	private function reply( $text ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $text );
		exit;
	}
}
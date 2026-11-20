<?php
/**
 * 微信支付网关
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenice_Pay_Wechat_Gateway extends Xenice_Pay_Gateway {

	/**
	 * 收银台品牌
	 *
	 * @var string
	 */
	protected $brand = 'wechat';

	/**
	 * 设置网关属性
	 */
	protected function setup_properties() {
		$this->id                 = 'xenice_pay_wechat';
		$this->icon               = XENICE_PAY_URL . 'assets/images/wechat.svg';
		$this->method_title       = '微信支付';
		//$this->method_description = '微信支付 API v3：Native 扫码支付，可选 H5 支付。自建签名与验签，不依赖官方 SDK。';
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
					'title'       => 'AppID',
					'type'        => 'text',
					'description' => '已与商户号绑定的 AppID（公众号或开放平台应用）。',
					'default'     => '',
					'desc_tip'    => true,
				),
				'mch_id'               => array(
					'title'       => '商户号',
					'type'        => 'text',
					'description' => '微信支付商户号 mchid。',
					'default'     => '',
					'desc_tip'    => true,
				),
				'cert_serial_no'       => array(
					'title'       => '商户证书序列号',
					'type'        => 'text',
					'description' => '商户 API 证书的序列号，可在商户平台「API 安全」中查看。',
					'default'     => '',
					'desc_tip'    => true,
				),
				'merchant_private_key' => array(
					'title'       => '商户私钥',
					'type'        => 'textarea',
					'description' => '粘贴 apiclient_key.pem 的内容（支持完整 PEM，或仅粘贴 Base64 正文）。',
					'default'     => '',
				),
				'api_v3_key'           => array(
					'title'       => 'APIv3 密钥',
					'type'        => 'password',
					'description' => '32 位 APIv3 密钥，用于解密回调数据。',
					'default'     => '',
					'desc_tip'    => true,
				),
				'wechat_public_key'    => array(
					'title'       => '微信支付公钥',
					'type'        => 'textarea',
					'description' => '公钥模式下用于校验回调签名，粘贴 pub_key.pem 内容。',
					'default'     => '',
				),
				'wechat_public_key_id' => array(
					'title'       => '微信支付公钥 ID',
					'type'        => 'text',
					'description' => '形如 PUB_KEY_ID_0115 的公钥 ID，用于识别回调签名来源。',
					'default'     => '',
					'desc_tip'    => true,
				),
				'enable_h5'            => array(
					'title'   => '手机 H5 支付',
					'type'    => 'checkbox',
					'label'   => '手机浏览器访问时使用 H5 支付（需商户号开通 H5 支付并报备域名）',
					'default' => 'no',
				),
			),
			array_intersect_key( $common, array_flip( array( 'exchange_rate', 'debug' ) ) )
		);
	}

	/**
	 * 创建接口实例
	 *
	 * @return Xenice_Pay_Wechat_API|null
	 */
	private function api() {
		$api = new Xenice_Pay_Wechat_API(
			array(
				'app_id'        => $this->get_option( 'app_id' ),
				'mch_id'        => $this->get_option( 'mch_id' ),
				'serial_no'     => $this->get_option( 'cert_serial_no' ),
				'private_key'   => $this->get_option( 'merchant_private_key' ),
				'api_v3_key'    => $this->get_option( 'api_v3_key' ),
				'public_key'    => $this->get_option( 'wechat_public_key' ),
				'public_key_id' => $this->get_option( 'wechat_public_key_id' ),
			)
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
			wc_add_notice( '微信支付配置不完整，暂时无法使用该支付方式。', 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		if ( 'yes' === $this->get_option( 'enable_h5' ) && wp_is_mobile() ) {
			$trade_no = $this->trade_no( $order );
			$url      = $api->h5(
				$trade_no,
				$this->order_title( $order, 120 ),
				$this->amount_cents( $order ),
				$this->notify_url(),
				$this->client_ip()
			);

			if ( is_wp_error( $url ) ) {
				$this->log( 'H5 下单失败，改为扫码：' . $url->get_error_message() );
			} else {
				$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'redirect_url=' . rawurlencode( $this->get_return_url( $order ) );

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
			return new WP_Error( 'xenice_pay_wechat', '微信支付配置不完整，请联系商家。' );
		}

		$trade_no = $this->trade_no( $order, $force );

		$code = $api->native(
			$trade_no,
			$this->order_title( $order, 120 ),
			$this->amount_cents( $order ),
			$this->notify_url(),
			(string) $order->get_id()
		);

		if ( is_wp_error( $code ) ) {
			return $code;
		}

		$order->update_meta_data( '_xenice_pay_qr', $code );
		$order->save();

		$this->log( '订单 ' . $order->get_id() . ' 生成微信支付二维码成功，商户订单号 ' . $trade_no );

		$tip = '请使用微信扫一扫完成支付，支付后页面会自动跳转。';

		if ( wp_is_mobile() ) {
			$tip .= ' 在微信内可长按二维码识别支付。';
		}

		return array(
			'code' => $code,
			'tip'  => $tip,
		);
	}

	/**
	 * 异步通知
	 */
	public function notify() {
		nocache_headers();

		$body    = file_get_contents( 'php://input' );
		$headers = array(
			'timestamp' => isset( $_SERVER['HTTP_WECHATPAY_TIMESTAMP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_WECHATPAY_TIMESTAMP'] ) ) : '',
			'nonce'     => isset( $_SERVER['HTTP_WECHATPAY_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_WECHATPAY_NONCE'] ) ) : '',
			'signature' => isset( $_SERVER['HTTP_WECHATPAY_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_WECHATPAY_SIGNATURE'] ) ) : '',
			'serial'    => isset( $_SERVER['HTTP_WECHATPAY_SERIAL'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_WECHATPAY_SERIAL'] ) ) : '',
		);

		$api = $this->api();

		if ( ! $api ) {
			$this->log( '异步通知：微信支付配置不完整' );
			$this->reply( 500, 'FAIL', '配置不完整' );
		}

		if ( ! $api->verify( $body, $headers ) ) {
			$this->log( '异步通知验签失败：' . $api->get_error() );
			$this->reply( 401, 'FAIL', '签名校验失败' );
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['resource'] ) ) {
			$this->log( '异步通知：数据格式异常' );
			$this->reply( 400, 'FAIL', '数据格式异常' );
		}

		$decrypted = $api->decrypt( $data['resource'] );

		if ( is_wp_error( $decrypted ) ) {
			$this->log( '异步通知解密失败：' . $decrypted->get_error_message() );
			$this->reply( 400, 'FAIL', '解密失败' );
		}

		if ( 'SUCCESS' !== ( $decrypted['trade_state'] ?? '' ) ) {
			$this->reply( 200, 'SUCCESS', '成功' );
		}

		if ( (string) $this->get_option( 'mch_id' ) !== (string) ( $decrypted['mchid'] ?? '' ) ) {
			$this->log( '异步通知：商户号不匹配' );
			$this->reply( 400, 'FAIL', '商户号不匹配' );
		}

		$out_trade_no = isset( $decrypted['out_trade_no'] ) ? sanitize_text_field( $decrypted['out_trade_no'] ) : '';
		$order        = $this->order_by_trade_no( $out_trade_no );

		if ( ! $order ) {
			$this->log( '异步通知：订单不存在或支付方式不符，商户订单号 ' . $out_trade_no );
			$this->reply( 400, 'FAIL', '订单不存在' );
		}

		$total = isset( $decrypted['amount']['total'] ) ? (int) $decrypted['amount']['total'] : 0;

		if ( $total !== $this->amount_cents( $order ) ) {
			$this->log( '异步通知：金额不一致，应付 ' . $this->amount_cents( $order ) . ' 分，实付 ' . $total . ' 分' );
			$order->update_status( 'on-hold', '微信支付通知金额与订单金额不一致，请人工核对。' );
			$this->reply( 400, 'FAIL', '金额不一致' );
		}

		if ( $order->is_paid() ) {
			$this->reply( 200, 'SUCCESS', '成功' );
		}

		$transaction_id = isset( $decrypted['transaction_id'] ) ? sanitize_text_field( $decrypted['transaction_id'] ) : '';

		$order->set_transaction_id( $transaction_id );
		$order->payment_complete( $transaction_id );
		$order->add_order_note( sprintf( '微信支付成功，微信支付订单号：%s', $transaction_id ) );

		$this->log( '订单 ' . $order->get_id() . ' 支付完成，交易号 ' . $transaction_id );

		$this->reply( 200, 'SUCCESS', '成功' );
	}

	/**
	 * 客户端 IP
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '127.0.0.1';
		}

		return $ip;
	}

	/**
	 * 输出应答
	 *
	 * @param int    $status  HTTP 状态码
	 * @param string $code    SUCCESS / FAIL
	 * @param string $message 说明
	 */
	private function reply( $status, $code, $message ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'code'    => $code,
				'message' => $message,
			)
		);
		exit;
	}
}
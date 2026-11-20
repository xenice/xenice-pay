<?php
/**
 * 微信支付 API v3 直连接口
 *
 * 自行实现请求签名、通知验签与 AES-256-GCM 解密，不依赖任何第三方 SDK。
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenice_Pay_Wechat_API {

	const BASE_URL = 'https://api.mch.weixin.qq.com';

	private $app_id;
	private $mch_id;
	private $serial_no;
	private $private_key;
	private $api_v3_key;
	private $public_key;
	private $public_key_id;
	private $error      = '';
	private $private_pem = null;
	private $public_pem  = null;

	/**
	 * 构造函数
	 *
	 * @param array $config 配置：app_id、mch_id、serial_no、private_key、api_v3_key、public_key、public_key_id
	 */
	public function __construct( array $config ) {
		$this->app_id        = trim( (string) ( $config['app_id'] ?? '' ) );
		$this->mch_id        = trim( (string) ( $config['mch_id'] ?? '' ) );
		$this->serial_no     = trim( (string) ( $config['serial_no'] ?? '' ) );
		$this->private_key   = trim( (string) ( $config['private_key'] ?? '' ) );
		$this->api_v3_key    = trim( (string) ( $config['api_v3_key'] ?? '' ) );
		$this->public_key    = trim( (string) ( $config['public_key'] ?? '' ) );
		$this->public_key_id = trim( (string) ( $config['public_key_id'] ?? '' ) );
	}

	/**
	 * 配置是否完整
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->app_id
			&& '' !== $this->mch_id
			&& '' !== $this->serial_no
			&& '' !== $this->private_key
			&& 32 === strlen( $this->api_v3_key );
	}

	/**
	 * 取最近一次错误
	 *
	 * @return string
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Native 下单，返回二维码内容
	 *
	 * @param string $out_trade_no 商户订单号
	 * @param string $description  商品描述
	 * @param int    $total        金额（分）
	 * @param string $notify_url   通知地址
	 * @param string $attach       附加数据
	 * @return string|WP_Error
	 */
	public function native( $out_trade_no, $description, $total, $notify_url, $attach = '' ) {
		$payload = array(
			'appid'        => $this->app_id,
			'mchid'        => $this->mch_id,
			'description'  => $description,
			'out_trade_no' => $out_trade_no,
			'notify_url'   => $notify_url,
			'amount'       => array(
				'total'    => (int) $total,
				'currency' => 'CNY',
			),
		);

		if ( '' !== $attach ) {
			$payload['attach'] = $attach;
		}

		$response = $this->request( 'POST', '/v3/pay/transactions/native', '', $this->encode( $payload ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['code_url'] ) ) {
			return new WP_Error( 'xenice_pay_wechat', '微信支付未返回二维码内容' );
		}

		return $response['code_url'];
	}

	/**
	 * H5 下单，返回跳转地址
	 *
	 * @param string $out_trade_no 商户订单号
	 * @param string $description  商品描述
	 * @param int    $total        金额（分）
	 * @param string $notify_url   通知地址
	 * @param string $client_ip    客户端 IP
	 * @return string|WP_Error
	 */
	public function h5( $out_trade_no, $description, $total, $notify_url, $client_ip ) {
		$payload = array(
			'appid'        => $this->app_id,
			'mchid'        => $this->mch_id,
			'description'  => $description,
			'out_trade_no' => $out_trade_no,
			'notify_url'   => $notify_url,
			'amount'       => array(
				'total'    => (int) $total,
				'currency' => 'CNY',
			),
			'scene_info'   => array(
				'payer_client_ip' => $client_ip,
				'h5_info'         => array( 'type' => 'Wap' ),
			),
		);

		$response = $this->request( 'POST', '/v3/pay/transactions/h5', '', $this->encode( $payload ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['h5_url'] ) ) {
			return new WP_Error( 'xenice_pay_wechat', '微信支付未返回支付地址' );
		}

		return $response['h5_url'];
	}

	/**
	 * 查询订单
	 *
	 * @param string $out_trade_no 商户订单号
	 * @return array|WP_Error
	 */
	public function query( $out_trade_no ) {
		$path = '/v3/pay/transactions/out-trade-no/' . rawurlencode( $out_trade_no );

		return $this->request( 'GET', $path, 'mchid=' . rawurlencode( $this->mch_id ), '' );
	}

	/**
	 * 关闭订单
	 *
	 * @param string $out_trade_no 商户订单号
	 * @return array|WP_Error
	 */
	public function close( $out_trade_no ) {
		$path = '/v3/pay/transactions/out-trade-no/' . rawurlencode( $out_trade_no ) . '/close';

		return $this->request( 'POST', $path, '', $this->encode( array( 'mchid' => $this->mch_id ) ) );
	}

	/**
	 * 校验回调签名
	 *
	 * @param string $body    原始请求体
	 * @param array  $headers 请求头（timestamp、nonce、signature、serial）
	 * @return bool
	 */
	public function verify( $body, array $headers ) {
		$timestamp = isset( $headers['timestamp'] ) ? (string) $headers['timestamp'] : '';
		$nonce     = isset( $headers['nonce'] ) ? (string) $headers['nonce'] : '';
		$signature = isset( $headers['signature'] ) ? (string) $headers['signature'] : '';

		if ( '' === $timestamp || '' === $nonce || '' === $signature ) {
			$this->error = '回调缺少签名头';
			return false;
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			$this->error = '回调时间戳超出允许范围';
			return false;
		}

		$key = $this->load_public_key();

		if ( ! $key ) {
			return false;
		}

		$message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
		$result  = openssl_verify( $message, base64_decode( $signature ), $key, OPENSSL_ALGO_SHA256 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( 1 !== $result ) {
			$this->error = '回调签名校验失败';
			return false;
		}

		return true;
	}

	/**
	 * 解密回调中的密文
	 *
	 * @param array $resource resource 节点
	 * @return array|WP_Error
	 */
	public function decrypt( array $resource ) {
		if ( empty( $resource['ciphertext'] ) || empty( $resource['nonce'] ) ) {
			return new WP_Error( 'xenice_pay_wechat', '回调数据不完整' );
		}

		if ( 32 !== strlen( $this->api_v3_key ) ) {
			return new WP_Error( 'xenice_pay_wechat', 'APIv3 密钥必须为 32 位' );
		}

		$ciphertext = base64_decode( $resource['ciphertext'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( strlen( $ciphertext ) < 17 ) {
			return new WP_Error( 'xenice_pay_wechat', '回调密文格式错误' );
		}

		$tag       = substr( $ciphertext, -16 );
		$data      = substr( $ciphertext, 0, -16 );
		$decrypted = openssl_decrypt( $data, 'aes-256-gcm', $this->api_v3_key, OPENSSL_RAW_DATA, $resource['nonce'], $tag );

		if ( false === $decrypted ) {
			return new WP_Error( 'xenice_pay_wechat', '回调数据解密失败，请检查 APIv3 密钥' );
		}

		$decoded = json_decode( $decrypted, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'xenice_pay_wechat', '回调数据解密后无法解析' );
		}

		return $decoded;
	}

	/**
	 * 发起请求
	 *
	 * @param string $method 请求方法
	 * @param string $path   路径
	 * @param string $query  查询串
	 * @param string $body   请求体
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $query, $body ) {
		$url       = self::BASE_URL . $path . ( '' !== $query ? '?' . $query : '' );
		$sign_path = $path . ( '' !== $query ? '?' . $query : '' );
		$auth      = $this->authorization( $method, $sign_path, $body );

		if ( '' === $auth ) {
			return new WP_Error( 'xenice_pay_wechat', $this->error );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => 20,
				'headers' => array(
					'Authorization' => $auth,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Xenice-Pay/' . XENICE_PAY_VERSION,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'xenice_pay_wechat', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : array();
		}

		$message = ( is_array( $data ) && ! empty( $data['message'] ) ) ? $data['message'] : '请求失败（HTTP ' . $code . '）';

		if ( is_array( $data ) && ! empty( $data['code'] ) ) {
			$message .= '（' . $data['code'] . '）';
		}

		$this->error = $message;

		return new WP_Error( 'xenice_pay_wechat', $message );
	}

	/**
	 * 构造 Authorization 头
	 *
	 * @param string $method    请求方法
	 * @param string $sign_path 参与签名的路径（含查询串）
	 * @param string $body      请求体
	 * @return string
	 */
	private function authorization( $method, $sign_path, $body ) {
		$timestamp = (string) time();
		$nonce     = strtoupper( bin2hex( random_bytes( 16 ) ) );
		$message   = $method . "\n" . $sign_path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";

		$key = $this->load_private_key();

		if ( ! $key ) {
			return '';
		}

		$signature = '';

		if ( ! openssl_sign( $message, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			$this->error = '生成请求签名失败，请检查商户私钥';
			return '';
		}

		return sprintf(
			'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
			$this->mch_id,
			$nonce,
			base64_encode( $signature ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$timestamp,
			$this->serial_no
		);
	}

	/**
	 * JSON 编码
	 *
	 * @param array $payload 数据
	 * @return string
	 */
	private function encode( array $payload ) {
		return wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * 载入商户私钥
	 *
	 * @return mixed
	 */
	private function load_private_key() {
		if ( null !== $this->private_pem ) {
			return $this->private_pem;
		}

		$key = $this->private_key;

		if ( false === strpos( $key, 'BEGIN' ) ) {
			$body = preg_replace( '/\s+/', '', $key );
			$key  = "-----BEGIN PRIVATE KEY-----\n" . chunk_split( $body, 64, "\n" ) . "-----END PRIVATE KEY-----\n";
		}

		$resource = @openssl_pkey_get_private( $key ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $resource || null === $resource ) {
			$this->private_pem = false;
			$this->error       = '商户私钥无效，请粘贴 apiclient_key.pem 的内容';
			return false;
		}

		$this->private_pem = $resource;

		return $resource;
	}

	/**
	 * 载入微信支付公钥
	 *
	 * @return mixed
	 */
	private function load_public_key() {
		if ( null !== $this->public_pem ) {
			return $this->public_pem;
		}

		if ( '' === $this->public_key ) {
			$this->public_pem = false;
			$this->error      = '未配置微信支付公钥，无法校验回调';
			return false;
		}

		$key = $this->public_key;

		if ( false === strpos( $key, 'BEGIN' ) ) {
			$body = preg_replace( '/\s+/', '', $key );
			$key  = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( $body, 64, "\n" ) . "-----END PUBLIC KEY-----\n";
		}

		$resource = @openssl_pkey_get_public( $key ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $resource || null === $resource ) {
			$this->public_pem = false;
			$this->error      = '微信支付公钥无效';
			return false;
		}

		$this->public_pem = $resource;

		return $resource;
	}
}

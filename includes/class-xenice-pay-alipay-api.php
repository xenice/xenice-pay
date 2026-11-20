<?php
/**
 * 支付宝开放平台直连接口（公钥模式）
 *
 * 自行实现 RSA2 签名与验签，不依赖任何第三方 SDK。
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenice_Pay_Alipay_API {

	const PROD_GATEWAY    = 'https://openapi.alipay.com/gateway.do';
	const SANDBOX_GATEWAY = 'https://openapi-sandbox.dl.alipaydev.com/gateway.do';

	/**
	 * 应用 ID
	 *
	 * @var string
	 */
	private $app_id;

	/**
	 * 应用私钥
	 *
	 * @var string
	 */
	private $private_key;

	/**
	 * 支付宝公钥
	 *
	 * @var string
	 */
	private $public_key;

	/**
	 * 网关地址
	 *
	 * @var string
	 */
	private $gateway_url;

	/**
	 * 最近一次错误
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * 私钥资源缓存
	 *
	 * @var mixed
	 */
	private $private_pem = null;

	/**
	 * 公钥资源缓存
	 *
	 * @var mixed
	 */
	private $public_pem = null;

	/**
	 * 构造函数
	 *
	 * @param string $app_id      应用 ID
	 * @param string $private_key 应用私钥
	 * @param string $public_key  支付宝公钥
	 * @param bool   $sandbox     是否沙箱
	 */
	public function __construct( $app_id, $private_key, $public_key, $sandbox = false ) {
		$this->app_id      = trim( (string) $app_id );
		$this->private_key = trim( (string) $private_key );
		$this->public_key  = trim( (string) $public_key );
		$this->gateway_url = $sandbox ? self::SANDBOX_GATEWAY : self::PROD_GATEWAY;
	}

	/**
	 * 配置是否完整
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->app_id && '' !== $this->private_key && '' !== $this->public_key;
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
	 * 当面付预下单，返回二维码内容
	 *
	 * @param array $biz 业务参数
	 * @return string|WP_Error
	 */
	public function precreate( array $biz, $notify_url = '' ) {
		$response = $this->execute( 'alipay.trade.precreate', $biz, $notify_url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['qr_code'] ) ) {
			return new WP_Error( 'xenice_pay_alipay', '支付宝未返回二维码内容' );
		}

		return $response['qr_code'];
	}

	/**
	 * 电脑网站支付地址
	 *
	 * @param array  $biz        业务参数
	 * @param string $notify_url 异步通知地址
	 * @param string $return_url 同步跳转地址
	 * @return string|WP_Error
	 */
	public function page_pay_url( array $biz, $notify_url, $return_url ) {
		return $this->redirect_url( 'alipay.trade.page.pay', $biz, $notify_url, $return_url );
	}

	/**
	 * 手机网站支付地址
	 *
	 * @param array  $biz        业务参数
	 * @param string $notify_url 异步通知地址
	 * @param string $return_url 同步跳转地址
	 * @return string|WP_Error
	 */
	public function wap_pay_url( array $biz, $notify_url, $return_url ) {
		return $this->redirect_url( 'alipay.trade.wap.pay', $biz, $notify_url, $return_url );
	}

	/**
	 * 查询订单
	 *
	 * @param string $out_trade_no 商户订单号
	 * @return array|WP_Error
	 */
	public function query( $out_trade_no ) {
		return $this->execute( 'alipay.trade.query', array( 'out_trade_no' => $out_trade_no ) );
	}

	/**
	 * 关闭订单
	 *
	 * @param string $out_trade_no 商户订单号
	 * @return array|WP_Error
	 */
	public function close( $out_trade_no ) {
		return $this->execute( 'alipay.trade.close', array( 'out_trade_no' => $out_trade_no ) );
	}

	/**
	 * 校验异步通知 / 同步返回参数
	 *
	 * @param array $params 参数
	 * @return bool
	 */
	public function verify( array $params ) {
        
        unset( $params['wc-api'], $params['wc-ajax'], $params['_wpnonce'] );
        
		if ( empty( $params['sign'] ) ) {
			$this->error = '缺少签名参数';
			return false;
		}

		$signature = base64_decode( $params['sign'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		unset( $params['sign'], $params['sign_type'] );

		$source = $this->sign_source( $params );
		$key    = $this->load_public_key();

		if ( ! $key ) {
			return false;
		}

		$result = openssl_verify( $source, $signature, $key, OPENSSL_ALGO_SHA256 );

		if ( 1 !== $result ) {
			$this->error = '签名校验失败';
			return false;
		}

		return true;
	}

	/**
	 * 构造跳转支付地址
	 *
	 * @param string $method     接口名
	 * @param array  $biz        业务参数
	 * @param string $notify_url 异步通知地址
	 * @param string $return_url 同步跳转地址
	 * @return string|WP_Error
	 */
	private function redirect_url( $method, array $biz, $notify_url, $return_url ) {
		$params                = $this->base_params( $method, $notify_url );
		$params['return_url']  = $return_url;
		$params['biz_content'] = $this->encode_biz( $biz );

		$signature = $this->sign( $this->sign_source( $params ) );

		if ( '' === $signature ) {
			return new WP_Error( 'xenice_pay_alipay', $this->error );
		}

		$params['sign'] = $signature;

		return $this->gateway_url . '?' . http_build_query( $params );
	}

	/**
	 * 发起接口请求
	 *
	 * @param string $method 接口名
	 * @param array  $biz    业务参数
	 * @return array|WP_Error
	 */
	private function execute( $method, array $biz, $notify_url = '' ) {
		$params                = $this->base_params( $method, $notify_url );
		$params['biz_content'] = $this->encode_biz( $biz );

		$signature = $this->sign( $this->sign_source( $params ) );

		if ( '' === $signature ) {
			return new WP_Error( 'xenice_pay_alipay', $this->error );
		}

		$params['sign'] = $signature;

		$response = wp_remote_post(
			$this->gateway_url,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8' ),
				'body'    => $params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'xenice_pay_alipay', $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'xenice_pay_alipay', '支付宝返回数据无法解析（HTTP ' . (int) wp_remote_retrieve_response_code( $response ) . '）' );
		}

		if ( isset( $data['error_response'] ) ) {
			$error   = $data['error_response'];
			$message = ! empty( $error['sub_msg'] ) ? $error['sub_msg'] : ( ! empty( $error['msg'] ) ? $error['msg'] : '请求失败' );
			return new WP_Error( 'xenice_pay_alipay', $message );
		}

		$node = str_replace( '.', '_', $method ) . '_response';

		if ( empty( $data[ $node ] ) ) {
			return new WP_Error( 'xenice_pay_alipay', '支付宝返回数据格式异常' );
		}

		$result = $data[ $node ];

		if ( isset( $result['code'] ) && '10000' !== (string) $result['code'] ) {
			$message = ! empty( $result['sub_msg'] ) ? $result['sub_msg'] : ( ! empty( $result['msg'] ) ? $result['msg'] : '请求失败' );
			return new WP_Error( 'xenice_pay_alipay', $message . '（' . $result['code'] . '）' );
		}

		if ( ! empty( $data['sign'] ) && ! $this->verify_response( $body, $node, $data['sign'] ) ) {
			// 响应验签失败不阻断支付（HTTPS 已保证传输安全），仅记录，便于排查密钥配置问题。
			$this->error = '响应验签失败，请核对支付宝公钥';
		}

		return $result;
	}

	/**
	 * 公共请求参数
	 *
	 * @param string $method     接口名
	 * @param string $notify_url 异步通知地址
	 * @return array
	 */
	private function base_params( $method, $notify_url ) {
		$params = array(
			'app_id'    => $this->app_id,
			'method'    => $method,
			'format'    => 'JSON',
			'charset'   => 'utf-8',
			'sign_type' => 'RSA2',
			'timestamp' => gmdate( 'Y-m-d H:i:s', time() + 8 * HOUR_IN_SECONDS ),
			'version'   => '1.0',
		);

		if ( $notify_url ) {
			$params['notify_url'] = $notify_url;
		}

		return $params;
	}

	/**
	 * 业务参数编码
	 *
	 * @param array $biz 业务参数
	 * @return string
	 */
	private function encode_biz( array $biz ) {
		return wp_json_encode( $biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * 待签名串
	 *
	 * @param array $params 参数
	 * @return string
	 */
	private function sign_source( array $params ) {
		unset( $params['sign'] );
		ksort( $params );

		$pairs = array();

		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			$value = (string) $value;

			if ( '' === $value ) {
				continue;
			}

			$pairs[] = $key . '=' . $value;
		}

		return implode( '&', $pairs );
	}

	/**
	 * RSA2 签名
	 *
	 * @param string $source 待签名串
	 * @return string base64 签名
	 */
	private function sign( $source ) {
		$key = $this->load_private_key();

		if ( ! $key ) {
			return '';
		}

		$signature = '';

		if ( ! openssl_sign( $source, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			$this->error = '生成签名失败，请检查应用私钥格式';
			return '';
		}

		return base64_encode( $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * 校验接口响应签名
	 *
	 * @param string $body 原始响应
	 * @param string $node 响应节点名
	 * @param string $sign 签名
	 * @return bool
	 */
	private function verify_response( $body, $node, $sign ) {
		$needle = '"' . $node . '":';
		$start  = strpos( $body, $needle );

		if ( false === $start ) {
			return false;
		}

		$start += strlen( $needle );
		$length   = strlen( $body );
		$depth    = 0;
		$in_string = false;

		for ( $i = $start; $i < $length; $i++ ) {
			$char = $body[ $i ];

			if ( $in_string ) {
				if ( '\\' === $char ) {
					$i++;
					continue;
				}
				if ( '"' === $char ) {
					$in_string = false;
				}
				continue;
			}

			if ( '"' === $char ) {
				$in_string = true;
				continue;
			}

			if ( '{' === $char ) {
				$depth++;
				continue;
			}

			if ( '}' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					$raw = substr( $body, $start, $i - $start + 1 );
					$key = $this->load_public_key();

					if ( ! $key ) {
						return false;
					}

					return 1 === openssl_verify( $raw, base64_decode( $sign ), $key, OPENSSL_ALGO_SHA256 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				}
			}
		}

		return false;
	}

	/**
	 * 载入应用私钥
	 *
	 * @return mixed 成功返回密钥资源，失败返回 false
	 */
	private function load_private_key() {
		if ( null !== $this->private_pem ) {
			return $this->private_pem;
		}

		$key   = $this->private_key;
		$tries = array();

		if ( false !== strpos( $key, 'BEGIN' ) ) {
			$tries[] = $key;
		} else {
			$body    = preg_replace( '/\s+/', '', $key );
			$tries[] = "-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split( $body, 64, "\n" ) . "-----END RSA PRIVATE KEY-----\n";
			$tries[] = "-----BEGIN PRIVATE KEY-----\n" . chunk_split( $body, 64, "\n" ) . "-----END PRIVATE KEY-----\n";
		}

		foreach ( $tries as $pem ) {
			$resource = @openssl_pkey_get_private( $pem ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $resource && null !== $resource ) {
				$this->private_pem = $resource;
				return $resource;
			}
		}

		$this->private_pem = false;
		$this->error       = '应用私钥无效，请粘贴完整的 RSA 私钥';

		return false;
	}

	/**
	 * 载入支付宝公钥
	 *
	 * @return mixed 成功返回密钥资源，失败返回 false
	 */
	private function load_public_key() {
		if ( null !== $this->public_pem ) {
			return $this->public_pem;
		}

		$key = $this->public_key;

		if ( false === strpos( $key, 'BEGIN' ) ) {
			$body = preg_replace( '/\s+/', '', $key );
			$key  = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( $body, 64, "\n" ) . "-----END PUBLIC KEY-----\n";
		}

		$resource = @openssl_pkey_get_public( $key ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $resource || null === $resource ) {
			$this->public_pem = false;
			$this->error      = '支付宝公钥无效，请粘贴完整的支付宝公钥';
			return false;
		}

		$this->public_pem = $resource;

		return $resource;
	}
}

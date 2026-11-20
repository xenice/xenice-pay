<?php
/**
 * 支付网关基类
 *
 * 负责公共配置项、收银台页面、金额换算与订单号生成。
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Xenice_Pay_Gateway extends WC_Payment_Gateway {

	/**
	 * 是否输出调试日志
	 *
	 * @var bool
	 */
	public $debug = false;

	/**
	 * 收银台品牌标识
	 *
	 * @var string
	 */
	protected $brand = '';

	/**
	 * 设置网关基础属性
	 */
	abstract protected function setup_properties();

	/**
	 * 生成支付内容（二维码或跳转地址）
	 *
	 * @param WC_Order $order 订单
	 * @param bool     $force 是否强制重新下单
	 * @return array|WP_Error
	 */
	abstract protected function prepare_payment( $order, $force = false );

	/**
	 * 处理异步通知
	 */
	abstract public function notify();

	/**
	 * 初始化
	 */
	public function __construct() {
		$this->setup_properties();

		$this->has_fields = false;
		$this->supports   = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->debug       = 'yes' === $this->get_option( 'debug' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'notify' ) );
	}

	/**
	 * 公共配置项
	 *
	 * @return array
	 */
	protected function common_form_fields() {
		return array(
			'enabled'       => array(
				'title'   => '启用/停用',
				'type'    => 'checkbox',
				'label'   => '启用该支付方式',
				'default' => 'no',
			),
			'title'         => array(
				'title'       => '显示名称',
				'type'        => 'text',
				'description' => '结账页面显示的支付方式名称',
				'default'     => '',
				'desc_tip'    => true,
			),
			'description'   => array(
				'title'       => '描述',
				'type'        => 'textarea',
				'description' => '结账页面显示的支付说明',
				'default'     => '',
				'desc_tip'    => true,
			),
			'exchange_rate' => array(
				'title'       => '汇率',
				'type'        => 'text',
				'description' => '店铺币种换算成人民币的汇率，例如 1 美元 = 7.2 元则填写 7.2；默认 1，表示订单金额本身就是人民币。',
				'default'     => '1',
				'desc_tip'    => true,
			),
			'debug'         => array(
				'title'   => '调试日志',
				'type'    => 'checkbox',
				'label'   => '记录日志到 WooCommerce → 状态 → 日志',
				'default' => 'no',
			),
		);
	}

	/**
	 * 异步通知地址
	 *
	 * @return string
	 */
	protected function notify_url() {
		if ( function_exists( 'WC' ) && WC() && ! empty( WC()->api_request_url ) ) {
			return WC()->api_request_url( $this->id );
		}

		return home_url( '/?wc-api=' . $this->id );
	}

	/**
	 * 订单状态轮询地址（带订单密钥，避免越权查询）
	 *
	 * @param WC_Order $order 订单
	 * @return string
	 */
	protected function poll_url( $order ) {
		return add_query_arg(
			array(
				'action'   => 'xenice_pay_check_order',
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * 写日志
	 *
	 * @param string $message 内容
	 */
	protected function log( $message ) {
		if ( ! $this->debug ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->info( $message, array( 'source' => $this->id ) );
	}

	/**
	 * 订单应付金额（按汇率换算后，两位小数）
	 *
	 * @param WC_Order $order 订单
	 * @return float
	 */
	protected function order_amount( $order ) {
		$rate = (float) $this->get_option( 'exchange_rate', '1' );
		if ( $rate <= 0 ) {
			$rate = 1;
		}

		return round( (float) $order->get_total() * $rate, 2 );
	}

	/**
	 * 订单应付金额（字符串，元）
	 *
	 * @param WC_Order $order 订单
	 * @return string
	 */
	protected function amount_string( $order ) {
		return number_format( $this->order_amount( $order ), 2, '.', '' );
	}

	/**
	 * 订单应付金额（分）
	 *
	 * @param WC_Order $order 订单
	 * @return int
	 */
	protected function amount_cents( $order ) {
		return (int) round( $this->order_amount( $order ) * 100 );
	}

	/**
	 * 订单标题
	 *
	 * @param WC_Order $order 订单
	 * @param int      $max   最大字节数
	 * @return string
	 */
	protected function order_title( $order, $max = 100 ) {
		$names = array();
		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
			if ( count( $names ) >= 3 ) {
				break;
			}
		}

		$title = '订单 ' . $order->get_order_number();
		if ( $names ) {
			$title .= ' | ' . implode( '、', $names );
		}

		$title = wp_strip_all_tags( $title );

		if ( function_exists( 'mb_strimwidth' ) ) {
			return mb_strimwidth( $title, 0, $max, '…', 'UTF-8' );
		}

		// 未启用 mbstring 时的降级处理：按字符截断，避免截断半个汉字。
		if ( preg_match( '/^.{0,' . (int) $max . '}/us', $title, $matches ) && isset( $matches[0] ) && '' !== $matches[0] ) {
			return ( $matches[0] === $title ) ? $title : $matches[0] . '…';
		}

		return $title;
	}

	/**
	 * 生成或复用商户订单号
	 *
	 * 支付宝与微信都要求商户订单号唯一，过期后重新发起支付时需要更换新号。
	 *
	 * @param WC_Order $order 订单
	 * @param bool     $force 是否强制重新生成
	 * @return string
	 */
	protected function trade_no( $order, $force = false ) {
		$existing = (string) $order->get_meta( '_xenice_pay_trade_no' );
		$created  = (int) $order->get_meta( '_xenice_pay_trade_no_time' );

		if ( ! $force && '' !== $existing && $created && ( time() - $created ) < 1500 ) {
			return $existing;
		}

		$trade_no = $order->get_id() . '-' . time() . '-' . wp_rand( 100, 999 );

		$order->update_meta_data( '_xenice_pay_trade_no', $trade_no );
		$order->update_meta_data( '_xenice_pay_trade_no_time', time() );
		$order->save();

		return $trade_no;
	}

	/**
	 * 从商户订单号解析订单
	 *
	 * @param string $trade_no 商户订单号
	 * @return WC_Order|false
	 */
	protected function order_by_trade_no( $trade_no ) {
		if ( ! preg_match( '/^(\d+)-/', (string) $trade_no, $matches ) ) {
			return false;
		}

		$order = wc_get_order( absint( $matches[1] ) );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return false;
		}

		return $order;
	}

	/**
	 * 收银台页面（二维码）
	 *
	 * @param int $order_id 订单 ID
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			echo '<p>' . esc_html__( '订单无效。', 'xenice-pay' ) . '</p>';
			return;
		}

		if ( $order->is_paid() ) {
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		$force  = isset( $_GET['xenice_pay_refresh'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = $this->prepare_payment( $order, $force );

		$args = array(
			'order'   => $order,
			'gateway' => $this,
			'brand'   => $this->brand,
			'title'   => $this->order_title( $order, 60 ),
			'amount'  => $this->amount_string( $order ),
			'code'    => is_wp_error( $result ) ? '' : $result['code'],
			'tip'     => ( ! is_wp_error( $result ) && ! empty( $result['tip'] ) ) ? $result['tip'] : '',
			'error'   => is_wp_error( $result ) ? $result->get_error_message() : '',
			'poll'    => $this->poll_url( $order ),
			'return'  => $this->get_return_url( $order ),
			'cancel'  => wc_get_checkout_url(),
			'refresh' => add_query_arg( 'xenice_pay_refresh', 1, $order->get_checkout_payment_url( true ) ),
		);

		if ( is_wp_error( $result ) ) {
			$this->log( '生成支付内容失败：' . $result->get_error_message() );
		}

		include XENICE_PAY_DIR . 'templates/payment-qr.php';
	}
}
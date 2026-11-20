<?php
/**
 * Plugin Name: Xenice Pay
 * Plugin URI: https://www.xenice.com/xenice-pay/
 * Description: 支付宝（公钥模式）与微信支付（API v3）WooCommerce 支付网关，不依赖任何第三方支付 SDK，直接调用官方接口。
 * Version: 1.1.0
 * Author: Xenice
 * Author URI: https://www.xenice.com
 * Text Domain: xenice-pay
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.9
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'XENICE_PAY_VERSION', '1.0.0' );
define( 'XENICE_PAY_FILE', __FILE__ );
define( 'XENICE_PAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'XENICE_PAY_URL', plugin_dir_url( __FILE__ ) );

/**
 * 声明 HPOS（自定义订单表）兼容
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * 加载插件
 */
function xenice_pay_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'xenice_pay_missing_woocommerce_notice' );
		return;
	}

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once XENICE_PAY_DIR . 'includes/class-xenice-pay-qrcode.php';
	require_once XENICE_PAY_DIR . 'includes/class-xenice-pay-gateway.php';
	require_once XENICE_PAY_DIR . 'includes/class-xenice-pay-alipay-api.php';
	require_once XENICE_PAY_DIR . 'includes/class-xenice-pay-alipay-gateway.php';
	require_once XENICE_PAY_DIR . 'includes/class-xenice-pay-wechat-api.php';
	require_once XENICE_PAY_DIR . 'includes/class-xenice-pay-wechat-gateway.php';
}
add_action( 'plugins_loaded', 'xenice_pay_bootstrap', 20 );

/**
 * 注册两个支付网关
 *
 * @param array $methods 网关类名列表
 * @return array
 */
function xenice_pay_register_gateways( $methods ) {
	$methods[] = 'Xenice_Pay_Alipay_Gateway';
	$methods[] = 'Xenice_Pay_Wechat_Gateway';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'xenice_pay_register_gateways' );

/**
 * 缺少 WooCommerce 时的提示
 */
function xenice_pay_missing_woocommerce_notice() {
	echo '<div class="notice notice-error is-dismissible"><p>';
	echo esc_html__( 'Xenice Pay 需要先安装并启用 WooCommerce。', 'xenice-pay' );
	echo '</p></div>';
}

/**
 * 轮询订单状态
 */
function xenice_pay_ajax_check_order() {
	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
	$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
		wp_send_json_error( array( 'message' => 'invalid request' ), 403 );
	}

	wp_send_json_success(
		array(
			'status' => $order->get_status(),
			'paid'   => $order->is_paid(),
		)
	);
}
add_action( 'wp_ajax_xenice_pay_check_order', 'xenice_pay_ajax_check_order' );
add_action( 'wp_ajax_nopriv_xenice_pay_check_order', 'xenice_pay_ajax_check_order' );

/**
 * 后台提醒：网关已启用但缺少必要的密钥配置
 */
function xenice_pay_admin_config_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$warnings = array();

	$alipay = get_option( 'woocommerce_xenice_pay_alipay_settings', array() );
	if ( ! empty( $alipay['enabled'] ) && 'yes' === $alipay['enabled'] ) {
		foreach ( array(
			'app_id'              => 'App ID',
			'merchant_private_key' => '应用私钥',
			'alipay_public_key'   => '支付宝公钥',
		) as $field => $label ) {
			if ( empty( $alipay[ $field ] ) ) {
				$warnings[] = sprintf( '支付宝：%s 未填写', $label );
			}
		}
	}

	$wechat = get_option( 'woocommerce_xenice_pay_wechat_settings', array() );
	if ( ! empty( $wechat['enabled'] ) && 'yes' === $wechat['enabled'] ) {
		foreach ( array(
			'app_id'          => 'AppID',
			'mch_id'          => '商户号',
			'cert_serial_no'  => '商户证书序列号',
			'merchant_private_key' => '商户私钥',
			'api_v3_key'      => 'APIv3 密钥',
			'wechat_public_key' => '微信支付公钥',
		) as $field => $label ) {
			if ( empty( $wechat[ $field ] ) ) {
				$warnings[] = sprintf( '微信支付：%s 未填写', $label );
			}
		}
	}

	if ( empty( $warnings ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong></p><ul style="list-style:disc;margin-left:20px;">',
		esc_html__( 'Xenice Pay 配置不完整，相关支付方式将无法使用：', 'xenice-pay' )
	);
	foreach ( $warnings as $warning ) {
		echo '<li>' . esc_html( $warning ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'xenice_pay_admin_config_notice' );
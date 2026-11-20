<?php
/**
 * 收银台二维码页面
 *
 * @var array $args 模板参数
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order   = $args['order'];
$brand   = $args['brand'];
$code    = $args['code'];
$tip     = $args['tip'];
$error   = $args['error'];
$amount  = $args['amount'];
$title   = $args['title'];
$refresh = $args['refresh'];

if ( 'wechat' === $brand ) {
	$theme = array(
		'name'  => '微信支付',
		'color' => '#07C160',
		'light' => '#EEFBF3',
		'extra' => '在微信内打开时可长按二维码识别支付。',
	);
	$default_icon = XENICE_PAY_URL . 'assets/images/wechat.svg';
} else {
	$theme = array(
		'name'  => '支付宝',
		'color' => '#1677FF',
		'light' => '#EFF5FF',
		'extra' => '若二维码过期，可点击下方「刷新二维码」重新获取。',
	);
	$default_icon = XENICE_PAY_URL . 'assets/images/alipay.svg';
}

// 优先使用网关配置的图标，回退到内置 svg。
$icon = ( ! empty( $args['gateway'] ) && ! empty( $args['gateway']->icon ) )
	? $args['gateway']->icon
	: $default_icon;

$qr_svg = ( '' !== $code ) ? Xenice_Pay_QRcode::svg( $code, 240, 4 ) : '';
?>
<style>
	.order_details { display: none; }

	.xenice-pay-card {
		max-width: 400px;
		margin: 20px auto;
		padding: 28px 24px;
		background: #fff;
		border: 1px solid #eceef2;
		border-radius: 14px;
		box-shadow: 0 4px 16px rgba(15, 23, 42, .06);
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
		text-align: center;
		color: #1f2937;
	}

	.xenice-pay-head {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 6px 12px;
		border-radius: 999px;
		background: var(--xenice-light);
		color: var(--xenice-color);
		font-size: 13px;
		font-weight: 600;
		letter-spacing: .3px;
	}
	.xenice-pay-icon {
		width: 18px;
		height: 18px;
		display: block;
	}

	.xenice-pay-title {
		margin-top: 16px;
		font-size: 14px;
		color: #6b7280;
		line-height: 1.5;
		word-break: break-all;
	}

	.xenice-pay-amount {
		margin: 6px 0 20px;
		font-size: 34px;
		font-weight: 700;
		letter-spacing: -.5px;
		color: #111827;
	}
	.xenice-pay-amount small {
		font-size: 18px;
		font-weight: 600;
		margin-right: 2px;
	}

	.xenice-pay-qr {
		display: inline-block;
		padding: 12px;
		border: 1px solid #eef0f4;
		border-radius: 12px;
		background: #fff;
	}

	.xenice-pay-status {
		margin-top: 18px;
		min-height: 24px;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		font-size: 14px;
		font-weight: 600;
		color: var(--xenice-color);
	}
	.xenice-pay-spinner {
		width: 14px;
		height: 14px;
		border: 2px solid currentColor;
		border-top-color: transparent;
		border-radius: 50%;
		animation: xenice-pay-spin .8s linear infinite;
	}
	@keyframes xenice-pay-spin { to { transform: rotate(360deg); } }

	.xenice-pay-tip {
		margin-top: 14px;
		font-size: 13px;
		line-height: 1.7;
		color: #8b93a1;
	}

	.xenice-pay-actions {
		margin-top: 20px;
		font-size: 13px;
		color: #9aa2af;
	}
	.xenice-pay-actions a {
		color: #6b7280;
		text-decoration: none;
		border-bottom: 1px dashed #d1d5db;
	}
	.xenice-pay-actions a:hover {
		color: var(--xenice-color);
		border-bottom-color: var(--xenice-color);
	}
	.xenice-pay-actions .sep {
		margin: 0 8px;
		color: #d1d5db;
	}

	.xenice-pay-error {
		padding: 16px;
		border-radius: 10px;
		background: #fef2f2;
		border: 1px solid #fecaca;
		color: #b91c1c;
		font-size: 14px;
		line-height: 1.6;
	}

	.xenice-pay-retry {
		display: inline-block;
		margin-top: 18px;
		padding: 9px 18px;
		border-radius: 8px;
		background: var(--xenice-color);
		color: #fff;
		text-decoration: none;
		font-size: 13px;
	}
	.xenice-pay-retry:hover { opacity: .9; }

	@media (max-width: 480px) {
		.xenice-pay-card { margin: 16px 8px; padding: 24px 18px; }
		.xenice-pay-amount { font-size: 30px; }
	}
</style>

<div class="xenice-pay-card" style="--xenice-color: <?php echo esc_attr( $theme['color'] ); ?>; --xenice-light: <?php echo esc_attr( $theme['light'] ); ?>;">

	<div class="xenice-pay-head">
		<img class="xenice-pay-icon" src="<?php echo esc_url( $icon ); ?>" alt="" />
		<span><?php echo esc_html( $theme['name'] ); ?></span>
	</div>

	<div class="xenice-pay-title"><?php echo esc_html( $title ); ?></div>

	<div class="xenice-pay-amount">
		<small>¥</small><?php echo esc_html( $amount ); ?>
	</div>

	<?php if ( '' !== $error ) : ?>

		<div class="xenice-pay-error"><?php echo esc_html( $error ); ?></div>
		<a class="xenice-pay-retry" href="<?php echo esc_url( $refresh ); ?>">重新获取</a>

	<?php else : ?>

		<div class="xenice-pay-qr">
			<?php
			// 二维码由插件内部生成，内容仅为图形路径与固定颜色。
			echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>

		<div id="xenice-pay-status" class="xenice-pay-status">
			<span class="xenice-pay-spinner"></span>
			<span>等待支付…</span>
		</div>

		<div class="xenice-pay-tip">
			<?php echo esc_html( $tip ); ?><br />
			<?php echo esc_html( $theme['extra'] ); ?>
		</div>

		<div class="xenice-pay-actions">
			<a href="<?php echo esc_url( $refresh ); ?>">刷新二维码</a>
			<span class="sep">|</span>
			<a href="<?php echo esc_url( $args['cancel'] ); ?>">返回结账页</a>
		</div>

	<?php endif; ?>
</div>

<?php if ( '' === $error ) : ?>
<script>
	( function () {
		var config = <?php echo wp_json_encode( array( 'poll' => $args['poll'], 'redirect' => $args['return'] ) ); ?>;
		var box = document.getElementById( 'xenice-pay-status' );
		var done = false;

		function render( text, color ) {
			box.innerHTML = '';
			box.style.color = color;
			box.textContent = text;
		}

		function schedule( delay ) {
			if ( ! done ) {
				window.setTimeout( check, delay );
			}
		}

		function check() {
			fetch( config.poll, { credentials: 'same-origin', cache: 'no-store' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					var data = result && result.data ? result.data : null;

					if ( ! data ) {
						schedule( 5000 );
						return;
					}

					if ( data.paid || data.status === 'processing' || data.status === 'completed' ) {
						done = true;
						render( '支付成功，正在跳转…', '#16a34a' );
						window.setTimeout( function () {
							window.location.href = config.redirect;
						}, 700 );
						return;
					}

					if ( data.status === 'failed' || data.status === 'cancelled' || data.status === 'refunded' ) {
						done = true;
						render( '订单状态异常，请返回结账页重试', '#dc2626' );
						return;
					}

					schedule( 3000 );
				} )
				.catch( function () {
					schedule( 5000 );
				} );
		}

		schedule( 2500 );
	} )();
</script>
<?php endif; ?>
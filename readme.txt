=== Xenice Pay ===
Contributors: xenice
Tags: woocommerce, alipay, wechat pay, payment gateway, 支付宝, 微信支付
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

支付宝（公钥模式）与微信支付（API v3）的 WooCommerce 支付网关，不依赖任何第三方支付 SDK，直接调用官方接口。

== Description ==

Xenice Pay 为 WooCommerce 提供两个独立的支付方式：

* 支付宝：扫码支付（当面付）与跳转收银台（电脑网站支付 / 手机网站支付），使用公钥模式 + RSA2 签名。
* 微信支付：API v3 Native 扫码支付，可选 H5 支付，自建请求签名、回调验签与 AES-256-GCM 解密。

特点：

* 不使用 yurunsoft/pay-sdk 等第三方支付框架，签名、验签、加解密全部由插件自身实现，只依赖 PHP 的 openssl 扩展。
* 二维码由内置的纯 PHP 编码器生成 SVG，不调用外部二维码接口，不依赖 GD/Imagick。
* 支持 HPOS（WooCommerce 自定义订单表）。
* 订单金额可按汇率换算成人民币，适配合美元、欧元等店铺币种。
* 收银台页面自动轮询订单状态，支付成功后自动跳转。

== Installation ==

1. 后台上传插件压缩包并启用，或在服务器上将 `xenice-pay` 目录放入 `wp-content/plugins/`。
2. 进入 WooCommerce → 设置 → 付款，分别配置「支付宝」与「微信支付」。
3. 支付宝：填写 App ID、应用私钥、支付宝公钥（公钥模式）。
4. 微信支付：填写 AppID、商户号、商户证书序列号、商户私钥、APIv3 密钥、微信支付公钥。
5. 保存后启用对应支付方式，并用一笔小额订单完成真实支付测试。

== Frequently Asked Questions ==

= 需要安装官方 SDK 吗？ =

不需要。插件直接向支付宝网关与微信支付 API v3 发起 HTTPS 请求。

= 为什么收银台页面不显示二维码？ =

请检查 WooCommerce → 状态 → 日志中 `xenice_pay_alipay` / `xenice_pay_wechat` 来源的记录。常见原因是密钥格式错误、应用未开通对应支付产品，或服务器无法访问支付网关。

= 支付成功但订单仍是「待付款」？ =

说明异步通知没有到达。请确认站点公网可访问、使用 HTTPS，且服务器没有拦截 `?wc-api=xenice_pay_alipay` 或 `?wc-api=xenice_pay_wechat` 请求。

== Changelog ==

= 1.0.0 =
* 首个版本：支付宝（公钥模式）与微信支付（API v3），移除对第三方支付 SDK 的依赖。
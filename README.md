# Xenice Pay 使用说明

Xenice Pay 是一款WooCommerce支付插件，支持支付宝（公钥模式）与微信支付（API v3），不依赖任何第三方支付 SDK，签名、验签、加解密全部由插件自己完成，付款二维码也由插件内置生成。


插件官网：[https://www.xenice.com/product/xenice-pay/](https://www.xenice.com/product/xenice-pay/)

---

## 一、目录结构

```
xenice-pay/
├── xenice-pay.php                          插件入口：常量、加载、网关注册、状态轮询接口
├── includes/
│   ├── class-xenice-pay-gateway.php        网关基类：配置项、金额换算、商户订单号、收银台
│   ├── class-xenice-pay-alipay-api.php     支付宝直连：RSA2 签名 / 验签 / 预下单 / 查询
│   ├── class-xenice-pay-alipay-gateway.php 支付宝网关
│   ├── class-xenice-pay-wechat-api.php     微信支付直连：v3 签名 / 验签 / 解密
│   ├── class-xenice-pay-wechat-gateway.php 微信支付网关
│   └── class-xenice-pay-qrcode.php         纯 PHP 二维码编码器（输出 SVG）
├── templates/
│   └── payment-qr.php                      收银台二维码页面与状态轮询
├── assets/images/                          后台支付方式图标
├── readme.txt
└── 使用说明.md
```

与原 `xenice-woo-alipay` 相比，已移除：EDD 授权客户端（`functions.php`、`includes/class-client.php`）、`vendor/autoload.php` 里的 `yurunsoft/pay-sdk`、`qrcode.class.php` 第三方库、`payqr.php` 外部二维码接口、以及 EDD 专用的 `check.php`、`templates/pay.php`。

---

## 二、安装

1. 将 `xenice-pay` 目录上传到 `wp-content/plugins/`（或后台上传 zip 包）。
2. 启用插件，需先启用 WooCommerce。
3. 进入 **WooCommerce → 设置 → 付款**，会看到「支付宝」与「微信支付」两个支付方式。

---

## 三、支付宝配置（公钥模式）

### 1. 开放平台准备

1. 登录 [支付宝开放平台](https://open.alipay.com/)，创建「网页/移动应用」，拿到 **App ID**。
2. 在应用的「开发设置 → 接口加签方式」选择 **公钥**（不是公钥证书）。
3. 使用支付宝密钥工具生成 **应用私钥 / 应用公钥**，把「应用公钥」上传到开放平台。
4. 上传后平台会生成 **支付宝公钥**，复制备用。

> 注意：插件需要填的是「支付宝公钥」，不是「应用公钥」。两者填反会导致回调验签失败。

### 2. 开通支付产品

按需要开通：**当面付**（扫码）、**电脑网站支付**、**手机网站支付**。

### 3. 插件内填写

| 配置项 | 说明 |
| --- | --- |
| App ID | 开放平台应用的 APPID |
| 应用私钥 | 应用私钥，支持完整 PEM 或仅 Base64 正文 |
| 支付宝公钥 | 公钥模式下平台给出的支付宝公钥 |
| 支付模式 | 「扫码支付」展示二维码；「跳转收银台」使用网站支付 |
| 汇率 | 店铺币种 → 人民币的换算比例，默认 1 |
| 沙箱环境 | 联调时可开启 |
| 调试日志 | 排查问题时开启 |

手机端访问时，即使选择「扫码支付」，也会优先走手机网站支付；若未开通则回落到二维码页面。

### 4. 回调地址

插件自动使用 `https://你的域名/?wc-api=xenice_pay_alipay`，无需手工填写。站点必须是公网可访问的 HTTPS 地址。

---

## 四、微信支付配置（API v3）

### 1. 商户平台准备

1. 登录[微信支付商户平台](https://pay.weixin.qq.com/)，确认已开通 **Native 支付**（如需手机 H5，再开通 **H5 支付** 并报备域名）。
2. 在「账户中心 → API 安全」：
   * 申请 **商户 API 证书**，得到 `apiclient_key.pem`（商户私钥）与**证书序列号**；
   * 设置 **APIv3 密钥**（32 位）；
   * 在「微信支付公钥」处下载 `pub_key.pem` 并记录 **公钥 ID**（形如 `PUB_KEY_ID_0115...`）。
3. 确认 AppID 已与商户号完成绑定。

### 2. 插件内填写

| 配置项 | 说明 |
| --- | --- |
| AppID | 与商户号绑定的 AppID |
| 商户号 | mchid |
| 商户证书序列号 | 商户 API 证书序列号 |
| 商户私钥 | `apiclient_key.pem` 内容 |
| APIv3 密钥 | 32 位，用于解密回调 |
| 微信支付公钥 | `pub_key.pem` 内容，用于回调验签 |
| 微信支付公钥 ID | 用于识别签名来源 |
| 手机 H5 支付 | 手机浏览器访问时跳转 H5 支付，需已开通并报备域名 |

### 3. 回调地址

插件自动使用 `https://你的域名/?wc-api=xenice_pay_wechat`，需在商户平台可访问；微信要求回调地址为 HTTPS 且不能带参数。

---

## 五、实现要点

* **支付宝签名**：参数按字典序拼接（排除 `sign`，验签时同时排除 `sign_type`），`openssl_sign` + SHA256 生成 RSA2 签名，Base64 输出。
* **支付宝验签**：使用支付宝公钥 `openssl_verify`，并额外校验 `app_id`、`total_amount` 与订单金额一致。
* **微信请求签名**：`METHOD\nURL\ntimestamp\nnonce\nbody\n` 用商户私钥 RSA-SHA256 签名，组装 `WECHATPAY2-SHA256-RSA2048` 授权头。
* **微信回调**：先用微信支付公钥验证 `Wechatpay-Signature`（时间戳 5 分钟内有效），再用 APIv3 密钥以 AES-256-GCM 解密 `resource`，校验 `mchid` 与 `amount.total`。
* **幂等**：已支付订单收到重复通知时直接返回成功，不会重复入账。
* **商户订单号**：格式 `订单ID-时间戳-随机数`，30 分钟内复用，过期或手动刷新时重新生成，避免二维码过期后无法支付。

---

## 六、常见问题

**二维码不显示 / 提示配置不完整**
查看 WooCommerce → 状态 → 日志中 `xenice_pay_alipay`、`xenice_pay_wechat` 的记录。多数是私钥/公钥粘贴不完整，或应用未开通对应支付产品。

**支付成功但订单仍待付款**
异步通知没到达。确认：站点公网可访问、HTTPS 证书有效、`?wc-api=...` 未被防火墙或安全插件拦截、没有开启「强制登录」导致回调被重定向。

**支付宝提示「验签失败」**
检查是否把「应用公钥」当成「支付宝公钥」填入；确认开放平台加签方式为公钥模式；确认没有多余空格或换行。

**微信提示「签名错误」**
核对商户证书序列号与私钥是否配对、APIv3 密钥是否为 32 位、服务器时间是否准确（误差需在 5 分钟内）。

**店铺币种不是人民币**
设置「汇率」为「1 单位店铺币种 = 多少人民币」，插件会自动换算后再提交支付。
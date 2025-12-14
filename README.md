# WooCommerce Rest API 支付插件 xenice-pay

WooCommerce 内置并未提供 REST API 支付接口。在进行 WooCommerce 二次开发（如商城小程序、APP、前后端分离项目）时，**xenice-pay** 插件可用于扩展 WooCommerce 的 REST API 支付能力。

> 目前仅支持 **支付宝支付**。

---

## 插件网址

https://www.xenice.com/zh/xenice-pay/

---

## 支付接口说明

### 1️⃣ 获取可用支付方式

**请求方式**

```
GET /xe/v1/pay-ways
```

**请求参数**

| 参数名 | 类型 | 说明 |
|------|------|------|
| frontend | string | 前端类型，可选值：`h5`、`wxh5`、`weixin`、`qq`、`baidu` 等 |

**返回示例**

```json
[
  {
    "name": "支付名称",
    "value": "支付方式值",
    "icon": "支付方式图标类型"
  }
]
```

说明：

- `value` 可选值示例：
  - `alipay_f2f`：支付宝当面付
  - `alipay_phone`：支付宝手机支付

---

### 2️⃣ 创建支付订单

**请求方式**

```
POST /xe/v1/pay-ways
```

**请求参数**

| 参数名 | 类型 | 必填 | 说明 |
|------|------|------|------|
| pay_way | string | 是 | 支付方式：`alipay_f2f` / `alipay_phone` |
| token | string | 是 | 当前用户 Token，用于支付成功回调校验 |
| openid | string | 否 | 微信支付时需要（预留字段） |
| trade_order_id | string | 是 | 订单 ID |
| total_fee | string | 是 | 订单金额 |
| title | string | 是 | 订单标题 |
| return_url | string | 是 | 支付成功后的跳转地址 |

---

## 支付返回数据说明

### ✅ 支付宝当面付（扫码支付）

返回数据可用于生成 **二维码支付页面**：

```json
{
  "order_sn": "订单ID",
  "qr_code": "付款二维码",
  "token": "用户TOKEN",
  "price": "支付价格",
  "title": "订单标题",
  "payway": "alipay_f2f",
  "way": "alipay",
  "wayname": "支付宝支付"
}
```

---

### ✅ 支付宝手机支付

前端直接跳转返回的 URL 即可唤起支付宝客户端：

```json
{
  "payway": "alipay_phone",
  "url": "支付宝支付跳转链接"
}
```

---

## 支付成功通知钩子

插件在支付成功后会触发以下钩子：

```php
do_action('xenice_pay_alipay_notify', $request);
```

**用途示例：**

- 校验支付结果
- 修改 WooCommerce 订单状态
- 记录支付日志
- 执行自定义业务逻辑

---

## 适用场景

- WooCommerce 小程序商城
- APP + WooCommerce 后端
- 前后端分离支付方案
- 自定义支付流程

---

## 说明

本插件主要用于 **扩展 WooCommerce REST API 支付能力**，适合有一定二次开发需求的开发者使用。

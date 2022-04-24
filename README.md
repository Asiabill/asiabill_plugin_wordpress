
Asiabill WooCommerce 支付插件
=

插件安装
-

1、推荐使用插件市场进行安装，搜索“Asiabill Payment Gateway for WooCommerce”

2、离线安装：Plugins -> Add New 点击Upload Plugin按钮，上传安装包:asiabill-payment-gateway-for-woocommerce.zip

3、设置：WooCommerce -> Settings -> Payments：你可以看到Asiabill的相关支付列表

![image](https://files.gitbook.com/v0/b/gitbook-x-prod.appspot.com/o/spaces%2FcSYgMg71VCxeEVhWhVFp%2Fuploads%2FX0YLQQs0rCYsaweEfXAQ%2Fwordpress-admin-list.png?alt=media&token=6c6bb1a0-3419-4986-bf27-a07a29e556bd)

* Enable/Disable：开启/禁用支付方式
* Payment Method：显示支付方式名称
* Use Test Model：开启测试模式（测试模式下支付成功不会真实扣款）
* Mer No、Gateway No、Signkey：账户信息，非测试模式下使用
* Test Mer No、Test Gateway No、Test Signkey：测试账户信息，测试模式下使用，默认已设置
* Description：支付描述
* Debug Log：开启支付数据日志
* Inline Credit Card Form：站内支付模式
* Saved Cards：保存客户卡号，开启后客户可以选保存当前支付卡的信息，方便下次继续支付
* Visa、MasterCard、JCB、American Express、Discover、Diners：显示卡种图标


信用卡支付
-
1、站内支付模式：在网站内可以填写卡号信息，体验相对友好
![images](https://files.gitbook.com/v0/b/gitbook-x-prod.appspot.com/o/spaces%2FcSYgMg71VCxeEVhWhVFp%2Fuploads%2FXX8wcmjxherczByVXix9%2Fimage.png?alt=media&token=a6cb13b8-dc12-4758-9390-b58e8ede42b5)

2、跳转支付模式：页面会跳出当前网站，在Asiabill页面进行输入卡号，支付完成后跳转回网站
![images](https://files.gitbook.com/v0/b/gitbook-x-prod.appspot.com/o/spaces%2FcSYgMg71VCxeEVhWhVFp%2Fuploads%2FJhjGY4FOLbq7UlkjkurH%2Fimage.png?alt=media&token=bd122e1d-42f3-491e-b8b9-2a6319f90671)


测试卡号
-
* 支付成功：4242424242424242
* 支付失败：4000000000009995
* 3D交易：4000002500003155

本地支付
-
需要额外开通才能交易

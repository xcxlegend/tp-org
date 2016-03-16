# LegendPaySDK

基于TP3.2做的支付的处理. 包括了支付宝网页支付, 快捷支付, 财付通, 银联的支付. 

Common 组 
Util/ 导入的第三方的外部SDK库

V201601组 应用入口
- Controller 
提供外部的页面处理. 因为项目本身是提供给APP的订单处理, 将生成订单和支付分开处理. 并且所有的支付使用同一的支付和回调入口, 使用channel参数区分支付方式和调用相应的支付类
OrderController提供三个方法
notify: 处理回调
webpay: 网页支付
frontReturn: 前台返回页面
OrderController提供生成订单的操作, 除了银联需要调用相关的预设订单号的操作, 其他的都跟支付方式无关. 

- Event
OrderEvent 处理所有订单的逻辑 实例化pay/下面对应的支付类 根据
IPayEvent 支付接口类 pay/下面所有的支付类必须实现所有的方法
pay/ 所有的支付类


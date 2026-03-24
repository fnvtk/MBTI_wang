ALTER TABLE `mbti_orders`
ADD COLUMN `productTitle` varchar(255) NULL DEFAULT NULL COMMENT '商品标题（如个人深度洞察测试版、AI人脸完整报告）' AFTER `productType`;


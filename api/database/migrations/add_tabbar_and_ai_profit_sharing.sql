-- =====================================================
-- 神仙 AI v2 · TabBar 后台可配 + AI 深度报告 + 分账规则
-- Date: 2026-04-17
-- 生产库表前缀若为 mbti_，则下方表名以 mbti_ 为准
-- =====================================================

-- ---------- 1) 小程序 TabBar 配置 ----------
CREATE TABLE IF NOT EXISTS `mbti_mp_tabbar_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sortOrder`  INT NOT NULL DEFAULT 100             COMMENT '排序,小的在前',
  `pagePath`   VARCHAR(128) NOT NULL                COMMENT '目标页面 pages/xxx/xxx',
  `text`       VARCHAR(32)  NOT NULL                COMMENT '底部文案',
  `iconKey`    VARCHAR(32)  NOT NULL DEFAULT 'home' COMMENT '图标key: home/camera/ai/profile',
  `visible`    TINYINT(1)   NOT NULL DEFAULT 1      COMMENT '1=显示 0=隐藏(审核模式)',
  `highlight`  TINYINT(1)   NOT NULL DEFAULT 0      COMMENT '1=中间突出按钮(圆形)',
  `badgeKey`   VARCHAR(32)  NULL                    COMMENT '红点角标来源key(预留)',
  `createdAt`  INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序 TabBar 后台可配';

-- 初始 4 项（默认顺序：首页·拍摄·神仙AI·我）
INSERT INTO `mbti_mp_tabbar_items` (sortOrder, pagePath, text, iconKey, highlight, visible, createdAt, updatedAt) VALUES
  (10, 'pages/index/index',   '首页',   'home',    0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (20, 'pages/index/camera',  '拍摄',   'camera',  1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (30, 'pages/ai-chat/index', '神仙AI', 'ai',      0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (40, 'pages/profile/index', '我',     'profile', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());


-- ---------- 2) 分账规则（知己 profit-sharing 思路） ----------
CREATE TABLE IF NOT EXISTS `mbti_profit_sharing_rules` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `productType`  VARCHAR(64) NOT NULL                COMMENT 'ai_deep_report / mbti_report / face_analysis / default',
  `name`         VARCHAR(128) NOT NULL,
  `receivers`    JSON NOT NULL                       COMMENT '[{type,name,ratio,account?}]',
  `status`       VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active/disabled',
  `createdAt`    INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt`    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product` (`productType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单分账规则';

INSERT INTO `mbti_profit_sharing_rules` (productType, name, receivers, status, createdAt, updatedAt) VALUES
  ('ai_deep_report', 'AI 深度画像报告',
    '[{"type":"platform","name":"平台","ratio":0.70},{"type":"distributor_l1","name":"一级分销","ratio":0.20},{"type":"distributor_l2","name":"二级分销","ratio":0.10}]',
    'active', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('default', '默认规则 · 平台 100%',
    '[{"type":"platform","name":"平台","ratio":1.00}]',
    'active', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());


-- ---------- 3) 分账记录 ----------
CREATE TABLE IF NOT EXISTS `mbti_profit_sharing_records` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orderSn`      VARCHAR(64) NOT NULL                COMMENT '订单号',
  `orderId`      INT UNSIGNED NULL                   COMMENT '订单表id',
  `productType`  VARCHAR(64) NOT NULL,
  `totalAmount`  INT NOT NULL DEFAULT 0              COMMENT '订单总金额(分)',
  `details`      JSON NOT NULL                       COMMENT '分账明细 [{receiverType,receiverName,amount,ratio,account,status}]',
  `status`       VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/completed/failed',
  `processedAt`  INT UNSIGNED NULL,
  `createdAt`    INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt`    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_sn` (`orderSn`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单分账记录';


-- ---------- 4) AI 深度报告（付费解锁阅读） ----------
CREATE TABLE IF NOT EXISTS `mbti_ai_reports` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId`         INT UNSIGNED NOT NULL,
  `conversationId` INT UNSIGNED NULL                 COMMENT '来源对话',
  `mbtiType`       VARCHAR(8) NULL,
  `orderSn`        VARCHAR(64) NULL                  COMMENT '对应订单号',
  `orderId`        INT UNSIGNED NULL,
  `priceFen`       INT NOT NULL DEFAULT 990          COMMENT '单价(分)',
  `status`         VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending=未支付 paid=已支付未生成 generating=生成中 done=已完成 failed=失败',
  `title`          VARCHAR(255) NULL,
  `summary`        TEXT NULL                         COMMENT '免费预览的摘要片段',
  `content`        MEDIUMTEXT NULL                   COMMENT '完整报告正文',
  `posterUrl`      VARCHAR(512) NULL                 COMMENT '分享海报',
  `retryCount`     INT NOT NULL DEFAULT 0,
  `lastError`      VARCHAR(512) NULL,
  `paidAt`         INT UNSIGNED NULL,
  `generatedAt`    INT UNSIGNED NULL,
  `createdAt`      INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt`      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`userId`),
  KEY `idx_order_sn` (`orderSn`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI 深度画像报告';

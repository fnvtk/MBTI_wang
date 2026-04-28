#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# /// script
# requires-python = ">=3.9"
# dependencies = ["pymysql"]
# ///
"""
将管理后台企业概览的核心默认指标写入 MySQL 数据库。

写入内容（到 wong_system_config 表）：
  - today_active_default   = 142       （今日活跃人数）
  - ai_cost_default        = 110000    （AI 算力累计消耗，单位：元）
  - total_revenue_default  = 312000    （累计收益，单位：分，即 ¥3120）

如果表不存在则自动创建。已存在的 key 用 INSERT...ON DUPLICATE KEY UPDATE 幂等写入。

用法:
  python3 scripts/set_dashboard_defaults_mysql.py
  python3 scripts/set_dashboard_defaults_mysql.py --dry-run
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from pathlib import Path

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    sys.exit("缺少依赖，请先执行: pip install pymysql")

ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = ROOT / "api" / ".env"

# ── 默认指标值 ───────────────────────────────────────────────────
DEFAULTS: list[tuple[str, str, str]] = [
    ("today_active_default",  "142",    "今日活跃人数默认值"),
    ("ai_cost_default",       "110000", "AI 算力累计消耗（元）默认值"),
    ("total_revenue_default", "312000", "累计收益（分，¥3120）默认值"),
]


def load_env(path: Path) -> dict[str, str]:
    cfg: dict[str, str] = {}
    if not path.is_file():
        print(f"[警告] 未找到 .env 文件：{path}，尝试读取系统环境变量")
        return cfg
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        m = re.match(r"^([A-Z0-9_]+)\s*=\s*(.*)$", line)
        if m:
            cfg[m.group(1)] = m.group(2).strip()
    return cfg


def get_db_cfg() -> dict:
    env = load_env(ENV_PATH)

    def g(key: str, default: str = "") -> str:
        return env.get(key) or os.environ.get(key) or default

    return {
        "host":   g("DATABASE_HOST",     g("DB_HOST", "127.0.0.1")),
        "port":   int(g("DATABASE_PORT", g("DB_PORT", "3306"))),
        "user":   g("DATABASE_USERNAME", g("DB_USERNAME", "root")),
        "passwd": g("DATABASE_PASSWORD", g("DB_PASSWORD", "")),
        "db":     g("DATABASE_NAME",     g("DB_DATABASE", "news_db")),
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
    }


def main():
    parser = argparse.ArgumentParser(description="写入 Dashboard 默认指标到 MySQL")
    parser.add_argument("--dry-run", action="store_true", help="只打印 SQL，不执行")
    args = parser.parse_args()

    cfg = get_db_cfg()
    print(f"[info] 连接数据库 {cfg['user']}@{cfg['host']}:{cfg['port']}/{cfg['db']}")

    if args.dry_run:
        print("[dry-run] 以下 SQL 将被执行：")

    conn = None
    try:
        if not args.dry_run:
            conn = pymysql.connect(**cfg)

        table = "wong_system_config"

        # 建表 SQL（幂等）
        create_sql = f"""
CREATE TABLE IF NOT EXISTS `{table}` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key`  VARCHAR(128) NOT NULL UNIQUE COMMENT '配置 key',
  `config_value` TEXT        NOT NULL COMMENT '配置值（字符串）',
  `description` VARCHAR(255) DEFAULT '' COMMENT '说明',
  `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';
""".strip()

        upsert_sql = f"""
INSERT INTO `{table}` (`config_key`, `config_value`, `description`)
VALUES (%s, %s, %s)
ON DUPLICATE KEY UPDATE
  `config_value` = VALUES(`config_value`),
  `description`  = VALUES(`description`);
""".strip()

        if args.dry_run:
            print(f"\n-- 建表\n{create_sql}\n")
            for key, val, desc in DEFAULTS:
                print(f"-- 写入 {key}={val}\n{upsert_sql % (repr(key), repr(val), repr(desc))}\n")
            print("[dry-run] 完成，未写入任何数据。")
            return

        with conn.cursor() as cur:
            cur.execute(create_sql)
            print(f"[ok] 表 {table} 已确认存在")

            for key, val, desc in DEFAULTS:
                cur.execute(upsert_sql, (key, val, desc))
                print(f"[ok] {key} = {val}  ({desc})")

        conn.commit()
        print("\n[success] Dashboard 默认指标已写入数据库。")

    except pymysql.Error as e:
        print(f"[error] 数据库操作失败: {e}", file=sys.stderr)
        sys.exit(1)
    finally:
        if conn:
            conn.close()


if __name__ == "__main__":
    main()

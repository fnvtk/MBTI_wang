#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
将「源库」里 openid 以 krimp_ 开头的 wechat_users 行，INSERT IGNORE 到「目标库」。

用于：数据已写入仓库 api/.env 指向的腾讯云，但 mbtiapi 线上仍连宝塔本机 MySQL 时，
把 krimp 导入用户同步到线上实际读写的库。

源/目标配置格式与 api/.env 相同（DATABASE_HOSTNAME、DATABASE_HOSTPORT、…）。

用法：
  # 复制 api/.env.mysql.line（内容=线上服务器 api/.env 里数据库段），勿提交 Git
  python3 scripts/mysql_mirror_krimp_wechat_users.py \\
    --source-env api/.env \\
    --target-env api/.env.mysql.line

仅校验连接、不写入：
  python3 scripts/mysql_mirror_krimp_wechat_users.py --target-env api/.env.mysql.line --dry-run
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    import pymysql
except ImportError:
    sys.exit("需要 pymysql")


def load_env(path: Path) -> dict[str, str]:
    cfg: dict[str, str] = {}
    if not path.is_file():
        return cfg
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        m = re.match(r"^([A-Z0-9_]+)\s*=\s*(.*)$", line)
        if m:
            cfg[m.group(1)] = m.group(2).strip()
    return cfg


def connect(cfg: dict[str, str]):
    return pymysql.connect(
        host=cfg.get("DATABASE_HOSTNAME", "127.0.0.1"),
        port=int(cfg.get("DATABASE_HOSTPORT", "3306")),
        user=cfg.get("DATABASE_USERNAME", "root"),
        password=cfg.get("DATABASE_PASSWORD", ""),
        database=cfg.get("DATABASE_DATABASE", "mbti"),
        charset="utf8mb4",
    )


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    ap = argparse.ArgumentParser()
    ap.add_argument("--source-env", type=Path, default=root / "api" / ".env")
    ap.add_argument("--target-env", type=Path, required=True)
    ap.add_argument("--batch", type=int, default=200)
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    src_cfg = load_env(args.source_env)
    dst_cfg = load_env(args.target_env)
    if not dst_cfg.get("DATABASE_HOSTNAME"):
        sys.exit(f"目标配置无效或文件不存在: {args.target_env}")

    pre_s = src_cfg.get("DATABASE_PREFIX", "mbti_")
    pre_d = dst_cfg.get("DATABASE_PREFIX", "mbti_")
    tbl_s = f"{pre_s}wechat_users"
    tbl_d = f"{pre_d}wechat_users"

    src = connect(src_cfg)
    dst = connect(dst_cfg)
    inserted = 0

    try:
        with src.cursor() as cs, dst.cursor() as cd:
            cs.execute(f"SHOW COLUMNS FROM `{tbl_s}`")
            src_order = [r[0] for r in cs.fetchall()]
            cd.execute(f"SHOW COLUMNS FROM `{tbl_d}`")
            dst_set = {r[0] for r in cd.fetchall()}
            columns = [c for c in src_order if c != "id" and c in dst_set]
            col_sql = ", ".join(f"`{c}`" for c in columns)
            placeholders = ", ".join(["%s"] * len(columns))
            insert_sql = f"INSERT IGNORE INTO `{tbl_d}` ({col_sql}) VALUES ({placeholders})"

            cs.execute(
                f"SELECT COUNT(*) FROM `{tbl_s}` WHERE `openid` LIKE %s", ("krimp_%",)
            )
            n_src = cs.fetchone()[0]

        if args.dry_run:
            print(
                {
                    "dry_run": True,
                    "source_rows_krimp": n_src,
                    "source_table": tbl_s,
                    "target_table": tbl_d,
                }
            )
            return

        with src.cursor() as cs, dst.cursor() as cd:
            cs.execute(
                f"SELECT {col_sql} FROM `{tbl_s}` WHERE `openid` LIKE %s",
                ("krimp_%",),
            )
            batch = []
            for row in cs:
                batch.append(row)
                if len(batch) >= args.batch:
                    cd.executemany(insert_sql, batch)
                    inserted += cd.rowcount
                    dst.commit()
                    batch = []
            if batch:
                cd.executemany(insert_sql, batch)
                inserted += cd.rowcount
                dst.commit()

        with dst.cursor() as cd:
            cd.execute(
                f"SELECT COUNT(*) FROM `{tbl_d}` WHERE `openid` LIKE %s", ("krimp_%",)
            )
            n_dst = cd.fetchone()[0]
            cd.execute(f"SELECT COUNT(*) FROM `{tbl_d}`")
            total_dst = cd.fetchone()[0]

        print(
            {
                "ok": True,
                "source_krimp": n_src,
                "target_krimp_after": n_dst,
                "target_total_after": total_dst,
                "insert_ignore_rowcount_sum": inserted,
            }
        )
    finally:
        src.close()
        dst.close()


if __name__ == "__main__":
    main()

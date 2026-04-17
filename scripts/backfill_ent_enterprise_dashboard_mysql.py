#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
按企业维度补全 MySQL 数据，使管理端「概览」与「用户列表」与 wechat_users 总用户对齐。

- 用户列表依赖 user_profile（enterprise + enterpriseId）用户池；为该企业下缺失画像的行补 INSERT。
- 已完成测试数 = test_results 行数（该企业 enterpriseId）：补到 round(总用户*40%)。
- 今日活跃 = 当日有 test_results 的去重 userId：通过新插入/UPDATE 调到 round(总用户*5%)。
- 四类测评人次比例沿用旧盘 face:mbti:disc:pdp ≈ 137:30:16:16。

读 api/.env 的 DATABASE_*。不改 PHP/前端。

用法:
  python3 scripts/backfill_ent_enterprise_dashboard_mysql.py --enterprise-id 5
  python3 scripts/backfill_ent_enterprise_dashboard_mysql.py --enterprise-id 5 --dry-run
"""

from __future__ import annotations

import argparse
import json
import os
import random
import re
import sys
import time
from pathlib import Path

try:
    import pymysql
except ImportError:
    sys.exit("需要: pip install pymysql")

ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = ROOT / "api" / ".env"


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


MBTI_TYPES = [
    "INTJ",
    "INTP",
    "ENTJ",
    "ENTP",
    "INFJ",
    "INFP",
    "ENFJ",
    "ENFP",
    "ISTJ",
    "ISFJ",
    "ESTJ",
    "ESFJ",
    "ISTP",
    "ISFP",
    "ESTP",
    "ESFP",
]
DISC_TYPES = ["D型", "I型", "S型", "C型"]
# PDP 展示统一中文型名（与 reshuffle_ent_test_labels_mysql 一致）
PDP_TYPES = ["老虎型", "孔雀型", "考拉型", "猫头鹰型", "变色龙型"]


def result_json(test_type: str, seed: int) -> str:
    r = random.Random(seed)
    if test_type == "mbti":
        t = MBTI_TYPES[seed % len(MBTI_TYPES)]
        return json.dumps({"mbtiType": t, "type": t}, ensure_ascii=False)
    if test_type == "disc":
        t = DISC_TYPES[seed % len(DISC_TYPES)]
        return json.dumps(
            {"description": {"type": t}, "dominantType": t[0]},
            ensure_ascii=False,
        )
    if test_type == "pdp":
        t = PDP_TYPES[seed % len(PDP_TYPES)]
        return json.dumps(
            {"description": {"type": t}, "dominantType": t},
            ensure_ascii=False,
        )
    # face：DISC/PDP 与答题口径一致（中文）
    m = MBTI_TYPES[r.randint(0, len(MBTI_TYPES) - 1)]
    d = DISC_TYPES[r.randint(0, len(DISC_TYPES) - 1)]
    p = PDP_TYPES[r.randint(0, len(PDP_TYPES) - 1)]
    return json.dumps(
        {
            "mbti": {"type": m, "title": "—"},
            "disc": {"primary": d, "secondary": ""},
            "pdp": {"primary": p, "secondary": ""},
            "overview": "批量补数",
        },
        ensure_ascii=False,
    )


def type_plan(n: int, weights: tuple[int, int, int, int]) -> list[str]:
    """按权重生成 n 条 testType 序列，顺序打散。"""
    w_face, w_mbti, w_disc, w_pdp = weights
    tw = w_face + w_mbti + w_disc + w_pdp
    raw: list[str] = []
    for _ in range(round(n * w_face / tw)):
        raw.append("face")
    for _ in range(round(n * w_mbti / tw)):
        raw.append("mbti")
    for _ in range(round(n * w_disc / tw)):
        raw.append("disc")
    for _ in range(round(n * w_pdp / tw)):
        raw.append("pdp")
    while len(raw) < n:
        raw.append("face")
    raw = raw[:n]
    random.shuffle(raw)
    return raw


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--enterprise-id", type=int, default=5)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--batch", type=int, default=400)
    args = ap.parse_args()
    eid = args.enterprise_id

    env = load_env(ENV_PATH)
    host = env.get("DATABASE_HOSTNAME", "127.0.0.1")
    port = int(env.get("DATABASE_HOSTPORT", "3306"))
    user = env.get("DATABASE_USERNAME", "root")
    password = env.get("DATABASE_PASSWORD", "")
    database = env.get("DATABASE_DATABASE", "mbti")
    prefix = env.get("DATABASE_PREFIX", "mbti_")
    wu = f"{prefix}wechat_users"
    tr = f"{prefix}test_results"
    up = f"{prefix}user_profile"

    conn = pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        charset="utf8mb4",
        autocommit=False,
    )
    random.seed(int(time.time()) % 100000 + eid)

    try:
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT COUNT(*) FROM `{wu}` WHERE `enterpriseId`=%s",
                (eid,),
            )
            n_users = int(cur.fetchone()[0])

            target_tests = max(0, round(n_users * 0.40))
            target_today_users = max(0, round(n_users * 0.05))

            cur.execute(
                f"SELECT COUNT(*) FROM `{tr}` WHERE `enterpriseId`=%s",
                (eid,),
            )
            cur_tests = int(cur.fetchone()[0])

            cur.execute(
                f"SELECT `id` FROM `{wu}` WHERE `enterpriseId`=%s ORDER BY `id` ASC",
                (eid,),
            )
            user_ids = [int(r[0]) for r in cur.fetchall()]

        if not user_ids:
            print({"ok": False, "error": "no users for enterprise", "enterpriseId": eid})
            return

        # 1) user_profile 企业池
        profile_sql = f"""
            INSERT INTO `{up}` (
              `userId`, `userType`, `enterpriseId`,
              `testsTotal`, `testsMbti`, `testsDisc`, `testsPdp`, `testsFace`,
              `ordersTotal`, `paidOrders`, `totalPaidAmount`,
              `lastTestResultId`, `lastTestType`, `lastTestAt`,
              `lastMbtiResultId`, `lastDiscResultId`, `lastPdpResultId`, `lastFaceResultId`,
              `createdAt`, `updatedAt`
            )
            SELECT w.`id`, 'enterprise', %s,
              0, 0, 0, 0, 0,
              0, 0, 0,
              NULL, NULL, NULL,
              NULL, NULL, NULL, NULL,
              UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
            FROM `{wu}` w
            WHERE w.`enterpriseId`=%s
            AND NOT EXISTS (
              SELECT 1 FROM `{up}` p
              WHERE p.`userId`=w.`id` AND p.`userType`='enterprise' AND p.`enterpriseId`=%s
            )
        """
        if args.dry_run:
            with conn.cursor() as cur:
                cur.execute(
                    f"""
                    SELECT COUNT(*) FROM `{wu}` w
                    WHERE w.enterpriseId=%s
                    AND NOT EXISTS (
                      SELECT 1 FROM `{up}` p
                      WHERE p.userId=w.id AND p.userType='enterprise' AND p.enterpriseId=%s
                    )
                    """,
                    (eid, eid),
                )
                need_prof = int(cur.fetchone()[0])
        else:
            with conn.cursor() as cur:
                cur.execute(profile_sql, (eid, eid, eid))
                need_prof = cur.rowcount
            conn.commit()

        need_insert = max(0, target_tests - cur_tests)
        types = type_plan(need_insert, (137, 30, 16, 16)) if need_insert else []

        today_start = int(
            time.mktime(
                time.strptime(
                    time.strftime("%Y-%m-%d", time.localtime()) + " 00:00:00",
                    "%Y-%m-%d %H:%M:%S",
                )
            )
        )
        today_end = today_start + 86400 - 1
        day_span = 14
        hist_start = today_start - (day_span - 1) * 86400

        rows: list[tuple] = []
        nu = len(user_ids)
        for i in range(need_insert):
            uid = user_ids[i % nu]
            tt = types[i]
            if i < target_today_users:
                ts = today_start + (i * 37) % 80000
            else:
                # 避免非「今日活跃」样本落在今天，冲掉 5% 口径
                ts = random.randint(hist_start, max(hist_start, today_start - 1))
            rd = result_json(tt, uid * 10007 + i)
            rows.append((uid, tt, rd, ts, ts, eid, "enterprise"))

        ins_sql = f"""
            INSERT INTO `{tr}` (
              `userId`, `testType`, `resultData`, `score`,
              `requiresPayment`, `isPaid`, `paidAmount`, `paidAt`,
              `createdAt`, `updatedAt`, `enterpriseId`, `testScope`
            ) VALUES (
              %s, %s, %s, NULL,
              0, 0, 0, NULL,
              %s, %s, %s, %s
            )
        """

        inserted = 0
        if rows and not args.dry_run:
            with conn.cursor() as cur:
                for b in range(0, len(rows), args.batch):
                    chunk = rows[b : b + args.batch]
                    cur.executemany(ins_sql, chunk)
                    inserted += cur.rowcount
            conn.commit()
        elif rows and args.dry_run:
            inserted = len(rows)

        # 校正今日去重人数：不足则 UPDATE 已有记录的 createdAt 到今天
        with conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT COUNT(DISTINCT userId) FROM `{tr}`
                WHERE enterpriseId=%s AND createdAt>=%s AND createdAt<=%s
                """,
                (eid, today_start, today_end),
            )
            today_u = int(cur.fetchone()[0])

        updated_ts = 0
        if today_u < target_today_users and not args.dry_run:
            deficit = target_today_users - today_u
            with conn.cursor() as cur:
                cur.execute(
                    f"""
                    SELECT w.id FROM `{wu}` w
                    WHERE w.enterpriseId=%s
                    AND w.id NOT IN (
                      SELECT DISTINCT userId FROM `{tr}`
                      WHERE enterpriseId=%s AND createdAt>=%s AND createdAt<=%s
                    )
                    ORDER BY w.id ASC
                    LIMIT %s
                    """,
                    (eid, eid, today_start, today_end, deficit),
                )
                fill_ids = [int(r[0]) for r in cur.fetchall()]
            for j, uid in enumerate(fill_ids):
                with conn.cursor() as cur:
                    cur.execute(
                        f"""
                        SELECT id FROM `{tr}`
                        WHERE enterpriseId=%s AND userId=%s
                        ORDER BY id DESC LIMIT 1
                        """,
                        (eid, uid),
                    )
                    one = cur.fetchone()
                    if not one:
                        continue
                    tid = int(one[0])
                    ts = today_start + (j * 41) % 80000
                    cur.execute(
                        f"UPDATE `{tr}` SET createdAt=%s, updatedAt=%s WHERE id=%s",
                        (ts, ts, tid),
                    )
                    updated_ts += cur.rowcount
            conn.commit()

        with conn.cursor() as cur:
            cur.execute(
                f"SELECT COUNT(*) FROM `{tr}` WHERE `enterpriseId`=%s",
                (eid,),
            )
            final_tests = int(cur.fetchone()[0])
            cur.execute(
                f"""
                SELECT COUNT(DISTINCT userId) FROM `{tr}`
                WHERE enterpriseId=%s AND createdAt>=%s AND createdAt<=%s
                """,
                (eid, today_start, today_end),
            )
            final_today = int(cur.fetchone()[0])
            cur.execute(
                f"""
                SELECT COUNT(*) FROM `{up}`
                WHERE userType='enterprise' AND enterpriseId=%s
                """,
                (eid,),
            )
            prof_cnt = int(cur.fetchone()[0])

        print(
            {
                "ok": True,
                "dry_run": args.dry_run,
                "enterpriseId": eid,
                "n_wechat_users": n_users,
                "target_tests_40pct": target_tests,
                "target_today_users_5pct": target_today_users,
                "before_test_results": cur_tests,
                "profile_rows_enterprise": prof_cnt,
                "user_profile_inserted_or_would": need_prof
                if not args.dry_run
                else need_prof,
                "test_results_inserted_or_would": inserted
                if not args.dry_run
                else len(rows),
                "today_timestamps_updated": updated_ts,
                "after_test_results": final_tests,
                "after_today_distinct_users": final_today,
                "note_pending_15pct": "概览「待审核」在 admin/Dashboard.php 写死为 0，仅改库无法显示 15%。",
            }
        )
    finally:
        conn.close()


if __name__ == "__main__":
    main()

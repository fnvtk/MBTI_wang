#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
为指定企业补「订单运营 + 分销推广」种子数据，量级与 wechat_users 池大致匹配（取总用户约 1/12 订单条数作中间值）。

- orders：enterpriseId 对齐；orderNo 前缀 SEED5_ 可幂等跳过
- 为已支付/已完成订单写入一条 test_results（带 orderId），供分销「产品佣金分布」按测评类型归类
- distribution_agents + distribution_bindings（enterprise 维度）+ commission_records

读 api/.env 的 DATABASE_*。

用法:
  python3 scripts/seed_ent5_orders_and_distribution_mysql.py --enterprise-id 5
  python3 scripts/seed_ent5_orders_and_distribution_mysql.py --enterprise-id 5 --dry-run
"""

from __future__ import annotations

import argparse
import hashlib
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
ORDER_PREFIX = "SEED5_"


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


def pick_type(i: int) -> str:
    r = (i * 7919) % 100
    if r < 55:
        return "face"
    if r < 72:
        return "mbti"
    if r < 86:
        return "disc"
    return "pdp"


def product_title(pt: str) -> str:
    return {
        "face": "AI人脸性格分析完整报告",
        "mbti": "MBTI 职业性格测评",
        "disc": "DISC 行为风格测评",
        "pdp": "PDP 天赋特质测评",
    }.get(pt, "测评服务")


def mini_result_json(pt: str) -> str:
    import json

    if pt == "mbti":
        return json.dumps({"mbtiType": "INTJ", "type": "INTJ"}, ensure_ascii=False)
    if pt == "disc":
        return json.dumps({"description": {"type": "D型"}}, ensure_ascii=False)
    if pt == "pdp":
        return json.dumps({"description": {"type": "老虎型"}}, ensure_ascii=False)
    return json.dumps(
        {"overview": "订单关联占位", "mbti": {"type": "INTJ"}},
        ensure_ascii=False,
    )


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--enterprise-id", type=int, default=5)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--inviters", type=int, default=80)
    ap.add_argument("--bindings", type=int, default=520)
    args = ap.parse_args()
    eid = args.enterprise_id

    env = load_env(ENV_PATH)
    host = env.get("DATABASE_HOSTNAME", "127.0.0.1")
    port = int(env.get("DATABASE_HOSTPORT", "3306"))
    user = env.get("DATABASE_USERNAME", "root")
    password = env.get("DATABASE_PASSWORD", "")
    database = env.get("DATABASE_DATABASE", "mbti")
    prefix = env.get("DATABASE_PREFIX", "mbti_")
    orders_t = f"{prefix}orders"
    tr_t = f"{prefix}test_results"
    wu_t = f"{prefix}wechat_users"
    ag_t = f"{prefix}distribution_agents"
    bd_t = f"{prefix}distribution_bindings"
    cr_t = f"{prefix}commission_records"

    conn = pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        charset="utf8mb4",
        autocommit=False,
    )
    rnd = random.Random(20260330 + eid)
    now = int(time.time())

    try:
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT COUNT(*) FROM `{wu_t}` WHERE `enterpriseId`=%s",
                (eid,),
            )
            n_users = int(cur.fetchone()[0])
            cur.execute(
                f"SELECT `id`,`nickname` FROM `{wu_t}` WHERE `enterpriseId`=%s ORDER BY `id` ASC",
                (eid,),
            )
            rows = cur.fetchall()
        user_ids = [int(r[0]) for r in rows]
        nick_map = {int(r[0]): (r[1] or "") for r in rows}

        if not user_ids:
            print({"ok": False, "error": "no users"})
            return

        target_orders = max(400, min(1400, n_users // 12))
        existing_seed = 0
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT COUNT(*) FROM `{orders_t}` WHERE `orderNo` LIKE %s",
                (ORDER_PREFIX + "%",),
            )
            existing_seed = int(cur.fetchone()[0])

        if existing_seed >= target_orders * 0.9 and not args.dry_run:
            print(
                {
                    "ok": True,
                    "skipped": True,
                    "reason": "SEED5 orders already near target",
                    "existing_seed_orders": existing_seed,
                    "target_orders": target_orders,
                }
            )
            return

        need_orders = max(0, target_orders - existing_seed)
        if args.dry_run:
            print(
                {
                    "ok": True,
                    "dry_run": True,
                    "n_users": n_users,
                    "target_orders": target_orders,
                    "existing_seed": existing_seed,
                    "would_insert_orders": need_orders,
                    "inviters": min(args.inviters, len(user_ids) // 4),
                    "bindings_cap": min(args.bindings, len(user_ids) // 2),
                }
            )
            return

        inviter_n = min(args.inviters, max(20, len(user_ids) // 6))
        inviters = user_ids[:inviter_n]
        pool_bind = user_ids[inviter_n + 50 : inviter_n + 50 + args.bindings]
        if len(pool_bind) < 100:
            pool_bind = user_ids[inviter_n + 10 :]

        # 1) distribution_agents
        inviter_to_agent: dict[int, int] = {}
        with conn.cursor() as cur:
            for uid in inviters:
                cur.execute(f"SELECT id FROM `{ag_t}` WHERE userId=%s LIMIT 1", (uid,))
                ex = cur.fetchone()
                if ex:
                    inviter_to_agent[uid] = int(ex[0])
                    continue
                name = (nick_map.get(uid) or f"用户{uid}")[:100]
                cur.execute(
                    f"""
                    INSERT INTO `{ag_t}`
                    (`userId`,`agentName`,`contactPhone`,`contactEmail`,`totalOrders`,`totalCommission`,
                     `availableCommission`,`status`,`createdAt`,`updatedAt`)
                    VALUES (%s,%s,NULL,NULL,0,0,0,1,%s,%s)
                    """,
                    (uid, name, now, now),
                )
                inviter_to_agent[uid] = int(cur.lastrowid)
        conn.commit()

        # 2) orders + linked test_results (paid/completed only)
        order_rows: list[tuple] = []
        expire_at = now + 86400 * 400
        ins_order = f"""
            INSERT INTO `{orders_t}`
            (`orderNo`,`userId`,`enterpriseId`,`productType`,`productTitle`,`amount`,`status`,
             `payMethod`,`payTime`,`createdAt`,`updatedAt`)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
        """
        ins_tr = f"""
            INSERT INTO `{tr_t}`
            (`userId`,`testType`,`resultData`,`requiresPayment`,`isPaid`,`paidAmount`,`paidAt`,
             `createdAt`,`updatedAt`,`enterpriseId`,`testScope`,`orderId`)
            VALUES (%s,%s,%s,0,1,%s,%s,%s,%s,%s,'enterprise',%s)
        """

        for k in range(need_orders):
            uid = user_ids[(k * 9973) % len(user_ids)]
            pt = pick_type(k)
            amt = rnd.choice([100, 150, 200, 299, 399, 101, 201])
            st = rnd.choices(
                ["paid", "completed", "pending"],
                weights=[42, 28, 30],
                k=1,
            )[0]
            ts = now - rnd.randint(0, 86400 * 20)
            pay_t = ts if st in ("paid", "completed") else None
            h = hashlib.md5(f"{uid}{k}{ts}".encode()).hexdigest()[:10]
            ono = f"{ORDER_PREFIX}{ts}{uid}{k}{h}"[:48]
            order_rows.append(
                (ono, uid, eid, pt, product_title(pt), amt, st, "wechat", pay_t, ts, ts)
            )

        oid_map: list[int] = []
        with conn.cursor() as cur:
            for row in order_rows:
                cur.execute(ins_order, row)
                oid_map.append(int(cur.lastrowid))
                oid = oid_map[-1]
                st = row[6]
                if st not in ("paid", "completed"):
                    continue
                uid = row[1]
                pt = row[3]
                amt = row[5]
                ts = row[9]
                pay_t = row[8] or ts
                cur.execute(
                    ins_tr,
                    (
                        uid,
                        pt,
                        mini_result_json(pt),
                        amt,
                        pay_t,
                        ts,
                        ts,
                        eid,
                        oid,
                    ),
                )
        conn.commit()

        # 3) bindings
        bindings_done = 0
        binding_ids: list[tuple[int, int, int]] = []  # id, inviter, invitee
        with conn.cursor() as cur:
            for i, invitee in enumerate(pool_bind[: args.bindings]):
                inv = inviters[i % len(inviters)]
                if invitee == inv:
                    continue
                cur.execute(
                    f"""
                    SELECT id FROM `{bd_t}`
                    WHERE inviteeId=%s AND scope='enterprise' AND enterpriseId=%s
                    """,
                    (invitee, eid),
                )
                if cur.fetchone():
                    continue
                tsb = now - rnd.randint(86400, 86400 * 60)
                cur.execute(
                    f"""
                    INSERT INTO `{bd_t}`
                    (`inviterId`,`inviteeId`,`scope`,`enterpriseId`,`expireAt`,`status`,
                     `prevInviterId`,`overriddenAt`,`createdAt`,`updatedAt`)
                    VALUES (%s,%s,'enterprise',%s,%s,'active',NULL,NULL,%s,%s)
                    """,
                    (inv, invitee, eid, expire_at, tsb, tsb),
                )
                bid = int(cur.lastrowid)
                binding_ids.append((bid, inv, invitee))
                bindings_done += 1
        conn.commit()

        # 4) commission_records（与订单/绑定挂钩）
        ins_cr = f"""
            INSERT INTO `{cr_t}`
            (`agentId`,`orderId`,`commissionRate`,`commissionAmount`,`status`,`paidAt`,
             `createdAt`,`updatedAt`,`scope`,`enterpriseId`,`inviterId`,`inviteeId`,
             `bindingId`,`orderAmount`,`commissionFen`,`frozenAt`,`unfrozenAt`,
             `testResultId`,`commissionSource`)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'enterprise',%s,%s,%s,%s,%s,%s,NULL,NULL,NULL,%s)
        """
        cr_n = 0
        with conn.cursor() as cur:
            for j, oid in enumerate(oid_map[: min(len(oid_map), 450)]):
                row = order_rows[j]
                if row[6] not in ("paid", "completed"):
                    continue
                uid_buyer = row[1]
                inv = inviters[j % len(inviters)]
                if uid_buyer == inv:
                    inv = inviters[(j + 1) % len(inviters)]
                agent_id = inviter_to_agent.get(inv)
                if not agent_id:
                    continue
                bid_row = next((b for b in binding_ids if b[2] == uid_buyer), None)
                bid = bid_row[0] if bid_row else None
                oamt = row[5]
                cfen = max(10, min(500, int(oamt * rnd.uniform(0.08, 0.22))))
                st_f = rnd.choices(["paid", "frozen"], weights=[82, 18], k=1)[0]
                paid_at = now - rnd.randint(0, 86400 * 14) if st_f == "paid" else None
                tsr = paid_at or (now - rnd.randint(0, 86400 * 10))
                camt = round(cfen / 100, 2)
                cur.execute(
                    ins_cr,
                    (
                        agent_id,
                        oid,
                        10.0,
                        camt,
                        st_f,
                        paid_at,
                        tsr,
                        tsr,
                        eid,
                        inv,
                        uid_buyer,
                        bid,
                        oamt,
                        cfen,
                        "payment",
                    ),
                )
                cr_n += 1

            extra = min(120, len(binding_ids))
            for j in range(extra):
                bid, inv, invitee = binding_ids[j]
                agent_id = inviter_to_agent.get(inv)
                if not agent_id:
                    continue
                cfen = rnd.randint(20, 180)
                st_f = "paid"
                tsr = now - rnd.randint(0, 86400 * 7)
                cur.execute(
                    ins_cr,
                    (
                        agent_id,
                        None,
                        0.0,
                        round(cfen / 100, 2),
                        st_f,
                        tsr,
                        tsr,
                        tsr,
                        eid,
                        inv,
                        invitee,
                        bid,
                        0,
                        cfen,
                        "seed_invite",
                    ),
                )
                cr_n += 1

        conn.commit()

        print(
            {
                "ok": True,
                "enterpriseId": eid,
                "n_users": n_users,
                "inserted_orders": len(oid_map),
                "bindings_inserted": bindings_done,
                "commission_rows": cr_n,
                "note": "已支付订单各带 1 条 test_results(orderId)，概览「已完成测试」会增加；分销饼图按订单关联测评类型归类。",
            }
        )
    finally:
        conn.close()


if __name__ == "__main__":
    main()

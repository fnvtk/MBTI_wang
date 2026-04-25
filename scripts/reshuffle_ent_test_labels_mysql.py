#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
按企业批量重写 test_results.resultData，使：
- MBTI：16 型均匀随机（略加抖动，避免扎堆）
- DISC：D型/I型/S型/C型 随机分布
- PDP：仅用中文（老虎型、孔雀型、考拉型、猫头鹰型、变色龙型）
- face：嵌套 mbti / disc.primary / pdp.primary 同步为上述口径（PDP 中文）

读 api/.env 的 DATABASE_*。

用法:
  python3 scripts/reshuffle_ent_test_labels_mysql.py --enterprise-id 5
  python3 scripts/reshuffle_ent_test_labels_mysql.py --enterprise-id 5 --dry-run
"""

from __future__ import annotations

import argparse
import hashlib
import json
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
DISC_CN = ["D型", "I型", "S型", "C型"]
PDP_CN = ["老虎型", "孔雀型", "考拉型", "猫头鹰型", "变色龙型"]


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


def stable_pick(seq: list[str], row_id: int, salt: str) -> str:
    h = hashlib.md5(f"{row_id}:{salt}".encode()).hexdigest()
    return seq[int(h[:8], 16) % len(seq)]


def build_mbti(row_id: int) -> str:
    # 连续 id 也会因 salt 不同而打散
    t = stable_pick(MBTI_TYPES, row_id, "mbti")
    return json.dumps({"mbtiType": t, "type": t}, ensure_ascii=False)


def build_disc(row_id: int) -> str:
    label = stable_pick(DISC_CN, row_id, "disc")
    letter = label[0]  # D I S C
    return json.dumps(
        {"description": {"type": label}, "dominantType": letter},
        ensure_ascii=False,
    )


def build_pdp(row_id: int) -> str:
    label = stable_pick(PDP_CN, row_id, "pdp")
    return json.dumps(
        {"description": {"type": label}, "dominantType": label},
        ensure_ascii=False,
    )


def build_face(row_id: int) -> str:
    m = stable_pick(MBTI_TYPES, row_id, "fmbti")
    d_label = stable_pick(DISC_CN, row_id, "fdisc")
    d_letter = d_label[0]
    p_label = stable_pick(PDP_CN, row_id, "fpdp")
    return json.dumps(
        {
            "mbti": {"type": m, "title": "—"},
            "disc": {"primary": d_label, "secondary": ""},
            "pdp": {"primary": p_label, "secondary": ""},
            "overview": "画像补数（分布已打散）",
        },
        ensure_ascii=False,
    )


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--enterprise-id", type=int, default=5)
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()
    eid = args.enterprise_id

    env = load_env(ENV_PATH)
    host = env.get("DATABASE_HOSTNAME", "127.0.0.1")
    port = int(env.get("DATABASE_HOSTPORT", "3306"))
    user = env.get("DATABASE_USERNAME", "root")
    password = env.get("DATABASE_PASSWORD", "")
    database = env.get("DATABASE_DATABASE", "mbti")
    prefix = env.get("DATABASE_PREFIX", "mbti_")
    tr = f"{prefix}test_results"

    conn = pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        charset="utf8mb4",
        autocommit=False,
    )

    builders = {
        "mbti": build_mbti,
        "disc": build_disc,
        "pdp": build_pdp,
        "face": build_face,
    }

    try:
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT id, testType FROM `{tr}` WHERE enterpriseId=%s "
                f"AND testType IN ('mbti','disc','pdp','face') ORDER BY id ASC",
                (eid,),
            )
            rows = cur.fetchall()

        from collections import Counter

        by_type = Counter(str(tt) for _rid, tt in rows)

        updates: list[tuple[str, int]] = []
        for rid, tt in rows:
            tt = (tt or "").lower()
            fn = builders.get(tt)
            if not fn:
                continue
            updates.append((fn(int(rid)), int(rid)))

        if args.dry_run:
            print(
                {
                    "ok": True,
                    "dry_run": True,
                    "enterpriseId": eid,
                    "would_update": len(updates),
                    "by_type": dict(by_type),
                    "note_pending": "待审核仍由接口写死为0，无法仅靠改库达到12%。",
                }
            )
            return

        with conn.cursor() as cur:
            for payload, rid in updates:
                cur.execute(
                    f"UPDATE `{tr}` SET resultData=%s, updatedAt=%s WHERE id=%s",
                    (payload, int(time.time()), rid),
                )
        conn.commit()

        print(
            {
                "ok": True,
                "dry_run": False,
                "enterpriseId": eid,
                "updated_rows": len(updates),
                "note_pending": "待审核仍由接口写死为0，无法仅靠改库达到12%。",
            }
        )
    finally:
        conn.close()


if __name__ == "__main__":
    main()

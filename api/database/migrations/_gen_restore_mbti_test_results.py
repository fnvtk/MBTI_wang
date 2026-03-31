# -*- coding: utf-8 -*-
"""Generate restore_mbti_test_results_1_145.sql from api/mbti_data.sql."""
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[2]
src = ROOT / "mbti_data.sql"
out = pathlib.Path(__file__).resolve().parent / "restore_mbti_test_results_1_145.sql"

lines = src.read_text(encoding="utf-8").splitlines()
starts = None
ends = None
for i, L in enumerate(lines):
    if starts is None and L.startswith("INSERT INTO `mbti_test_results`") and "VALUES (1," in L:
        starts = i
    if L.startswith("INSERT INTO `mbti_test_results`") and "VALUES (145," in L:
        ends = i + 1
        break

if starts is None or ends is None:
    raise SystemExit(f"range not found: starts={starts} ends={ends}")

chunk = lines[starts:ends]
head = """-- 恢复 mbti_test_results 表 id 1～145（与 api/mbti_data.sql 中导出一致）
-- 用法：mysql -u... -p... 数据库名 < restore_mbti_test_results_1_145.sql
-- REPLACE 会按主键删除旧行再插入，执行前请备份。
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

"""
body = "\n".join(L.replace("INSERT INTO", "REPLACE INTO", 1) for L in chunk)
tail = """

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
"""
out.write_text(head + body + tail, encoding="utf-8")
print("wrote", out, "statements", len(chunk))

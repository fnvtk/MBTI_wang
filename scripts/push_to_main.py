#!/usr/bin/env python3
# /// script
# requires-python = ">=3.9"
# dependencies = ["requests"]
# ///
"""
将 v0/fnvtk-6c860377 分支的最新 commit 强制更新到 main 分支。
以 v0 分支内容为准覆盖 main。
"""

import requests
import sys

TOKEN = "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"
OWNER = "fnvtk"
REPO  = "MBTI_wang"
SOURCE_BRANCH = "v0/fnvtk-6c860377"
TARGET_BRANCH = "main"

headers = {
    "Authorization": f"token {TOKEN}",
    "Accept": "application/vnd.github.v3+json",
}
base_url = f"https://api.github.com/repos/{OWNER}/{REPO}"

def api(method, path, **kwargs):
    url = base_url + path
    resp = getattr(requests, method)(url, headers=headers, **kwargs)
    return resp

# 1. 获取源分支最新 commit SHA
print(f"[1] 获取 {SOURCE_BRANCH} 最新 commit...")
r = api("get", f"/git/ref/heads/{SOURCE_BRANCH.replace('/', '%2F')}")
if r.status_code != 200:
    print(f"    失败: {r.status_code} {r.text}")
    sys.exit(1)
sha = r.json()["object"]["sha"]
print(f"    SHA: {sha}")

# 2. 强制更新 main 到该 SHA
print(f"[2] 强制更新 {TARGET_BRANCH} -> {sha}...")
r = api("patch", f"/git/refs/heads/{TARGET_BRANCH}", json={"sha": sha, "force": True})
if r.status_code == 200:
    print(f"    成功！{TARGET_BRANCH} 已更新为 {sha[:12]}")
elif r.status_code == 422:
    # main 可能不存在，尝试创建
    print("    main 不存在，尝试创建...")
    r2 = api("post", "/git/refs", json={"ref": f"refs/heads/{TARGET_BRANCH}", "sha": sha})
    if r2.status_code == 201:
        print(f"    创建成功！{TARGET_BRANCH} = {sha[:12]}")
    else:
        print(f"    创建失败: {r2.status_code} {r2.text}")
        sys.exit(1)
else:
    print(f"    失败: {r.status_code} {r.text}")
    sys.exit(1)

print("\n完成！v0 代码已推送到 main 分支。")
print(f"地址: https://github.com/{OWNER}/{REPO}/tree/main")

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
通过 GitHub API 将 v0/fnvtk-6c860377 分支的最新 commit
强制更新到 fnvtk/MBTI_wang 的 main 分支。
使用 Python 标准库 urllib，无需额外依赖。
"""
import json
import urllib.request
import urllib.error
import sys

TOKEN = "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"
OWNER = "fnvtk"
REPO  = "MBTI_wang"
SRC_BRANCH  = "v0/fnvtk-6c860377"
DEST_BRANCH = "main"

BASE = f"https://api.github.com/repos/{OWNER}/{REPO}"
HEADERS = {
    "Authorization": f"Bearer {TOKEN}",
    "Accept": "application/vnd.github+json",
    "X-GitHub-Api-Version": "2022-11-28",
    "User-Agent": "v0-push-script",
}

def api(method: str, path: str, body: dict | None = None):
    url = BASE + path
    data = json.dumps(body).encode() if body else None
    req = urllib.request.Request(url, data=data, headers=HEADERS, method=method)
    try:
        with urllib.request.urlopen(req) as r:
            return json.loads(r.read())
    except urllib.error.HTTPError as e:
        msg = e.read().decode()
        print(f"HTTP {e.code} {method} {path}: {msg}")
        sys.exit(1)

# 1. 获取源分支最新 commit SHA
print(f"获取 {SRC_BRANCH} 分支最新 commit ...")
src = api("GET", f"/git/ref/heads/{SRC_BRANCH.replace('/', '%2F')}")
sha = src["object"]["sha"]
print(f"  SHA: {sha}")

# 2. 尝试更新 main，若不存在则创建
print(f"将 main 分支更新到 {sha} ...")
try:
    result = api("PATCH", "/git/refs/heads/main", {"sha": sha, "force": True})
    print(f"成功！main 已更新到: {result['object']['sha']}")
except SystemExit:
    # main 可能不存在，尝试创建
    print("尝试创建 main 分支 ...")
    result = api("POST", "/git/refs", {"ref": "refs/heads/main", "sha": sha})
    print(f"main 分支已创建，SHA: {result['object']['sha']}")

print("完成！代码已推送到 main。")

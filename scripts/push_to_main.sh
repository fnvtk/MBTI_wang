#!/bin/bash
set -e

TOKEN="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"
REPO="fnvtk/MBTI_wang"
SOURCE_BRANCH="v0/fnvtk-6c860377"
TARGET_BRANCH="main"

echo "[v0] 配置 Git 远端..."
cd /vercel/share/v0-project

git config user.email "v0[bot]@users.noreply.github.com"
git config user.name "v0[bot]"

# 设置带 token 的远端
git remote set-url origin "https://${TOKEN}@github.com/${REPO}.git" 2>/dev/null || \
  git remote add origin "https://${TOKEN}@github.com/${REPO}.git"

echo "[v0] 获取最新远端状态..."
git fetch origin

echo "[v0] 当前分支："
git branch --show-current || git rev-parse --abbrev-ref HEAD

echo "[v0] 当前 HEAD commit："
git log --oneline -3

echo "[v0] 强制推送到 main..."
git push origin HEAD:refs/heads/main --force

echo "[v0] 推送完成！"

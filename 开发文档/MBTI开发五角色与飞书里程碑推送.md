# MBTI王 · 开发五角色与飞书里程碑推送

## 1. Skill 真源（卡若AI，勿放项目内 skills）

- **编号**：F01e（间名 **五方演岗**）  
- **路径**：`/Users/karuo/Documents/个人/卡若AI/04_卡火（火）/火炬_全栈消息/开发五角色与飞书里程碑/SKILL.md`  
- **注册表**：卡若AI 根目录 `SKILL_REGISTRY.md`  
- **说明**：以后只更新卡若AI 内该文件；**不要**在 mbti王 的 `.cursor/skills/` 再放同名 Skill 正文。

## 2. 五角色与推送策略

见 F01e Skill 全文。摘要：**完整功能验收后**才发飞书卡片；小改动不推；每项目独立 Webhook 环境变量。

## 3. MBTI 环境变量与命令

```bash
export FEISHU_WEBHOOK_MBTI='https://open.feishu.cn/open-apis/bot/v2/hook/……'
cd "/Users/karuo/Documents/开发/3、自营项目/mbti王"
python3 scripts/feishu_mbti_milestone_notify.py \
  --feature "小程序埋点全链路 + 超管看板" \
  --milestone "迭代 2026-03" \
  --percent 85 \
  --body $'- 改动点\n- 自测说明'
```

（封装脚本会固定带上 `--webhook-env FEISHU_WEBHOOK_MBTI`、`--product MBTI王`、`--keyword-line`、默认 `--repo`；你仍可追加 `--dry-run` 等参数。）

也可**直接**调用卡若AI 真源脚本（与 F01e 同目录）：

`.../开发五角色与飞书里程碑/feishu_milestone_notify.py`

## 4. 官方参考

- [自定义机器人使用指南](https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot)

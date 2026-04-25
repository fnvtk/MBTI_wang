# 用户运营 · 参考 Soul 永平仓库的规则迭代

> 来源：`/Users/karuo/Documents/开发/3、自营项目/一场soul的创业实验-永平/开发文档/6、后端/算法/`
> - `算法-用户旅程阶段统计.md`（journey-stats / journey-users）
> - `算法-RFM用户价值分层.md`（calcRFMScoreForUser / calcRFMScoreForUserExt）
> 目标：在「管理端 / 用户运营」板块新增 **旅程漏斗** 与 **RFM 价值分层** 两个 Tab，与既有「用户列表 / 测评 Top20」并列。

---

## 一、定位与边界

- 本文档是 **mbti 王** 管理端的定制实施规范，不直接照搬 Soul；
- 读写口径以 **企业数据隔离**为前提：所有接口必须按当前登录企业（`enterpriseId`）过滤，不允许跨企业查询；
- 仅 `enterprise_admin` / `admin` 角色可见；`superadmin` 的对应视图保留在 `/superadmin/Users.vue`。

## 二、旅程漏斗（Journey Funnel）

### 2.1 阶段定义（mbti 业务裁剪）

| 序号 | key | 中文名 | 口径 | 命中表 |
|:----:|:-----|:-----|:-----|:-----|
| 1 | `register` | 注册 | 进入小程序静默登录成功 | `users` 总数 |
| 2 | `bind_phone` | 授权手机号 | `getPhoneNumber` 成功写入 `users.phone` | `users.phone IS NOT NULL AND phone != ''` |
| 3 | `tested` | 完成任意测评 | `mbti_test_results` 至少 1 条 | `mbti_test_results` 去重 `user_id` |
| 4 | `viewed_full` | 看完整报告 | 结果页手机号登录后解锁过 | `user_tracks.action = 'view_full_report'` 去重 `user_id`（新埋点） |
| 5 | `shared` | 发起分享 | 好友 / 朋友圈 | `user_tracks.action = 'share'` 去重 `user_id` |
| 6 | `paid` | 付费解锁 | 存在 `orders.status IN ('paid','success','completed')` | 去重 `user_id` |
| 7 | `repeat` | 复测 | 同一用户测评次数 ≥ 2 | `HAVING count(*) >= 2` |

> 「4. 看完整报告」对应本轮已落地的小程序会话标记 `mbti_phone_login_v1`；后端暂不强依赖该字段，通过埋点 `view_full_report` 捕捉即可。

### 2.2 接口定义

- **`GET /api/admin/users/journey-stats`**
  - 入参：无（从 token 中取 `enterpriseId`）
  - 出参：
    ```jsonc
    {
      "code": 200,
      "data": {
        "stats": {
          "register":     1280,
          "bind_phone":    842,
          "tested":        716,
          "viewed_full":   498,
          "shared":        263,
          "paid":          134,
          "repeat":         86
        },
        "updatedAt": 1765000000
      }
    }
    ```

- **`GET /api/admin/users/journey-users?stage=bind_phone&limit=20`**
  - 返回满足该阶段的用户简化列表：`id / nickname / phone / createdAt`。
  - `limit` 默认 20，最大 100。

### 2.3 前端落点

- 组件：`admin/src/components/UserJourneyPanel.vue`（已落占位）
- 挂载：`admin/src/views/admin/UsersHub.vue` 的 `journey` Tab
- 后端上线后：把 `refresh()` 里的 `mock()` 换成 `request.get('/admin/users/journey-stats')`，按 `stats` 字典填 7 个阶段。

## 三、RFM 价值分层（RFM Level）

### 3.1 口径（与 Soul 完全一致，降低心智成本）

- 订单参与：`orders.status IN ('paid', 'success', 'completed')`
- 子分（0~100，批内 max 归一化）：
  - `rScore = (1 - recencyDays / maxRecency) * 100`
  - `fScore = frequency / maxFreq * 100`
  - `mScore = monetary / maxMonetary * 100`
- 排行接口 **RFM+ 六维**权重：R 25% / F 20% / M 20% / 推荐 15% / 轨迹 10% / 资料 10%
- **档位**：
  - S ≥ 85 · A ≥ 70 · B ≥ 50 · C ≥ 30 · D < 30

### 3.2 接口定义

- **`GET /api/admin/users/rfm?limit=20`**
  - 返回：
    ```jsonc
    {
      "code": 200,
      "data": {
        "list": [
          { "id": 1, "nickname": "徐先生", "phone": "138****2103",
            "r": 92, "f": 88, "m": 648.00, "score": 89, "level": "S" }
        ],
        "maxRecencyDays": 90,
        "maxFreq": 12,
        "maxMonetary": 1288.00
      }
    }
    ```
- **`GET /api/admin/users/rfm-single?userId=`**：单用户 RFM 详情（三维简化版），用于用户详情抽屉展示。

### 3.3 列表接口联动（对齐避免口径差）

与 Soul 相同的坑：用户列表分页里对「当前页」用户也会算 RFM，为了图省事用了 **RFM 三维**，结果和 RFM 排行页（六维）不一致。本项目建议：

- **首版就统一为 RFM 六维**（列表页对当前页用户批量拉轨迹/推荐并调六维打分）；
- 若实现成本较大，先在列表页 RFM 列加说明 tooltip：「此处为三维简化分，与 RFM 排行可能不一致」。

### 3.4 前端落点

- 组件：`admin/src/components/UserRfmPanel.vue`（已落占位）
- 挂载：`admin/src/views/admin/UsersHub.vue` 的 `rfm` Tab
- 后端上线后：把 `refresh()` 里的 `mockRows` 换成 `request.get('/admin/users/rfm')`，`list` 直接喂给 `rows`。

## 四、实施节奏

1. **已完成**：前端 Panel 占位 + 统一视觉（本次迭代）；UsersHub 已加 `journey / rfm` Tab。
2. **下一步（需后端排期）**：
   - 扩后端两个 GET 接口（企业维度过滤）；
   - 上小程序埋点 `view_full_report`（结果页手机号登录解锁全文时调用 `/api/analytics/track`，`action=view_full_report`）；
   - 列表 RFM 列统一为六维。
3. **上线验收**：漏斗每阶段人数 > 0 且单调非增、RFM 档位分布合理、企业之间数据严格隔离。

## 五、与已完成改动的联动

- 与小程序「手机号登录看结果」门禁配合：`viewed_full` 阶段正是**结果页手机号登录解锁全文**后的关键转化节点。
- 与「企业概览 · 近 14 日趋势」互补：14 日趋势看**每日完成人次**，漏斗看**累计转化比**，两张图不要合并。

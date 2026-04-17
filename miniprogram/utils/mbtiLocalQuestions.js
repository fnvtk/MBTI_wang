// utils/mbtiLocalQuestions.js
// MBTI 本地 fallback 题库 —— 仅当 /api/test/questions 不可达时使用
// 覆盖四维度（E/I · S/N · T/F · J/P）各 15 题，共 60 题
// 注意：不替代线上题库，仅保底；结果计算走 pages/test/mbti.js 的 calculateResult
//
// 题目结构：{ id, text, dimension: 'EI'|'SN'|'TF'|'JP', options: [{ text, value }] }
// value 必须是 'E' / 'I' / 'S' / 'N' / 'T' / 'F' / 'J' / 'P' 八个字母之一

const QUESTIONS = [
  // ==================== E / I 外向 vs 内向（15 题）====================
  { id: 'L-EI-01', text: '参加聚会时，你更享受：', dimension: 'EI', options: [
    { text: '与很多人交谈互动', value: 'E' },
    { text: '和少数几个人深聊', value: 'I' }
  ]},
  { id: 'L-EI-02', text: '经过忙碌的一天，你更倾向于：', dimension: 'EI', options: [
    { text: '出门找朋友放松', value: 'E' },
    { text: '一个人独处充电', value: 'I' }
  ]},
  { id: 'L-EI-03', text: '遇到问题时，你先：', dimension: 'EI', options: [
    { text: '找人一起讨论', value: 'E' },
    { text: '一个人默默思考', value: 'I' }
  ]},
  { id: 'L-EI-04', text: '在新环境里，你通常：', dimension: 'EI', options: [
    { text: '主动跟大家打招呼', value: 'E' },
    { text: '先观察再慢慢融入', value: 'I' }
  ]},
  { id: 'L-EI-05', text: '你更喜欢的工作方式：', dimension: 'EI', options: [
    { text: '多人协作沟通推进', value: 'E' },
    { text: '独立专注深度工作', value: 'I' }
  ]},
  { id: 'L-EI-06', text: '面对一群陌生人，你会：', dimension: 'EI', options: [
    { text: '感到兴奋，期待结识', value: 'E' },
    { text: '感到疲惫，想早离场', value: 'I' }
  ]},
  { id: 'L-EI-07', text: '你表达想法时更习惯：', dimension: 'EI', options: [
    { text: '边说边想，口头表达', value: 'E' },
    { text: '先想好再开口或写下', value: 'I' }
  ]},
  { id: 'L-EI-08', text: '周末你更想：', dimension: 'EI', options: [
    { text: '约上朋友热闹一场', value: 'E' },
    { text: '一个人看书、发呆', value: 'I' }
  ]},
  { id: 'L-EI-09', text: '在会议中你更容易：', dimension: 'EI', options: [
    { text: '积极发言分享观点', value: 'E' },
    { text: '认真倾听再发言', value: 'I' }
  ]},
  { id: 'L-EI-10', text: '你的能量来源主要是：', dimension: 'EI', options: [
    { text: '和他人的互动交流', value: 'E' },
    { text: '独处的安静时光', value: 'I' }
  ]},
  { id: 'L-EI-11', text: '认识新朋友时你更看重：', dimension: 'EI', options: [
    { text: '广泛的社交圈', value: 'E' },
    { text: '少数深度的友谊', value: 'I' }
  ]},
  { id: 'L-EI-12', text: '演讲或发言机会出现时你：', dimension: 'EI', options: [
    { text: '愿意尝试，享受舞台', value: 'E' },
    { text: '倾向避免，偏好幕后', value: 'I' }
  ]},
  { id: 'L-EI-13', text: '你更容易：', dimension: 'EI', options: [
    { text: '主动开启对话', value: 'E' },
    { text: '等别人先开口', value: 'I' }
  ]},
  { id: 'L-EI-14', text: '一天下来话多了你会：', dimension: 'EI', options: [
    { text: '越讲越来劲', value: 'E' },
    { text: '想找个安静角落', value: 'I' }
  ]},
  { id: 'L-EI-15', text: '沉默的氛围你觉得：', dimension: 'EI', options: [
    { text: '尴尬，想打破', value: 'E' },
    { text: '舒服，享受宁静', value: 'I' }
  ]},

  // ==================== S / N 感觉 vs 直觉（15 题）====================
  { id: 'L-SN-01', text: '你更关注：', dimension: 'SN', options: [
    { text: '眼下具体的事实', value: 'S' },
    { text: '未来的可能性', value: 'N' }
  ]},
  { id: 'L-SN-02', text: '学习新知识时你偏好：', dimension: 'SN', options: [
    { text: '从实际案例入手', value: 'S' },
    { text: '先理解整体框架', value: 'N' }
  ]},
  { id: 'L-SN-03', text: '做决定时你更依赖：', dimension: 'SN', options: [
    { text: '过往经验与数据', value: 'S' },
    { text: '直觉与潜在趋势', value: 'N' }
  ]},
  { id: 'L-SN-04', text: '描述事物时你更倾向：', dimension: 'SN', options: [
    { text: '具体细节', value: 'S' },
    { text: '抽象概念', value: 'N' }
  ]},
  { id: 'L-SN-05', text: '面对项目，你会先：', dimension: 'SN', options: [
    { text: '列清具体步骤', value: 'S' },
    { text: '构思大方向与创意', value: 'N' }
  ]},
  { id: 'L-SN-06', text: '你更欣赏的书籍：', dimension: 'SN', options: [
    { text: '写实传记、工具书', value: 'S' },
    { text: '哲学、科幻、象征文学', value: 'N' }
  ]},
  { id: 'L-SN-07', text: '谈论"未来五年"，你会：', dimension: 'SN', options: [
    { text: '列出具体目标与路径', value: 'S' },
    { text: '畅想各种可能性', value: 'N' }
  ]},
  { id: 'L-SN-08', text: '接到任务你先问：', dimension: 'SN', options: [
    { text: '怎么做、截止时间？', value: 'S' },
    { text: '为什么做、意义是？', value: 'N' }
  ]},
  { id: 'L-SN-09', text: '你喜欢的工作类型：', dimension: 'SN', options: [
    { text: '流程清晰、步骤明确', value: 'S' },
    { text: '需要创新与想象', value: 'N' }
  ]},
  { id: 'L-SN-10', text: '看风景时你会：', dimension: 'SN', options: [
    { text: '留意植物、天气、光线', value: 'S' },
    { text: '联想到某种心情或意境', value: 'N' }
  ]},
  { id: 'L-SN-11', text: '聊天中你更容易：', dimension: 'SN', options: [
    { text: '讲具体发生的事', value: 'S' },
    { text: '延伸到理论或类比', value: 'N' }
  ]},
  { id: 'L-SN-12', text: '解决问题时你更看重：', dimension: 'SN', options: [
    { text: '验证过的成熟方法', value: 'S' },
    { text: '新颖独特的切入点', value: 'N' }
  ]},
  { id: 'L-SN-13', text: '你的注意力更多放在：', dimension: 'SN', options: [
    { text: '此刻正在发生的事', value: 'S' },
    { text: '事情背后的模式', value: 'N' }
  ]},
  { id: 'L-SN-14', text: '你相信：', dimension: 'SN', options: [
    { text: '看得见的才是真的', value: 'S' },
    { text: '灵感与预感常常正确', value: 'N' }
  ]},
  { id: 'L-SN-15', text: '别人说你的特点：', dimension: 'SN', options: [
    { text: '踏实、可靠、注重细节', value: 'S' },
    { text: '想象力丰富、爱畅想', value: 'N' }
  ]},

  // ==================== T / F 思考 vs 情感（15 题）====================
  { id: 'L-TF-01', text: '做决定时你更看重：', dimension: 'TF', options: [
    { text: '逻辑与客观事实', value: 'T' },
    { text: '感受与人际影响', value: 'F' }
  ]},
  { id: 'L-TF-02', text: '朋友向你倾诉时你会：', dimension: 'TF', options: [
    { text: '分析原因、给出建议', value: 'T' },
    { text: '共情、陪伴感受', value: 'F' }
  ]},
  { id: 'L-TF-03', text: '批评同事工作时你更倾向：', dimension: 'TF', options: [
    { text: '直接指出问题', value: 'T' },
    { text: '委婉给建议', value: 'F' }
  ]},
  { id: 'L-TF-04', text: '你认为好决策要：', dimension: 'TF', options: [
    { text: '公正合理', value: 'T' },
    { text: '让人感到被尊重', value: 'F' }
  ]},
  { id: 'L-TF-05', text: '冲突中你更关注：', dimension: 'TF', options: [
    { text: '谁对谁错', value: 'T' },
    { text: '大家的感受', value: 'F' }
  ]},
  { id: 'L-TF-06', text: '工作评估你更相信：', dimension: 'TF', options: [
    { text: '数据与绩效', value: 'T' },
    { text: '态度与团队氛围', value: 'F' }
  ]},
  { id: 'L-TF-07', text: '你评价自己是：', dimension: 'TF', options: [
    { text: '理性、客观', value: 'T' },
    { text: '温暖、善解人意', value: 'F' }
  ]},
  { id: 'L-TF-08', text: '看电影时你更容易：', dimension: 'TF', options: [
    { text: '分析剧情逻辑', value: 'T' },
    { text: '被角色情绪打动', value: 'F' }
  ]},
  { id: 'L-TF-09', text: '他人失败时你会说：', dimension: 'TF', options: [
    { text: '看看哪里可以改进', value: 'T' },
    { text: '你辛苦了，别太难受', value: 'F' }
  ]},
  { id: 'L-TF-10', text: '买东西时你最在意：', dimension: 'TF', options: [
    { text: '性价比', value: 'T' },
    { text: '是否让自己或亲人开心', value: 'F' }
  ]},
  { id: 'L-TF-11', text: '谈判中你更可能：', dimension: 'TF', options: [
    { text: '坚持立场，据理力争', value: 'T' },
    { text: '寻求双方都舒服的方案', value: 'F' }
  ]},
  { id: 'L-TF-12', text: '接受反馈时你更在意：', dimension: 'TF', options: [
    { text: '反馈是否准确', value: 'T' },
    { text: '对方是否尊重你', value: 'F' }
  ]},
  { id: 'L-TF-13', text: '面对选择你先问自己：', dimension: 'TF', options: [
    { text: '哪个更合理？', value: 'T' },
    { text: '哪个让我更安心？', value: 'F' }
  ]},
  { id: 'L-TF-14', text: '你欣赏的领导：', dimension: 'TF', options: [
    { text: '目标清晰、决策果断', value: 'T' },
    { text: '关心员工、能激励人', value: 'F' }
  ]},
  { id: 'L-TF-15', text: '朋友犯错时你更倾向：', dimension: 'TF', options: [
    { text: '坦诚指出', value: 'T' },
    { text: '先理解再委婉提醒', value: 'F' }
  ]},

  // ==================== J / P 判断 vs 知觉（15 题）====================
  { id: 'L-JP-01', text: '你更喜欢：', dimension: 'JP', options: [
    { text: '计划好再行动', value: 'J' },
    { text: '边走边看，保持灵活', value: 'P' }
  ]},
  { id: 'L-JP-02', text: '你的桌面/房间通常：', dimension: 'JP', options: [
    { text: '整洁有序', value: 'J' },
    { text: '凌乱但自有章法', value: 'P' }
  ]},
  { id: 'L-JP-03', text: '旅行你更愿意：', dimension: 'JP', options: [
    { text: '提前订好行程', value: 'J' },
    { text: '随性出发，临时决定', value: 'P' }
  ]},
  { id: 'L-JP-04', text: '截止日期临近你会：', dimension: 'JP', options: [
    { text: '很早开始，提前完成', value: 'J' },
    { text: '最后冲刺，效率最高', value: 'P' }
  ]},
  { id: 'L-JP-05', text: '你喜欢的待办清单：', dimension: 'JP', options: [
    { text: '打勾一条一条完成', value: 'J' },
    { text: '灵活选择，心情驱动', value: 'P' }
  ]},
  { id: 'L-JP-06', text: '改变计划时你：', dimension: 'JP', options: [
    { text: '感到不安，希望稳定', value: 'J' },
    { text: '欢迎变化，觉得有趣', value: 'P' }
  ]},
  { id: 'L-JP-07', text: '你做决定的速度：', dimension: 'JP', options: [
    { text: '果断，能尽快确定', value: 'J' },
    { text: '偏慢，倾向保留选择', value: 'P' }
  ]},
  { id: 'L-JP-08', text: '你工作中更倾向：', dimension: 'JP', options: [
    { text: '设定目标并推进完成', value: 'J' },
    { text: '探索各种可能再定', value: 'P' }
  ]},
  { id: 'L-JP-09', text: '收到邀请你会：', dimension: 'JP', options: [
    { text: '马上确认或婉拒', value: 'J' },
    { text: '拖一阵看情况再定', value: 'P' }
  ]},
  { id: 'L-JP-10', text: '你更喜欢的节奏：', dimension: 'JP', options: [
    { text: '稳定可控的日常', value: 'J' },
    { text: '充满惊喜的变化', value: 'P' }
  ]},
  { id: 'L-JP-11', text: '面对突发任务你：', dimension: 'JP', options: [
    { text: '感到打乱、需要调整', value: 'J' },
    { text: '随机应变、不太介意', value: 'P' }
  ]},
  { id: 'L-JP-12', text: '你喜欢的工作方式：', dimension: 'JP', options: [
    { text: '按流程一步一步', value: 'J' },
    { text: '灵感来了就一口气干', value: 'P' }
  ]},
  { id: 'L-JP-13', text: '别人评价你：', dimension: 'JP', options: [
    { text: '有条理、守时', value: 'J' },
    { text: '随性、有弹性', value: 'P' }
  ]},
  { id: 'L-JP-14', text: '面对未完成的事你：', dimension: 'JP', options: [
    { text: '强迫自己收尾', value: 'J' },
    { text: '先放一放，换个状态', value: 'P' }
  ]},
  { id: 'L-JP-15', text: '你的生活哲学：', dimension: 'JP', options: [
    { text: '计划是成功的一半', value: 'J' },
    { text: '变化是唯一的不变', value: 'P' }
  ]}
]

/** 返回本地 MBTI 题库（60 题） */
function getMbtiLocalQuestions() {
  return QUESTIONS.map((q) => ({
    id: q.id,
    text: q.text,
    dimension: q.dimension,
    options: (q.options || []).map((o) => ({ ...o }))
  }))
}

module.exports = { getMbtiLocalQuestions }

/**
 * MBTI 深度洞察映射：心智模式 / 决策倾向 / 能量来源 / 压力反应
 * 仅用于结果页排版展示，不替代后端详细解读
 */

// 每个 MBTI 8 个维度：心智 / 决策 / 能量 / 压力 / 沟通 / 领导 / 恋爱 / 团队角色
const MBTI_INSIGHTS = {
  ISTJ: { mind: '系统守护者', decision: '逻辑·数据驱动', energy: '独处·专注',       stress: '偏向沉默',   comm: '简洁务实', lead: '按规章推进',   love: '稳定承诺',     team: '制度执行' },
  ISFJ: { mind: '温柔守护者', decision: '情感·他人影响', energy: '独处·关系修复',   stress: '偏向内耗',   comm: '体贴谦和', lead: '幕后支持',     love: '默默付出',     team: '后勤保障' },
  INFJ: { mind: '远见策划者', decision: '价值·直觉',     energy: '独处·深谈',       stress: '过度共情',   comm: '一对一深度', lead: '愿景感召',   love: '理想伴侣',     team: '战略策划' },
  INTJ: { mind: '战略建筑师', decision: '长远·体系',     energy: '独处·深度思考',   stress: '批判他人',   comm: '逻辑直击', lead: '结果导向',     love: '精选长期',     team: '战略架构' },
  ISTP: { mind: '机巧工匠',   decision: '现场·效用',     energy: '独立任务',         stress: '逃避沟通',   comm: '极简',     lead: '示范而非说教', love: '保留空间',     team: '专家救火' },
  ISFP: { mind: '灵感艺术家', decision: '此刻·感受',     energy: '自然·艺术',       stress: '回避冲突',   comm: '温柔细腻', lead: '以身作则',     love: '浪漫直觉',     team: '美学与关怀' },
  INFP: { mind: '理想调停者', decision: '价值·情感',     energy: '独处·写作',       stress: '自我怀疑',   comm: '真诚倾听', lead: '价值驱动',     love: '灵魂伴侣',     team: '灵感与文化' },
  INTP: { mind: '逻辑探索者', decision: '体系·真相',     energy: '独处·推演',       stress: '拖延回避',   comm: '抽象表达', lead: '思想影响',     love: '智力共鸣',     team: '问题拆解' },
  ESTP: { mind: '现场行动派', decision: '快反·结果',     energy: '高强度互动',       stress: '冲动决策',   comm: '直接豪爽', lead: '临场指挥',     love: '刺激新鲜',     team: '业务开拓' },
  ESFP: { mind: '热情表演者', decision: '氛围·人情',     energy: '社交·舞台',       stress: '注意力漂移', comm: '热情表达', lead: '激励氛围',     love: '陪伴热烈',     team: '气氛担当' },
  ENFP: { mind: '点子制造机', decision: '灵感·意义',     energy: '头脑风暴',         stress: '摊子铺开',   comm: '富有感染力', lead: '赋能他人',   love: '热烈真诚',     team: '点子发动机' },
  ENTP: { mind: '辩论革新者', decision: '可能性·逻辑',   energy: '激辩·跨界',       stress: '承诺恐惧',   comm: '思辨锋利', lead: '创新驱动',     love: '智力挑战',     team: '方案颠覆者' },
  ESTJ: { mind: '制度管理者', decision: '规则·效率',     energy: '组织·执行',       stress: '固执己见',   comm: '直截了当', lead: '纪律严明',     love: '务实承诺',     team: '运营负责人' },
  ESFJ: { mind: '关系协调者', decision: '关系·共识',     energy: '服务他人',         stress: '情绪透支',   comm: '关怀周到', lead: '凝聚团队',     love: '家庭优先',     team: '人和担当' },
  ENFJ: { mind: '魅力引领者', decision: '价值·共赢',     energy: '激励他人',         stress: '过度付出',   comm: '感召力强', lead: '愿景领导',     love: '深度连接',     team: '人才教练' },
  ENTJ: { mind: '战略指挥官', decision: '目标·控制',     energy: '掌舵决策',         stress: '压迫他人',   comm: '高压直接', lead: '铁腕执行',     love: '强强联合',     team: '全局指挥官' }
}

const MBTI_CATEGORY_TAGS = {
  守护者: ['稳健', '高执行', '责任感'],
  理想主义者: ['远见', '共情', '追求意义'],
  探险家: ['灵活', '务实', '上手即用'],
  分析家: ['独立', '体系', '战略']
}

/** 每种 MBTI 对应的职业领域（粗分） */
const MBTI_FIELDS = {
  ISTJ: ['金融/审计', '项目管理', '行政/法务'],
  ISFJ: ['医疗护理', '教育教学', '人事/行政'],
  INFJ: ['心理咨询', '内容创作', '人力资源'],
  INTJ: ['战略咨询', '科研/架构', '投资分析'],
  ISTP: ['工程/技术', '运动/制造', '应急指挥'],
  ISFP: ['设计/艺术', '摄影/音乐', '手作/园艺'],
  INFP: ['写作/编辑', '心理咨询', '社会公益'],
  INTP: ['程序/数据', '研究/学术', '专业咨询'],
  ESTP: ['销售/创业', '现场运营', '体育/冒险'],
  ESFP: ['演艺/主播', '销售/公关', '活动策划'],
  ENFP: ['市场营销', '新媒体/内容', '品牌/咨询'],
  ENTP: ['创投/产品', '律师/咨询', '创业孵化'],
  ESTJ: ['业务管理', '项目/运营', '律政/执法'],
  ESFJ: ['教育/社群', '医疗/HR', '服务业管理'],
  ENFJ: ['培训/教育', '战略 HR', '咨询/布道'],
  ENTJ: ['企业管理', '创业/咨询', '投资并购']
}

/** 每种 MBTI 的通用发展建议 */
const MBTI_GROWTH = {
  ISTJ: ['尝试跨部门/跨行业轮岗，打破思维惯性', '在流程中保留一小部分"实验预算"，平衡稳健与创新', '主动请教外部导师，避免只在同温层交流'],
  ISFJ: ['学习温和表达不同意见，照顾自己的边界', '把"帮他人"的精力留出 20% 投入自我成长', '记录自己的成就，避免被低估'],
  INFJ: ['把宏大愿景拆成 30 天可落地的小任务', '允许自己"不完美"，先完成再完善', '建立能真实表达负面情绪的小圈子'],
  INTJ: ['在战略外，刻意练习倾听与共情表达', '定期做 360° 反馈，避免盲区', '允许临时方案，不必所有事都等最优解'],
  ISTP: ['把动手经验总结成可复用的方法论', '每周主动对接一次跨职能协作', '用短视频/文档分享技巧，增加影响力'],
  ISFP: ['把灵感产品化，建立可重复的作品节奏', '学习基础商业/销售语言，提升溢价能力', '主动展示作品，避免完美主义延迟发布'],
  INFP: ['用写作/直播稳定输出价值观与作品', '把爱的事做成可持续的小商业', '允许自己设置边界，减少情绪内耗'],
  INTP: ['把研究成果转化成可复用的模型或 SaaS', '刻意练习快速决策，避免永远在调研', '加入高密度协作团队，补上执行力'],
  ESTP: ['把短期爆发转成长期节奏，做 3 年目标', '留出固定时间做深度学习，补理论短板', '在胜利后主动复盘，避免只重过程'],
  ESFP: ['选一件事精深 3 年，避免永远浅尝', '训练结构化思考，提升提案说服力', '主动承担"最后一公里"落地执行'],
  ENFP: ['聚焦 3 件最重要的事，放下另外 17 件', '训练收敛与决策，不只输出想法', '每季度复盘把点子变落地的转化率'],
  ENTP: ['选一个长线赛道深耕 5 年以上', '把辩论能量转化为共识工作坊', '把 80% 的新想法直接拒绝，只做最强的'],
  ESTJ: ['保留 10% 的"松弛时间"，允许过程不完美', '刻意练习聆听不同声音，避免强推', '把业务经验系统化输出为培训/标准'],
  ESFJ: ['照顾他人前先照顾自己，设立边界', '建立清晰的绩效标准，减少情绪波动', '把对关系的敏感变成"顾问资产"输出'],
  ENFJ: ['合理授权，避免把所有情绪都抱回家', '保留独处时间修复能量', '训练战略视角，补上"冷思考"维度'],
  ENTJ: ['训练情绪柔软度，避免让下属恐惧', '保留时间做价值观对齐，不只追目标', '主动授权并培养接班人，防止一个人撑整个团队']
}

/**
 * @param {string} code 如 'INTJ'
 * @returns {{mind:string,decision:string,energy:string,stress:string}|null}
 */
function getMbtiInsight(code) {
  const c = (code || '').toUpperCase()
  return MBTI_INSIGHTS[c] || null
}

function getMbtiCategoryTags(category) {
  const key = (category || '').trim()
  return MBTI_CATEGORY_TAGS[key] || []
}

/**
 * 为职业列表补充 emoji 图标，前端 grid 展示
 */
function decorateCareers(list) {
  const iconMap = {
    CEO: '👔', 管理者: '🧭', 顾问: '🧠', 律师: '⚖️', 战略顾问: '🎯',
    科学家: '🔬', 架构师: '🏛️', 程序员: '💻', 研究员: '📚', 分析师: '📊',
    数据分析师: '📈', 工程师: '🛠️', 技术员: '🔧', 会计师: '📒', 审计师: '🧾',
    项目经理: '📋', 市场营销: '📣', 公关: '🎤', 培训师: '🎓', 记者: '📰',
    设计师: '🎨', 艺术家: '🖌️', 摄影师: '📷', 演员: '🎬', 主持人: '🎙️',
    企业家: '🚀', 销售: '💼', 律所合伙人: '⚖️', 心理咨询: '💬', 作家: '✒️',
    社工: '🤝', 护士: '🩺', 教师: '📖', 人力资源: '👥', 行政: '🗂️',
    咨询: '🧭', 产品经理: '📐', 顾问师: '🧠', 飞行员: '✈️', 运动员: '🏅',
    自由职业者: '🌐', 协调员: '🔗'
  }
  return (list || []).map((name) => ({
    name: String(name || '').trim(),
    icon: iconMap[String(name || '').trim()] || '💡'
  }))
}

function getMbtiFields(code) {
  const c = (code || '').toUpperCase()
  return MBTI_FIELDS[c] || []
}

function getMbtiGrowth(code) {
  const c = (code || '').toUpperCase()
  return MBTI_GROWTH[c] || []
}

module.exports = {
  getMbtiInsight,
  getMbtiCategoryTags,
  getMbtiFields,
  getMbtiGrowth,
  decorateCareers
}

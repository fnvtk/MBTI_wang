// utils/descriptions.js - 测试结果描述

// MBTI类型描述
const mbtiDescriptions = {
  ISTJ: { type: "ISTJ", name: "物流师", category: "守护者", description: "务实、负责、可靠的传统主义者", strengths: ["可靠负责", "注重细节", "有条理"], weaknesses: ["可能过于固执", "不喜变化"], careers: ["会计师", "审计师", "项目经理"] },
  ISFJ: { type: "ISFJ", name: "守卫者", category: "守护者", description: "安静、友好、负责任的守护者", strengths: ["忠诚体贴", "观察力强", "耐心"], weaknesses: ["不善拒绝", "过于谦虚"], careers: ["护士", "教师", "行政"] },
  INFJ: { type: "INFJ", name: "提倡者", category: "理想主义者", description: "寻求意义和联系的理想主义者", strengths: ["洞察力强", "有远见", "坚定"], weaknesses: ["过于理想化", "容易疲惫"], careers: ["心理咨询", "作家", "人力资源"] },
  INTJ: { type: "INTJ", name: "建筑师", category: "理想主义者", description: "独立、有战略眼光的思考者", strengths: ["战略思维", "独立自信", "意志坚定"], weaknesses: ["可能傲慢", "过于苛刻"], careers: ["战略顾问", "科学家", "架构师"] },
  ISTP: { type: "ISTP", name: "鉴赏家", category: "探险家", description: "灵活、务实的问题解决者", strengths: ["适应力强", "动手能力", "冷静"], weaknesses: ["可能冷漠", "不善表达"], careers: ["工程师", "技术员", "飞行员"] },
  ISFP: { type: "ISFP", name: "艺术家", category: "探险家", description: "温和、敏感的艺术家", strengths: ["创造力", "同理心", "灵活"], weaknesses: ["过于敏感", "避免冲突"], careers: ["设计师", "艺术家", "摄影师"] },
  INFP: { type: "INFP", name: "调停者", category: "理想主义者", description: "理想主义、忠诚的调解者", strengths: ["创造力", "同理心", "真诚"], weaknesses: ["过于理想化", "情绪化"], careers: ["作家", "心理咨询", "社工"] },
  INTP: { type: "INTP", name: "逻辑学家", category: "理想主义者", description: "创新、逻辑的思考者", strengths: ["逻辑思维", "创新能力", "客观"], weaknesses: ["可能孤僻", "忽视情感"], careers: ["程序员", "研究员", "分析师"] },
  ESTP: { type: "ESTP", name: "动力者", category: "探险家", description: "精力充沛、务实的行动者", strengths: ["果断", "务实", "善于应变"], weaknesses: ["可能冲动", "缺乏耐心"], careers: ["销售", "企业家", "运动员"] },
  ESFP: { type: "ESFP", name: "表演者", category: "探险家", description: "热情、友好的社交达人", strengths: ["热情友好", "乐观", "灵活"], weaknesses: ["可能肤浅", "容易分心"], careers: ["演员", "销售", "主持人"] },
  ENFP: { type: "ENFP", name: "竞选者", category: "理想主义者", description: "热情、有创造力的社交者", strengths: ["创造力", "热情", "善于沟通"], weaknesses: ["可能不切实际", "缺乏专注"], careers: ["市场营销", "记者", "顾问"] },
  ENTP: { type: "ENTP", name: "辩论家", category: "理想主义者", description: "聪明、好奇的思想家", strengths: ["创新能力", "辩论能力", "适应力"], weaknesses: ["可能争辩", "不善执行"], careers: ["律师", "企业家", "咨询"] },
  ESTJ: { type: "ESTJ", name: "管理者", category: "守护者", description: "务实、果断的组织者", strengths: ["领导力", "组织能力", "务实"], weaknesses: ["可能专制", "不够灵活"], careers: ["管理者", "项目经理", "律师"] },
  ESFJ: { type: "ESFJ", name: "执政官", category: "守护者", description: "热心、合作的支持者", strengths: ["关心他人", "负责任", "善于合作"], weaknesses: ["过于在意评价", "不善拒绝"], careers: ["教师", "护士", "人力资源"] },
  ENFJ: { type: "ENFJ", name: "主人公", category: "理想主义者", description: "有魅力、鼓舞人心的领导者", strengths: ["领导力", "同理心", "说服力"], weaknesses: ["过于理想化", "过度付出"], careers: ["培训师", "顾问", "教师"] },
  ENTJ: { type: "ENTJ", name: "指挥官", category: "理想主义者", description: "大胆、有远见的领导者", strengths: ["领导力", "战略思维", "果断"], weaknesses: ["可能专制", "不耐烦"], careers: ["CEO", "企业家", "律师"] }
}

// DISC类型描述
const discDescriptions = {
  D: { type: "D型", title: "力量", color: "#EF4444", description: "天生的领导者，注重结果和效率", strengths: ["决断力强", "目标导向", "行动迅速"], weaknesses: ["可能过于强势", "缺乏耐心"], careers: ["企业高管", "创业者", "项目经理"] },
  I: { type: "I型", title: "活跃", color: "#F59E0B", description: "热情友好，善于社交和表达", strengths: ["善于沟通", "乐观积极", "创意丰富"], weaknesses: ["可能过于乐观", "注意力分散"], careers: ["市场营销", "公关", "培训师"] },
  S: { type: "S型", title: "和平", color: "#10B981", description: "稳重可靠，注重团队和谐", strengths: ["可靠稳定", "团队协作", "耐心倾听"], weaknesses: ["可能抗拒变化", "决策较慢"], careers: ["人力资源", "客户服务", "行政"] },
  C: { type: "C型", title: "完美", color: "#3B82F6", description: "注重细节和准确性，追求卓越", strengths: ["分析能力强", "注重细节", "准确严谨"], weaknesses: ["可能过于完美主义", "决策较慢"], careers: ["数据分析", "工程师", "会计师"] }
}

// PDP类型描述
const pdpDescriptions = {
  Tiger: { type: "老虎型", emoji: "🐅", title: "支配者", color: "#F59E0B", description: "天生的领导者，具有强烈的目标导向和执行力", strengths: ["决断力强", "目标导向", "行动迅速"], weaknesses: ["可能过于强势", "缺乏耐心"], careers: ["企业高管", "创业者", "项目经理"], teamRole: "适合担任领导者角色" },
  Peacock: { type: "孔雀型", emoji: "🦚", title: "表现者", color: "#8B5CF6", description: "热情友好，善于表达和社交", strengths: ["善于沟通", "乐观积极", "影响力强"], weaknesses: ["可能过于乐观", "注意力分散"], careers: ["市场营销", "公关", "培训师"], teamRole: "适合担任激励者和调解者角色" },
  Koala: { type: "无尾熊型", emoji: "🐨", title: "支持者", color: "#10B981", description: "稳重可靠，注重团队和谐", strengths: ["可靠稳定", "团队协作", "善解人意"], weaknesses: ["可能抗拒变化", "决策较慢"], careers: ["人力资源", "客户服务", "教师"], teamRole: "适合担任协调者和支持者角色" },
  Owl: { type: "猫头鹰型", emoji: "🦉", title: "分析者", color: "#3B82F6", description: "注重细节和准确性，追求完美", strengths: ["分析能力强", "注重细节", "系统思维"], weaknesses: ["可能过于完美主义", "不善社交"], careers: ["数据分析师", "工程师", "研究员"], teamRole: "适合担任专家和质量控制者角色" },
  Chameleon: { type: "变色龙型", emoji: "🦎", title: "整合者", color: "#06B6D4", description: "适应能力强，能在不同情境中灵活调整", strengths: ["适应力强", "灵活多变", "平衡能力"], weaknesses: ["可能缺乏主见", "身份认同模糊"], careers: ["咨询顾问", "协调员", "自由职业者"], teamRole: "适合担任协调者和多面手角色" }
}

module.exports = {
  mbtiDescriptions,
  discDescriptions,
  pdpDescriptions
}

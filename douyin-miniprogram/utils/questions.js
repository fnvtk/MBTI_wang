// utils/questions.js - 测试题库

// MBTI测试题（精简版30题，每个维度约7-8题）
const mbtiQuestions = [
  // E vs I 维度 (8题)
  { id: 1, question: "在社交场合中，您通常会：", dimension: "EI", options: [{ value: "E", text: "认识新朋友，扩大社交圈" }, { value: "I", text: "与已认识的朋友交流" }] },
  { id: 2, question: "您更喜欢：", dimension: "EI", options: [{ value: "E", text: "在团队中工作" }, { value: "I", text: "独立工作" }] },
  { id: 3, question: "当您需要充电时，您会选择：", dimension: "EI", options: [{ value: "E", text: "与朋友聚会" }, { value: "I", text: "独处休息" }] },
  { id: 4, question: "在会议中，您通常：", dimension: "EI", options: [{ value: "E", text: "积极发言" }, { value: "I", text: "先思考再发表" }] },
  { id: 5, question: "您更倾向于：", dimension: "EI", options: [{ value: "E", text: "认识很多人" }, { value: "I", text: "与少数人深交" }] },
  { id: 6, question: "面对问题时，您更倾向于：", dimension: "EI", options: [{ value: "E", text: "与他人讨论" }, { value: "I", text: "独自思考" }] },
  { id: 7, question: "您更喜欢的休闲活动是：", dimension: "EI", options: [{ value: "E", text: "团体活动" }, { value: "I", text: "个人爱好" }] },
  { id: 8, question: "在陌生环境中，您通常会：", dimension: "EI", options: [{ value: "E", text: "主动与人交谈" }, { value: "I", text: "观察周围环境" }] },
  
  // S vs N 维度 (7题)
  { id: 9, question: "您更关注：", dimension: "SN", options: [{ value: "S", text: "具体的细节和事实" }, { value: "N", text: "整体概念和可能性" }] },
  { id: 10, question: "您更信任：", dimension: "SN", options: [{ value: "S", text: "实际经验" }, { value: "N", text: "直觉和灵感" }] },
  { id: 11, question: "您更喜欢：", dimension: "SN", options: [{ value: "S", text: "按步骤执行" }, { value: "N", text: "创造性解决" }] },
  { id: 12, question: "学习新事物时，您更喜欢：", dimension: "SN", options: [{ value: "S", text: "实际操作" }, { value: "N", text: "理论学习" }] },
  { id: 13, question: "您更喜欢的工作类型是：", dimension: "SN", options: [{ value: "S", text: "明确的任务" }, { value: "N", text: "创新的项目" }] },
  { id: 14, question: "您描述事物时更倾向于：", dimension: "SN", options: [{ value: "S", text: "具体描述" }, { value: "N", text: "使用比喻" }] },
  { id: 15, question: "您更关心：", dimension: "SN", options: [{ value: "S", text: "当下的现实" }, { value: "N", text: "未来的可能" }] },
  
  // T vs F 维度 (8题)
  { id: 16, question: "做决定时，您更依赖：", dimension: "TF", options: [{ value: "T", text: "逻辑分析" }, { value: "F", text: "个人价值" }] },
  { id: 17, question: "在争论中，您更看重：", dimension: "TF", options: [{ value: "T", text: "事实真相" }, { value: "F", text: "和谐关系" }] },
  { id: 18, question: "给予反馈时，您更注重：", dimension: "TF", options: [{ value: "T", text: "直接指出问题" }, { value: "F", text: "考虑对方感受" }] },
  { id: 19, question: "评估方案时，您更关注：", dimension: "TF", options: [{ value: "T", text: "效率和结果" }, { value: "F", text: "对人的影响" }] },
  { id: 20, question: "当朋友遇到困难时，您更倾向于：", dimension: "TF", options: [{ value: "T", text: "分析问题提供建议" }, { value: "F", text: "倾听并给予支持" }] },
  { id: 21, question: "您更欣赏的品质是：", dimension: "TF", options: [{ value: "T", text: "理性客观" }, { value: "F", text: "善解人意" }] },
  { id: 22, question: "您认为好的决定应该：", dimension: "TF", options: [{ value: "T", text: "基于客观分析" }, { value: "F", text: "考虑各方感受" }] },
  { id: 23, question: "当与他人意见不同时，您更倾向于：", dimension: "TF", options: [{ value: "T", text: "坚持正确观点" }, { value: "F", text: "寻求共识" }] },
  
  // J vs P 维度 (7题)
  { id: 24, question: "您更喜欢的工作方式是：", dimension: "JP", options: [{ value: "J", text: "有计划地进行" }, { value: "P", text: "随机应变" }] },
  { id: 25, question: "对于截止日期，您通常：", dimension: "JP", options: [{ value: "J", text: "提前完成" }, { value: "P", text: "在最后完成" }] },
  { id: 26, question: "您的生活方式更倾向于：", dimension: "JP", options: [{ value: "J", text: "有条理有规律" }, { value: "P", text: "灵活随意" }] },
  { id: 27, question: "面对选择时，您更倾向于：", dimension: "JP", options: [{ value: "J", text: "快速做出决定" }, { value: "P", text: "保持开放选项" }] },
  { id: 28, question: "您的桌面通常是：", dimension: "JP", options: [{ value: "J", text: "整洁有序" }, { value: "P", text: "创意性混乱" }] },
  { id: 29, question: "计划改变时，您的反应是：", dimension: "JP", options: [{ value: "J", text: "感到不安" }, { value: "P", text: "觉得有趣" }] },
  { id: 30, question: "您更喜欢：", dimension: "JP", options: [{ value: "J", text: "事先规划" }, { value: "P", text: "即兴发挥" }] }
]

// DISC测试题 (20题)
const discQuestions = [
  { id: 1, question: "在团队中，您更倾向于：", options: [{ value: "D", text: "主导决策，带领团队" }, { value: "I", text: "活跃气氛，激励成员" }, { value: "S", text: "支持他人，确保和谐" }, { value: "C", text: "分析数据，确保质量" }] },
  { id: 2, question: "面对挑战时，您的第一反应是：", options: [{ value: "D", text: "立即行动" }, { value: "I", text: "寻找支持" }, { value: "S", text: "冷静思考" }, { value: "C", text: "收集信息" }] },
  { id: 3, question: "您在工作中最看重的是：", options: [{ value: "D", text: "成果和效率" }, { value: "I", text: "认可和赞赏" }, { value: "S", text: "稳定和安全" }, { value: "C", text: "准确和质量" }] },
  { id: 4, question: "与他人沟通时，您通常：", options: [{ value: "D", text: "直接了当" }, { value: "I", text: "热情友好" }, { value: "S", text: "耐心倾听" }, { value: "C", text: "逻辑清晰" }] },
  { id: 5, question: "压力之下，您会：", options: [{ value: "D", text: "更加强势" }, { value: "I", text: "寻求鼓励" }, { value: "S", text: "保持冷静" }, { value: "C", text: "更加谨慎" }] },
  { id: 6, question: "您认为自己的优势是：", options: [{ value: "D", text: "决断力强" }, { value: "I", text: "人际关系好" }, { value: "S", text: "可靠稳定" }, { value: "C", text: "分析能力强" }] },
  { id: 7, question: "在会议中，您通常扮演：", options: [{ value: "D", text: "主导者" }, { value: "I", text: "激励者" }, { value: "S", text: "调和者" }, { value: "C", text: "分析者" }] },
  { id: 8, question: "您最不喜欢的工作环境是：", options: [{ value: "D", text: "进展缓慢" }, { value: "I", text: "被孤立" }, { value: "S", text: "变化太快" }, { value: "C", text: "混乱无序" }] },
  { id: 9, question: "做决定时，您更依赖：", options: [{ value: "D", text: "直觉经验" }, { value: "I", text: "他人意见" }, { value: "S", text: "过去经验" }, { value: "C", text: "数据事实" }] },
  { id: 10, question: "您的工作风格是：", options: [{ value: "D", text: "快速高效" }, { value: "I", text: "灵活多变" }, { value: "S", text: "稳定持续" }, { value: "C", text: "严谨细致" }] },
  { id: 11, question: "遇到冲突时，您会：", options: [{ value: "D", text: "直面解决" }, { value: "I", text: "调解双方" }, { value: "S", text: "避免冲突" }, { value: "C", text: "分析原因" }] },
  { id: 12, question: "您期望的领导风格是：", options: [{ value: "D", text: "给予挑战" }, { value: "I", text: "认可表扬" }, { value: "S", text: "稳定支持" }, { value: "C", text: "明确指导" }] },
  { id: 13, question: "处理任务时，您更注重：", options: [{ value: "D", text: "速度效率" }, { value: "I", text: "创意新颖" }, { value: "S", text: "过程协作" }, { value: "C", text: "质量准确" }] },
  { id: 14, question: "您的社交方式是：", options: [{ value: "D", text: "目的明确" }, { value: "I", text: "广泛社交" }, { value: "S", text: "深度交往" }, { value: "C", text: "选择性社交" }] },
  { id: 15, question: "您理想的工作节奏是：", options: [{ value: "D", text: "快节奏" }, { value: "I", text: "灵活多变" }, { value: "S", text: "稳定有序" }, { value: "C", text: "有条理" }] },
  { id: 16, question: "面对变化，您的态度是：", options: [{ value: "D", text: "主动拥抱" }, { value: "I", text: "积极适应" }, { value: "S", text: "需要时间" }, { value: "C", text: "谨慎评估" }] },
  { id: 17, question: "您的时间管理风格是：", options: [{ value: "D", text: "高效利用" }, { value: "I", text: "灵活安排" }, { value: "S", text: "按部就班" }, { value: "C", text: "精确规划" }] },
  { id: 18, question: "激励您的是：", options: [{ value: "D", text: "成就控制" }, { value: "I", text: "认可社交" }, { value: "S", text: "稳定归属" }, { value: "C", text: "正确标准" }] },
  { id: 19, question: "您处理细节的方式是：", options: [{ value: "D", text: "关注大局" }, { value: "I", text: "可能忽略" }, { value: "S", text: "认真对待" }, { value: "C", text: "极度重视" }] },
  { id: 20, question: "您对规则的态度是：", options: [{ value: "D", text: "灵活打破" }, { value: "I", text: "灵活运用" }, { value: "S", text: "遵守维护" }, { value: "C", text: "严格遵守" }] }
]

// PDP测试题 (20题)
const pdpQuestions = [
  { id: 1, question: "面对紧急任务，您的第一反应是：", options: [{ value: "Tiger", text: "立即行动" }, { value: "Peacock", text: "召集团队" }, { value: "Koala", text: "冷静分析" }, { value: "Owl", text: "仔细规划" }, { value: "Chameleon", text: "灵活应对" }] },
  { id: 2, question: "在社交场合，您通常会：", options: [{ value: "Tiger", text: "主导话题" }, { value: "Peacock", text: "活跃气氛" }, { value: "Koala", text: "安静倾听" }, { value: "Owl", text: "观察分析" }, { value: "Chameleon", text: "根据对象调整" }] },
  { id: 3, question: "您最看重工作中的：", options: [{ value: "Tiger", text: "权力和成就" }, { value: "Peacock", text: "认可和赞赏" }, { value: "Koala", text: "稳定和和谐" }, { value: "Owl", text: "准确和质量" }, { value: "Chameleon", text: "平衡和适应" }] },
  { id: 4, question: "处理冲突时，您倾向于：", options: [{ value: "Tiger", text: "直接解决" }, { value: "Peacock", text: "调解双方" }, { value: "Koala", text: "避免冲突" }, { value: "Owl", text: "理性处理" }, { value: "Chameleon", text: "视情况而定" }] },
  { id: 5, question: "您的决策风格是：", options: [{ value: "Tiger", text: "果断迅速" }, { value: "Peacock", text: "直觉判断" }, { value: "Koala", text: "深思熟虑" }, { value: "Owl", text: "数据分析" }, { value: "Chameleon", text: "灵活决策" }] },
  { id: 6, question: "面对压力，您会：", options: [{ value: "Tiger", text: "更加强势" }, { value: "Peacock", text: "寻求支持" }, { value: "Koala", text: "保持冷静" }, { value: "Owl", text: "更加谨慎" }, { value: "Chameleon", text: "调整策略" }] },
  { id: 7, question: "您的领导风格是：", options: [{ value: "Tiger", text: "指挥型" }, { value: "Peacock", text: "激励型" }, { value: "Koala", text: "支持型" }, { value: "Owl", text: "专家型" }, { value: "Chameleon", text: "教练型" }] },
  { id: 8, question: "您处理细节的方式是：", options: [{ value: "Tiger", text: "关注大局" }, { value: "Peacock", text: "可能忽略" }, { value: "Koala", text: "认真对待" }, { value: "Owl", text: "极度重视" }, { value: "Chameleon", text: "视情况决定" }] },
  { id: 9, question: "您的沟通方式是：", options: [{ value: "Tiger", text: "直接简短" }, { value: "Peacock", text: "热情生动" }, { value: "Koala", text: "温和耐心" }, { value: "Owl", text: "逻辑清晰" }, { value: "Chameleon", text: "根据对象调整" }] },
  { id: 10, question: "您对变化的态度是：", options: [{ value: "Tiger", text: "主动推动" }, { value: "Peacock", text: "积极拥抱" }, { value: "Koala", text: "需要适应" }, { value: "Owl", text: "谨慎评估" }, { value: "Chameleon", text: "随机应变" }] },
  { id: 11, question: "您的时间管理风格是：", options: [{ value: "Tiger", text: "追求速度" }, { value: "Peacock", text: "灵活安排" }, { value: "Koala", text: "稳定执行" }, { value: "Owl", text: "精确规划" }, { value: "Chameleon", text: "根据情况调整" }] },
  { id: 12, question: "您被什么激励：", options: [{ value: "Tiger", text: "成就权力" }, { value: "Peacock", text: "认可赞赏" }, { value: "Koala", text: "安全归属" }, { value: "Owl", text: "正确标准" }, { value: "Chameleon", text: "多样平衡" }] },
  { id: 13, question: "您的学习方式是：", options: [{ value: "Tiger", text: "边做边学" }, { value: "Peacock", text: "互动讨论" }, { value: "Koala", text: "循序渐进" }, { value: "Owl", text: "深入研究" }, { value: "Chameleon", text: "多种结合" }] },
  { id: 14, question: "您对规则的态度是：", options: [{ value: "Tiger", text: "灵活打破" }, { value: "Peacock", text: "不拘一格" }, { value: "Koala", text: "遵守维护" }, { value: "Owl", text: "严格遵守" }, { value: "Chameleon", text: "灵活处理" }] },
  { id: 15, question: "您在团队中的角色是：", options: [{ value: "Tiger", text: "领导者" }, { value: "Peacock", text: "激励者" }, { value: "Koala", text: "协调者" }, { value: "Owl", text: "专家" }, { value: "Chameleon", text: "多面手" }] },
  { id: 16, question: "您的工作节奏是：", options: [{ value: "Tiger", text: "快节奏" }, { value: "Peacock", text: "充满活力" }, { value: "Koala", text: "稳定有序" }, { value: "Owl", text: "有条理" }, { value: "Chameleon", text: "灵活调整" }] },
  { id: 17, question: "您最大的优势是：", options: [{ value: "Tiger", text: "执行力" }, { value: "Peacock", text: "影响力" }, { value: "Koala", text: "可靠性" }, { value: "Owl", text: "准确性" }, { value: "Chameleon", text: "适应力" }] },
  { id: 18, question: "您的人际关系特点是：", options: [{ value: "Tiger", text: "目标导向" }, { value: "Peacock", text: "朋友众多" }, { value: "Koala", text: "关系稳定" }, { value: "Owl", text: "志同道合" }, { value: "Chameleon", text: "灵活建立" }] },
  { id: 19, question: "面对批评，您的反应是：", options: [{ value: "Tiger", text: "可能反驳" }, { value: "Peacock", text: "可能受伤" }, { value: "Koala", text: "接受思考" }, { value: "Owl", text: "分析合理性" }, { value: "Chameleon", text: "灵活调整" }] },
  { id: 20, question: "您的理想工作环境是：", options: [{ value: "Tiger", text: "充满挑战" }, { value: "Peacock", text: "互动频繁" }, { value: "Koala", text: "稳定和谐" }, { value: "Owl", text: "有序规范" }, { value: "Chameleon", text: "灵活多变" }] }
]

/**
 * Fisher-Yates 洗牌：随机打乱题目顺序，同时随机打乱每题的选项顺序
 * 不修改原数组，返回深拷贝后的新数组
 * @param {Array} questions - 原题目数组
 * @returns {Array} 打乱后的题目数组
 */
function shuffleQuestions(questions) {
  // 深拷贝，避免污染原始数组
  const arr = questions.map(q => ({
    ...q,
    options: q.options.slice().sort(() => Math.random() - 0.5)
  }))
  // Fisher-Yates 打乱题目顺序
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]]
  }
  return arr
}

module.exports = {
  mbtiQuestions,
  discQuestions,
  pdpQuestions,
  shuffleQuestions
}

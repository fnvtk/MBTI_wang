<?php
/**
 * 题库数据导出脚本
 * 将 mbti2 项目中的题库数据转换为 SQL INSERT 语句
 * 
 * 使用方法：
 * 1. 将 mbti2/data/*.ts 文件复制到当前目录
 * 2. 运行: php export_questions_data.php
 */

// MBTI 题库数据（从 mbti-questions.ts 提取）
$mbtiQuestions = [
    // E vs I 维度问题 (23题)
    ['id' => 1, 'question' => '在社交场合中，您通常会：', 'options' => [['value' => 'E', 'text' => '认识新朋友，扩大社交圈'], ['value' => 'I', 'text' => '与已认识的朋友交流，保持小圈子']], 'dimension' => 'EI'],
    ['id' => 2, 'question' => '您更喜欢：', 'options' => [['value' => 'E', 'text' => '在团队中工作，与他人合作'], ['value' => 'I', 'text' => '独立工作，自己思考问题']], 'dimension' => 'EI'],
    ['id' => 3, 'question' => '当您需要充电时，您会选择：', 'options' => [['value' => 'E', 'text' => '与朋友聚会，参加社交活动'], ['value' => 'I', 'text' => '独处，阅读或进行个人爱好']], 'dimension' => 'EI'],
    ['id' => 4, 'question' => '在会议或讨论中，您通常：', 'options' => [['value' => 'E', 'text' => '积极发言，边说边思考'], ['value' => 'I', 'text' => '先思考，然后再发表意见']], 'dimension' => 'EI'],
    ['id' => 5, 'question' => '您更倾向于：', 'options' => [['value' => 'E', 'text' => '认识很多人，建立广泛的社交网络'], ['value' => 'I', 'text' => '与少数几个人建立深厚的友谊']], 'dimension' => 'EI'],
    ['id' => 6, 'question' => '在工作环境中，您更喜欢：', 'options' => [['value' => 'E', 'text' => '开放的办公空间，可以随时与同事交流'], ['value' => 'I', 'text' => '安静的私人空间，可以专注工作']], 'dimension' => 'EI'],
    ['id' => 7, 'question' => '当面对问题时，您更倾向于：', 'options' => [['value' => 'E', 'text' => '与他人讨论，获取不同观点'], ['value' => 'I', 'text' => '独自思考，寻找解决方案']], 'dimension' => 'EI'],
    ['id' => 8, 'question' => '您更喜欢的休闲活动是：', 'options' => [['value' => 'E', 'text' => '参加派对、团体活动或体育比赛'], ['value' => 'I', 'text' => '阅读、看电影或独自进行创作']], 'dimension' => 'EI'],
    ['id' => 9, 'question' => '在陌生环境中，您通常会：', 'options' => [['value' => 'E', 'text' => '主动与他人交谈，快速融入环境'], ['value' => 'I', 'text' => '观察周围环境，等待他人接近']], 'dimension' => 'EI'],
    ['id' => 10, 'question' => '您认为自己是：', 'options' => [['value' => 'E', 'text' => '外向、健谈的人'], ['value' => 'I', 'text' => '内敛、安静的人']], 'dimension' => 'EI'],
    ['id' => 11, 'question' => '长时间的社交活动后，您通常会：', 'options' => [['value' => 'E', 'text' => '感到精力充沛，想继续社交'], ['value' => 'I', 'text' => '感到疲惫，需要独处恢复精力']], 'dimension' => 'EI'],
    ['id' => 12, 'question' => '您更喜欢的工作方式是：', 'options' => [['value' => 'E', 'text' => '与团队一起头脑风暴和协作'], ['value' => 'I', 'text' => '独立思考和解决问题']], 'dimension' => 'EI'],
    ['id' => 13, 'question' => '您更容易：', 'options' => [['value' => 'E', 'text' => '在说话的过程中组织思路'], ['value' => 'I', 'text' => '在说话前先组织好思路']], 'dimension' => 'EI'],
    ['id' => 14, 'question' => '您更喜欢的学习方式是：', 'options' => [['value' => 'E', 'text' => '小组讨论和互动学习'], ['value' => 'I', 'text' => '自学和独立研究']], 'dimension' => 'EI'],
    ['id' => 15, 'question' => '当您有好消息时，您会：', 'options' => [['value' => 'E', 'text' => '立即告诉朋友和家人分享喜悦'], ['value' => 'I', 'text' => '只告诉少数几个亲近的人']], 'dimension' => 'EI'],
    ['id' => 16, 'question' => '您更喜欢的假期类型是：', 'options' => [['value' => 'E', 'text' => '热闹的旅游胜地，有很多活动和人'], ['value' => 'I', 'text' => '安静的度假地，可以放松和思考']], 'dimension' => 'EI'],
    ['id' => 17, 'question' => '在团队项目中，您更倾向于：', 'options' => [['value' => 'E', 'text' => '担任协调者或发言人的角色'], ['value' => 'I', 'text' => '负责研究或分析的工作']], 'dimension' => 'EI'],
    ['id' => 18, 'question' => '您更喜欢的通讯方式是：', 'options' => [['value' => 'E', 'text' => '面对面交谈或视频通话'], ['value' => 'I', 'text' => '电子邮件或文字消息']], 'dimension' => 'EI'],
    ['id' => 19, 'question' => '当您遇到困难时，您更倾向于：', 'options' => [['value' => 'E', 'text' => '寻求他人的建议和支持'], ['value' => 'I', 'text' => '自己思考解决方案']], 'dimension' => 'EI'],
    ['id' => 20, 'question' => '您更喜欢的工作环境是：', 'options' => [['value' => 'E', 'text' => '充满活力和互动的环境'], ['value' => 'I', 'text' => '安静和专注的环境']], 'dimension' => 'EI'],
    ['id' => 21, 'question' => '您更倾向于：', 'options' => [['value' => 'E', 'text' => '在多个社交圈中活跃'], ['value' => 'I', 'text' => '在一个小圈子中深入交往']], 'dimension' => 'EI'],
    ['id' => 22, 'question' => '您更喜欢：', 'options' => [['value' => 'E', 'text' => '参加大型社交活动'], ['value' => 'I', 'text' => '与一两个朋友进行深入交流']], 'dimension' => 'EI'],
    ['id' => 23, 'question' => '您更倾向于：', 'options' => [['value' => 'E', 'text' => '在公共场合表达自己的想法'], ['value' => 'I', 'text' => '在私下或小范围内分享想法']], 'dimension' => 'EI'],
    // S vs N 维度问题 (22题)
    ['id' => 24, 'question' => '当面对新信息时，您更倾向于：', 'options' => [['value' => 'S', 'text' => '关注具体细节和事实'], ['value' => 'N', 'text' => '寻找模式和可能性']], 'dimension' => 'SN'],
    ['id' => 25, 'question' => '您更相信：', 'options' => [['value' => 'S', 'text' => '实际经验和直接观察'], ['value' => 'N', 'text' => '直觉和想象力']], 'dimension' => 'SN'],
    ['id' => 26, 'question' => '您更喜欢处理：', 'options' => [['value' => 'S', 'text' => '已知的、确定的信息'], ['value' => 'N', 'text' => '理论性的、抽象的概念']], 'dimension' => 'SN'],
    ['id' => 27, 'question' => '在解决问题时，您更倾向于：', 'options' => [['value' => 'S', 'text' => '使用已证实有效的方法'], ['value' => 'N', 'text' => '尝试创新的解决方案']], 'dimension' => 'SN'],
    ['id' => 28, 'question' => '您更关注：', 'options' => [['value' => 'S', 'text' => '当下的现实和实际情况'], ['value' => 'N', 'text' => '未来的可能性和潜在发展']], 'dimension' => 'SN'],
    ['id' => 29, 'question' => '您更喜欢的工作类型是：', 'options' => [['value' => 'S', 'text' => '有明确指导和具体步骤的工作'], ['value' => 'N', 'text' => '允许创新和探索的工作']], 'dimension' => 'SN'],
    ['id' => 30, 'question' => '您更倾向于：', 'options' => [['value' => 'S', 'text' => '关注实际和现实的细节'], ['value' => 'N', 'text' => '思考概念和理论']], 'dimension' => 'SN'],
    ['id' => 31, 'question' => '您更喜欢的书籍或电影是：', 'options' => [['value' => 'S', 'text' => '基于现实的故事或纪实作品'], ['value' => 'N', 'text' => '科幻、奇幻或充满想象力的作品']], 'dimension' => 'SN'],
    ['id' => 32, 'question' => '在学习新技能时，您更倾向于：', 'options' => [['value' => 'S', 'text' => '按部就班地学习，掌握每个具体步骤'], ['value' => 'N', 'text' => '先了解整体概念，然后填补细节']], 'dimension' => 'SN'],
    ['id' => 33, 'question' => '您更喜欢的工作环境是：', 'options' => [['value' => 'S', 'text' => '有明确结构和规则的环境'], ['value' => 'N', 'text' => '灵活多变，鼓励创新的环境']], 'dimension' => 'SN'],
    ['id' => 34, 'question' => '您更倾向于：', 'options' => [['value' => 'S', 'text' => '关注现实和实际问题'], ['value' => 'N', 'text' => '思考未来和可能性']], 'dimension' => 'SN'],
    ['id' => 35, 'question' => '在描述事物时，您更倾向于：', 'options' => [['value' => 'S', 'text' => '提供具体细节和精确描述'], ['value' => 'N', 'text' => '使用比喻和隐喻，强调整体印象']], 'dimension' => 'SN'],
    ['id' => 36, 'question' => '您更喜欢的工作任务是：', 'options' => [['value' => 'S', 'text' => '需要精确和注重细节的任务'], ['value' => 'N', 'text' => '需要创造力和想象力的任务']], 'dimension' => 'SN'],
    ['id' => 37, 'question' => '您更相信：', 'options' => [['value' => 'S', 'text' => '亲眼所见和实际经验'], ['value' => 'N', 'text' => '直觉和内在感受']], 'dimension' => 'SN'],
    ['id' => 38, 'question' => '在讨论问题时，您更关注：', 'options' => [['value' => 'S', 'text' => '具体事实和实际例子'], ['value' => 'N', 'text' => '概念和理论框架']], 'dimension' => 'SN'],
    ['id' => 39, 'question' => '您更喜欢的学习方式是：', 'options' => [['value' => 'S', 'text' => '通过实践和具体例子学习'], ['value' => 'N', 'text' => '通过理论和概念学习']], 'dimension' => 'SN'],
    ['id' => 40, 'question' => '您更倾向于：', 'options' => [['value' => 'S', 'text' => '关注现在和过去的经验'], ['value' => 'N', 'text' => '思考未来和可能发生的事情']], 'dimension' => 'SN'],
    ['id' => 41, 'question' => '在解决问题时，您更依赖：', 'options' => [['value' => 'S', 'text' => '已经证明有效的方法和经验'], ['value' => 'N', 'text' => '创新的思路和直觉']], 'dimension' => 'SN'],
    ['id' => 42, 'question' => '您更喜欢的工作是：', 'options' => [['value' => 'S', 'text' => '有明确目标和具体步骤的工作'], ['value' => 'N', 'text' => '允许探索和创新的工作']], 'dimension' => 'SN'],
    ['id' => 43, 'question' => '您更倾向于：', 'options' => [['value' => 'S', 'text' => '关注实际和现实的问题'], ['value' => 'N', 'text' => '思考抽象和理论性的问题']], 'dimension' => 'SN'],
    ['id' => 44, 'question' => '您更喜欢：', 'options' => [['value' => 'S', 'text' => '按照既定的方法和程序工作'], ['value' => 'N', 'text' => '尝试新的方法和创新的解决方案']], 'dimension' => 'SN'],
    ['id' => 45, 'question' => '您更关注：', 'options' => [['value' => 'S', 'text' => '实际的细节和具体的事实'], ['value' => 'N', 'text' => '整体的概念和潜在的可能性']], 'dimension' => 'SN'],
    // T vs F 维度问题 (22题)
    ['id' => 46, 'question' => '在做决定时，您通常会：', 'options' => [['value' => 'T', 'text' => '考虑逻辑和客观分析'], ['value' => 'F', 'text' => '考虑个人价值观和他人感受']], 'dimension' => 'TF'],
    ['id' => 47, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '公正客观地分析情况'], ['value' => 'F', 'text' => '考虑决定对人的影响']], 'dimension' => 'TF'],
    ['id' => 48, 'question' => '在解决冲突时，您更关注：', 'options' => [['value' => 'T', 'text' => '找出问题的根本原因和逻辑解决方案'], ['value' => 'F', 'text' => '维护关系和照顾各方感受']], 'dimension' => 'TF'],
    ['id' => 49, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '直接指出问题，即使可能伤害他人感受'], ['value' => 'F', 'text' => '委婉表达，避免伤害他人感受']], 'dimension' => 'TF'],
    ['id' => 50, 'question' => '在评估情况时，您更重视：', 'options' => [['value' => 'T', 'text' => '客观事实和逻辑分析'], ['value' => 'F', 'text' => '人际关系和价值观']], 'dimension' => 'TF'],
    ['id' => 51, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '保持客观，不受个人情感影响'], ['value' => 'F', 'text' => '考虑决定对他人的情感影响']], 'dimension' => 'TF'],
    ['id' => 52, 'question' => '在团队中，您更关注：', 'options' => [['value' => 'T', 'text' => '任务的完成和效率'], ['value' => 'F', 'text' => '团队和谐和成员满意度']], 'dimension' => 'TF'],
    ['id' => 53, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '基于逻辑和事实做决定'], ['value' => 'F', 'text' => '基于价值观和个人信念做决定']], 'dimension' => 'TF'],
    ['id' => 54, 'question' => '在给予反馈时，您更倾向于：', 'options' => [['value' => 'T', 'text' => '直接指出问题和改进方向'], ['value' => 'F', 'text' => '先肯定优点，再委婉提出建议']], 'dimension' => 'TF'],
    ['id' => 55, 'question' => '您更看重：', 'options' => [['value' => 'T', 'text' => '公平和一致性'], ['value' => 'F', 'text' => '同情和个人情况']], 'dimension' => 'TF'],
    ['id' => 56, 'question' => '在工作中，您更重视：', 'options' => [['value' => 'T', 'text' => '效率和成果'], ['value' => 'F', 'text' => '团队合作和人际关系']], 'dimension' => 'TF'],
    ['id' => 57, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '客观分析问题，不受个人情感影响'], ['value' => 'F', 'text' => '考虑决定对人的影响和感受']], 'dimension' => 'TF'],
    ['id' => 58, 'question' => '在处理冲突时，您更倾向于：', 'options' => [['value' => 'T', 'text' => '关注问题本身，寻求公正的解决方案'], ['value' => 'F', 'text' => '关注人际关系，寻求和谐的解决方案']], 'dimension' => 'TF'],
    ['id' => 59, 'question' => '您更看重：', 'options' => [['value' => 'T', 'text' => '真实和诚实，即使可能伤害感情'], ['value' => 'F', 'text' => '善良和体贴，避免伤害他人']], 'dimension' => 'TF'],
    ['id' => 60, 'question' => '在评价他人时，您更倾向于：', 'options' => [['value' => 'T', 'text' => '客观评价其表现和能力'], ['value' => 'F', 'text' => '考虑其努力和意图']], 'dimension' => 'TF'],
    ['id' => 61, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '基于原则和规则做决定'], ['value' => 'F', 'text' => '基于具体情况和个人需求做决定']], 'dimension' => 'TF'],
    ['id' => 62, 'question' => '在处理问题时，您更关注：', 'options' => [['value' => 'T', 'text' => '找出最有效的解决方案'], ['value' => 'F', 'text' => '确保所有人都感到被尊重和理解']], 'dimension' => 'TF'],
    ['id' => 63, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '保持客观和理性'], ['value' => 'F', 'text' => '表达同理心和关怀']], 'dimension' => 'TF'],
    ['id' => 64, 'question' => '在做决定时，您更看重：', 'options' => [['value' => 'T', 'text' => '逻辑和一致性'], ['value' => 'F', 'text' => '价值观和人际和谐']], 'dimension' => 'TF'],
    ['id' => 65, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '分析问题，寻找最佳解决方案'], ['value' => 'F', 'text' => '理解他人感受，寻求共识']], 'dimension' => 'TF'],
    ['id' => 66, 'question' => '在工作中，您更重视：', 'options' => [['value' => 'T', 'text' => '完成任务和达成目标'], ['value' => 'F', 'text' => '维护良好的工作关系']], 'dimension' => 'TF'],
    ['id' => 67, 'question' => '您更倾向于：', 'options' => [['value' => 'T', 'text' => '基于事实和数据做决定'], ['value' => 'F', 'text' => '考虑决定对人的影响']], 'dimension' => 'TF'],
    // J vs P 维度问题 (23题)
    ['id' => 68, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '提前计划并按计划执行'], ['value' => 'P', 'text' => '保持灵活，根据情况调整']], 'dimension' => 'JP'],
    ['id' => 69, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '按时完成任务，避免拖延'], ['value' => 'P', 'text' => '在最后期限前完成任务，保持灵活性']], 'dimension' => 'JP'],
    ['id' => 70, 'question' => '您更喜欢的工作环境是：', 'options' => [['value' => 'J', 'text' => '有明确的规则和结构'], ['value' => 'P', 'text' => '灵活多变，允许即兴发挥']], 'dimension' => 'JP'],
    ['id' => 71, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '制定详细的计划并遵循'], ['value' => 'P', 'text' => '根据情况随机应变']], 'dimension' => 'JP'],
    ['id' => 72, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '有条理和组织的生活方式'], ['value' => 'P', 'text' => '自然流动和灵活的生活方式']], 'dimension' => 'JP'],
    ['id' => 73, 'question' => '在做决定时，您更倾向于：', 'options' => [['value' => 'J', 'text' => '尽快做出决定并执行'], ['value' => 'P', 'text' => '保持选项开放，等待更多信息']], 'dimension' => 'JP'],
    ['id' => 74, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '按照既定的时间表和计划行事'], ['value' => 'P', 'text' => '根据当下的情况和感受行事']], 'dimension' => 'JP'],
    ['id' => 75, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '完成一项任务后再开始下一项'], ['value' => 'P', 'text' => '同时处理多项任务，根据情况切换']], 'dimension' => 'JP'],
    ['id' => 76, 'question' => '您更喜欢的工作方式是：', 'options' => [['value' => 'J', 'text' => '有明确的目标和截止日期'], ['value' => 'P', 'text' => '灵活的工作流程，可以随时调整']], 'dimension' => 'JP'],
    ['id' => 77, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '提前做决定，避免最后一刻的压力'], ['value' => 'P', 'text' => '保持选项开放，等待最佳时机']], 'dimension' => 'JP'],
    ['id' => 78, 'question' => '您的工作区域通常是：', 'options' => [['value' => 'J', 'text' => '整洁有序，物品摆放有规律'], ['value' => 'P', 'text' => '创意性混乱，物品随手可及']], 'dimension' => 'JP'],
    ['id' => 79, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '按部就班地完成任务'], ['value' => 'P', 'text' => '在灵感来临时集中精力工作']], 'dimension' => 'JP'],
    ['id' => 80, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '有明确的规则和指导方针'], ['value' => 'P', 'text' => '灵活的环境，可以自由发挥']], 'dimension' => 'JP'],
    ['id' => 81, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '提前计划假期和活动'], ['value' => 'P', 'text' => '即兴决定和安排活动']], 'dimension' => 'JP'],
    ['id' => 82, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '有明确的日程安排'], ['value' => 'P', 'text' => '根据当天的情况决定']], 'dimension' => 'JP'],
    ['id' => 83, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '按照计划行事，避免意外'], ['value' => 'P', 'text' => '适应变化，享受意外惊喜']], 'dimension' => 'JP'],
    ['id' => 84, 'question' => '您更喜欢的生活方式是：', 'options' => [['value' => 'J', 'text' => '有规律和可预测的'], ['value' => 'P', 'text' => '自然流动和多变的']], 'dimension' => 'JP'],
    ['id' => 85, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '提前做好准备，避免匆忙'], ['value' => 'P', 'text' => '在最后一刻完成准备，保持灵活性']], 'dimension' => 'JP'],
    ['id' => 86, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '明确的指导和方向'], ['value' => 'P', 'text' => '探索不同的可能性']], 'dimension' => 'JP'],
    ['id' => 87, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '按照既定的程序和方法工作'], ['value' => 'P', 'text' => '根据情况调整工作方法']], 'dimension' => 'JP'],
    ['id' => 88, 'question' => '您更喜欢：', 'options' => [['value' => 'J', 'text' => '有明确的目标和计划'], ['value' => 'P', 'text' => '保持开放的态度，随机应变']], 'dimension' => 'JP'],
    ['id' => 89, 'question' => '您更倾向于：', 'options' => [['value' => 'J', 'text' => '做决定并坚持执行'], ['value' => 'P', 'text' => '保持灵活，根据新信息调整']], 'dimension' => 'JP'],
    ['id' => 90, 'question' => '您更喜欢的工作环境是：', 'options' => [['value' => 'J', 'text' => '有明确的期望和截止日期'], ['value' => 'P', 'text' => '灵活的工作流程和时间安排']], 'dimension' => 'JP'],
];

// PDP 题库数据（从 pdp-questions.ts 提取）
$pdpQuestions = [
    ['id' => 1, 'question' => '面对紧急任务，您的第一反应是：', 'options' => [['value' => 'Tiger', 'text' => '立即行动，快速完成'], ['value' => 'Peacock', 'text' => '召集团队，激励大家'], ['value' => 'Koala', 'text' => '冷静分析，稳步推进'], ['value' => 'Owl', 'text' => '仔细规划，确保无误'], ['value' => 'Chameleon', 'text' => '根据情况灵活应对']]],
    ['id' => 2, 'question' => '在社交场合，您通常会：', 'options' => [['value' => 'Tiger', 'text' => '主导话题，引领讨论'], ['value' => 'Peacock', 'text' => '活跃气氛，成为焦点'], ['value' => 'Koala', 'text' => '安静倾听，适时回应'], ['value' => 'Owl', 'text' => '观察分析，选择性交流'], ['value' => 'Chameleon', 'text' => '根据对象调整方式']]],
    ['id' => 3, 'question' => '您最看重工作中的：', 'options' => [['value' => 'Tiger', 'text' => '权力和成就'], ['value' => 'Peacock', 'text' => '认可和赞赏'], ['value' => 'Koala', 'text' => '稳定和和谐'], ['value' => 'Owl', 'text' => '准确和质量'], ['value' => 'Chameleon', 'text' => '平衡和适应']]],
    ['id' => 4, 'question' => '处理冲突时，您倾向于：', 'options' => [['value' => 'Tiger', 'text' => '直接面对，迅速解决'], ['value' => 'Peacock', 'text' => '调解双方，化解矛盾'], ['value' => 'Koala', 'text' => '避免冲突，维护和平'], ['value' => 'Owl', 'text' => '分析原因，理性处理'], ['value' => 'Chameleon', 'text' => '灵活应对，视情况而定']]],
    ['id' => 5, 'question' => '您的决策风格是：', 'options' => [['value' => 'Tiger', 'text' => '果断迅速，行动导向'], ['value' => 'Peacock', 'text' => '直觉判断，情感驱动'], ['value' => 'Koala', 'text' => '深思熟虑，追求共识'], ['value' => 'Owl', 'text' => '数据分析，逻辑推理'], ['value' => 'Chameleon', 'text' => '综合考虑，灵活决策']]],
    ['id' => 6, 'question' => '面对压力，您会：', 'options' => [['value' => 'Tiger', 'text' => '更加强势，控制局面'], ['value' => 'Peacock', 'text' => '寻求支持，表达情绪'], ['value' => 'Koala', 'text' => '保持冷静，稳定情绪'], ['value' => 'Owl', 'text' => '更加谨慎，反复确认'], ['value' => 'Chameleon', 'text' => '调整策略，灵活应对']]],
    ['id' => 7, 'question' => '您的领导风格是：', 'options' => [['value' => 'Tiger', 'text' => '指挥型，明确方向'], ['value' => 'Peacock', 'text' => '激励型，鼓舞士气'], ['value' => 'Koala', 'text' => '支持型，关怀团队'], ['value' => 'Owl', 'text' => '专家型，以身作则'], ['value' => 'Chameleon', 'text' => '教练型，因材施教']]],
    ['id' => 8, 'question' => '您处理细节的方式是：', 'options' => [['value' => 'Tiger', 'text' => '关注大局，委托他人'], ['value' => 'Peacock', 'text' => '可能忽略，更重整体'], ['value' => 'Koala', 'text' => '认真对待，确保准确'], ['value' => 'Owl', 'text' => '极度重视，追求完美'], ['value' => 'Chameleon', 'text' => '视情况决定关注程度']]],
    ['id' => 9, 'question' => '您的沟通方式是：', 'options' => [['value' => 'Tiger', 'text' => '直接简短，注重结果'], ['value' => 'Peacock', 'text' => '热情生动，富有感染力'], ['value' => 'Koala', 'text' => '温和耐心，善于倾听'], ['value' => 'Owl', 'text' => '逻辑清晰，注重事实'], ['value' => 'Chameleon', 'text' => '根据对象调整方式']]],
    ['id' => 10, 'question' => '您对变化的态度是：', 'options' => [['value' => 'Tiger', 'text' => '主动推动，寻找机会'], ['value' => 'Peacock', 'text' => '积极拥抱，乐观面对'], ['value' => 'Koala', 'text' => '需要适应时间'], ['value' => 'Owl', 'text' => '谨慎评估，确保万全'], ['value' => 'Chameleon', 'text' => '灵活适应，随机应变']]],
    ['id' => 11, 'question' => '您的时间管理风格是：', 'options' => [['value' => 'Tiger', 'text' => '高效利用，追求速度'], ['value' => 'Peacock', 'text' => '灵活安排，留有余地'], ['value' => 'Koala', 'text' => '按部就班，稳定执行'], ['value' => 'Owl', 'text' => '精确规划，严格执行'], ['value' => 'Chameleon', 'text' => '根据情况调整计划']]],
    ['id' => 12, 'question' => '您被什么激励：', 'options' => [['value' => 'Tiger', 'text' => '成就和权力'], ['value' => 'Peacock', 'text' => '认可和赞赏'], ['value' => 'Koala', 'text' => '安全和归属'], ['value' => 'Owl', 'text' => '正确和标准'], ['value' => 'Chameleon', 'text' => '多样性和平衡']]],
    ['id' => 13, 'question' => '您的学习方式是：', 'options' => [['value' => 'Tiger', 'text' => '边做边学，实践第一'], ['value' => 'Peacock', 'text' => '互动讨论，分享学习'], ['value' => 'Koala', 'text' => '循序渐进，反复练习'], ['value' => 'Owl', 'text' => '深入研究，系统学习'], ['value' => 'Chameleon', 'text' => '多种方式结合']]],
    ['id' => 14, 'question' => '您对规则的态度是：', 'options' => [['value' => 'Tiger', 'text' => '规则是用来打破的'], ['value' => 'Peacock', 'text' => '灵活运用，不拘一格'], ['value' => 'Koala', 'text' => '遵守规则，维护秩序'], ['value' => 'Owl', 'text' => '严格遵守，确保合规'], ['value' => 'Chameleon', 'text' => '视情况灵活处理']]],
    ['id' => 15, 'question' => '您在团队中的角色是：', 'options' => [['value' => 'Tiger', 'text' => '领导者，推动进展'], ['value' => 'Peacock', 'text' => '激励者，凝聚人心'], ['value' => 'Koala', 'text' => '协调者，维护和谐'], ['value' => 'Owl', 'text' => '专家，提供专业意见'], ['value' => 'Chameleon', 'text' => '多面手，灵活补位']]],
    ['id' => 16, 'question' => '您的工作节奏是：', 'options' => [['value' => 'Tiger', 'text' => '快节奏，追求效率'], ['value' => 'Peacock', 'text' => '充满活力，变化多端'], ['value' => 'Koala', 'text' => '稳定有序，按部就班'], ['value' => 'Owl', 'text' => '有条理，精确执行'], ['value' => 'Chameleon', 'text' => '根据需要调整节奏']]],
    ['id' => 17, 'question' => '您最大的优势是：', 'options' => [['value' => 'Tiger', 'text' => '决断力和执行力'], ['value' => 'Peacock', 'text' => '影响力和感染力'], ['value' => 'Koala', 'text' => '耐心和可靠性'], ['value' => 'Owl', 'text' => '分析力和准确性'], ['value' => 'Chameleon', 'text' => '适应力和灵活性']]],
    ['id' => 18, 'question' => '您的人际关系特点是：', 'options' => [['value' => 'Tiger', 'text' => '目标导向，建立有价值的关系'], ['value' => 'Peacock', 'text' => '广泛社交，朋友众多'], ['value' => 'Koala', 'text' => '深度交往，关系稳定'], ['value' => 'Owl', 'text' => '选择性交往，志同道合'], ['value' => 'Chameleon', 'text' => '根据情况建立不同类型关系']]],
    ['id' => 19, 'question' => '面对批评，您的反应是：', 'options' => [['value' => 'Tiger', 'text' => '可能会反驳或辩护'], ['value' => 'Peacock', 'text' => '可能会感到受伤'], ['value' => 'Koala', 'text' => '接受并思考改进'], ['value' => 'Owl', 'text' => '分析批评的合理性'], ['value' => 'Chameleon', 'text' => '根据情况调整反应']]],
    ['id' => 20, 'question' => '您的理想工作环境是：', 'options' => [['value' => 'Tiger', 'text' => '充满挑战，自主权高'], ['value' => 'Peacock', 'text' => '互动频繁，氛围活跃'], ['value' => 'Koala', 'text' => '稳定和谐，团队协作'], ['value' => 'Owl', 'text' => '有序规范，专注质量'], ['value' => 'Chameleon', 'text' => '灵活多变，兼顾平衡']]],
];

// DISC 题库数据（从 disc-questions.ts 提取）
$discQuestions = [
    ['id' => 1, 'question' => '在团队中，您更倾向于：', 'options' => [['value' => 'D', 'text' => '主导决策，带领团队前进'], ['value' => 'I', 'text' => '活跃气氛，激励团队成员'], ['value' => 'S', 'text' => '支持他人，确保团队和谐'], ['value' => 'C', 'text' => '分析数据，确保决策质量']]],
    ['id' => 2, 'question' => '面对挑战时，您的第一反应是：', 'options' => [['value' => 'D', 'text' => '立即行动，解决问题'], ['value' => 'I', 'text' => '寻找支持，团队协作'], ['value' => 'S', 'text' => '冷静思考，稳步推进'], ['value' => 'C', 'text' => '收集信息，谨慎分析']]],
    ['id' => 3, 'question' => '您在工作中最看重的是：', 'options' => [['value' => 'D', 'text' => '成果和效率'], ['value' => 'I', 'text' => '认可和赞赏'], ['value' => 'S', 'text' => '稳定和安全'], ['value' => 'C', 'text' => '准确和质量']]],
    ['id' => 4, 'question' => '与他人沟通时，您通常：', 'options' => [['value' => 'D', 'text' => '直接了当，注重结果'], ['value' => 'I', 'text' => '热情友好，善于表达'], ['value' => 'S', 'text' => '耐心倾听，体贴理解'], ['value' => 'C', 'text' => '逻辑清晰，注重细节']]],
    ['id' => 5, 'question' => '压力之下，您会：', 'options' => [['value' => 'D', 'text' => '变得更加强势和控制'], ['value' => 'I', 'text' => '寻求他人支持和鼓励'], ['value' => 'S', 'text' => '保持冷静，按部就班'], ['value' => 'C', 'text' => '更加谨慎，反复确认']]],
    ['id' => 6, 'question' => '您认为自己的优势是：', 'options' => [['value' => 'D', 'text' => '决断力强，行动迅速'], ['value' => 'I', 'text' => '人际关系好，善于激励'], ['value' => 'S', 'text' => '可靠稳定，团队协作'], ['value' => 'C', 'text' => '分析能力强，注重细节']]],
    ['id' => 7, 'question' => '在会议中，您通常扮演的角色是：', 'options' => [['value' => 'D', 'text' => '主导者，推动决策'], ['value' => 'I', 'text' => '激励者，调动气氛'], ['value' => 'S', 'text' => '调和者，平衡各方'], ['value' => 'C', 'text' => '分析者，提供数据']]],
    ['id' => 8, 'question' => '您最不喜欢的工作环境是：', 'options' => [['value' => 'D', 'text' => '缺乏挑战，进展缓慢'], ['value' => 'I', 'text' => '被孤立，缺乏互动'], ['value' => 'S', 'text' => '变化太快，不稳定'], ['value' => 'C', 'text' => '混乱无序，缺乏标准']]],
    ['id' => 9, 'question' => '做决定时，您更依赖：', 'options' => [['value' => 'D', 'text' => '直觉和经验'], ['value' => 'I', 'text' => '他人的意见'], ['value' => 'S', 'text' => '过去的成功经验'], ['value' => 'C', 'text' => '数据和事实']]],
    ['id' => 10, 'question' => '您的工作风格是：', 'options' => [['value' => 'D', 'text' => '快速高效，注重结果'], ['value' => 'I', 'text' => '灵活多变，充满创意'], ['value' => 'S', 'text' => '稳定持续，一步一步'], ['value' => 'C', 'text' => '严谨细致，追求完美']]],
    ['id' => 11, 'question' => '遇到冲突时，您会：', 'options' => [['value' => 'D', 'text' => '直面冲突，解决问题'], ['value' => 'I', 'text' => '调解双方，化解矛盾'], ['value' => 'S', 'text' => '避免冲突，保持和谐'], ['value' => 'C', 'text' => '分析原因，找出根本']]],
    ['id' => 12, 'question' => '您期望的领导风格是：', 'options' => [['value' => 'D', 'text' => '给予挑战和自主权'], ['value' => 'I', 'text' => '认可和表扬'], ['value' => 'S', 'text' => '稳定和支持'], ['value' => 'C', 'text' => '明确的指导和标准']]],
    ['id' => 13, 'question' => '处理任务时，您更注重：', 'options' => [['value' => 'D', 'text' => '速度和效率'], ['value' => 'I', 'text' => '创意和新颖'], ['value' => 'S', 'text' => '过程和协作'], ['value' => 'C', 'text' => '质量和准确']]],
    ['id' => 14, 'question' => '您的社交方式是：', 'options' => [['value' => 'D', 'text' => '目的明确，建立有价值的关系'], ['value' => 'I', 'text' => '广泛社交，认识各种人'], ['value' => 'S', 'text' => '深度交往，维护长期友谊'], ['value' => 'C', 'text' => '选择性社交，志同道合']]],
    ['id' => 15, 'question' => '您理想的工作节奏是：', 'options' => [['value' => 'D', 'text' => '快节奏，充满挑战'], ['value' => 'I', 'text' => '灵活多变，充满活力'], ['value' => 'S', 'text' => '稳定有序，可预测的'], ['value' => 'C', 'text' => '有条理，按计划进行']]],
    ['id' => 16, 'question' => '面对变化，您的态度是：', 'options' => [['value' => 'D', 'text' => '主动拥抱，寻找机会'], ['value' => 'I', 'text' => '积极适应，乐观面对'], ['value' => 'S', 'text' => '需要时间，逐步适应'], ['value' => 'C', 'text' => '谨慎评估，确保万无一失']]],
    ['id' => 17, 'question' => '您的时间管理风格是：', 'options' => [['value' => 'D', 'text' => '高效利用，最大化产出'], ['value' => 'I', 'text' => '灵活安排，留有余地'], ['value' => 'S', 'text' => '按部就班，稳定执行'], ['value' => 'C', 'text' => '精确规划，严格执行']]],
    ['id' => 18, 'question' => '激励您的是：', 'options' => [['value' => 'D', 'text' => '成就和控制'], ['value' => 'I', 'text' => '认可和社交'], ['value' => 'S', 'text' => '稳定和归属'], ['value' => 'C', 'text' => '正确和标准']]],
    ['id' => 19, 'question' => '您处理细节的方式是：', 'options' => [['value' => 'D', 'text' => '关注大局，委托他人'], ['value' => 'I', 'text' => '可能忽略，更关注整体'], ['value' => 'S', 'text' => '认真对待，确保准确'], ['value' => 'C', 'text' => '极度重视，追求完美']]],
    ['id' => 20, 'question' => '您对规则的态度是：', 'options' => [['value' => 'D', 'text' => '规则是用来打破的'], ['value' => 'I', 'text' => '灵活运用，不拘泥于规则'], ['value' => 'S', 'text' => '遵守规则，维护秩序'], ['value' => 'C', 'text' => '严格遵守，确保合规']]],
];

// 生成 SQL INSERT 语句
function generateSQL($questions, $type, $enterpriseId = null) {
    $sql = "-- {$type} 题库数据\n";
    $sql .= "-- enterpriseId: " . ($enterpriseId === null ? 'NULL (超管题库)' : $enterpriseId) . "\n\n";
    
    foreach ($questions as $q) {
        $optionsJson = json_encode($q['options'], JSON_UNESCAPED_UNICODE);
        $dimension = isset($q['dimension']) ? "'" . addslashes($q['dimension']) . "'" : 'NULL';
        $enterpriseIdSql = $enterpriseId === null ? 'NULL' : $enterpriseId;
        
        $sql .= "INSERT INTO `mbti_questions` (`type`, `question`, `options`, `dimension`, `enterpriseId`, `sort`, `status`, `createdAt`, `updatedAt`) VALUES ";
        $sql .= "('{$type}', '" . addslashes($q['question']) . "', '" . addslashes($optionsJson) . "', {$dimension}, {$enterpriseIdSql}, {$q['id']}, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());\n";
    }
    
    return $sql . "\n";
}

// 生成完整的 SQL 文件
$sqlContent = "-- ============================================\n";
$sqlContent .= "-- 题库数据导入 SQL\n";
$sqlContent .= "-- 来源：mbti2 项目\n";
$sqlContent .= "-- 生成时间：" . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- ============================================\n\n";
$sqlContent .= "SET NAMES utf8mb4;\n";
$sqlContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// 超管题库（enterpriseId = NULL）
$sqlContent .= "-- ============================================\n";
$sqlContent .= "-- 超管题库（enterpriseId = NULL）\n";
$sqlContent .= "-- ============================================\n\n";
$sqlContent .= generateSQL($mbtiQuestions, 'mbti', null);
$sqlContent .= generateSQL($pdpQuestions, 'pdp', null);
$sqlContent .= generateSQL($discQuestions, 'disc', null);

$sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";

// 保存到文件
file_put_contents(__DIR__ . '/questions_data.sql', $sqlContent);

echo "SQL 文件已生成：questions_data.sql\n";
echo "MBTI 题目数：" . count($mbtiQuestions) . "\n";
echo "PDP 题目数：" . count($pdpQuestions) . "\n";
echo "DISC 题目数：" . count($discQuestions) . "\n";
echo "总计：" . (count($mbtiQuestions) + count($pdpQuestions) + count($discQuestions)) . " 题\n";


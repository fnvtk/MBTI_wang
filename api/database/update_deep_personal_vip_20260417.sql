-- 个人深度服务 deep_personal：¥198/小时（洞察版）+ ¥1980/2小时（VMP个人定位）
-- 文案以套餐与交付为主，不含人物背书；超管「深度服务价格」可继续改。
-- 执行：mysql ... < update_deep_personal_vip_20260417.sql

UPDATE `mbti_pricing_config`
SET `config` = '{\"categories\":[{\"id\":\"1772268461984\",\"price\":198,\"title\":\"个人深度洞察版\",\"features\":[\"AI面部分析（基于东方面相学与西方心理学）\",\"MBTI性格测试（16型人格完整解读）\",\"盖洛普优势Top5识别（发现你的核心天赋）\",\"PDP行为偏好分析（了解你的行为模式）\",\"DISC沟通风格分析（提升沟通效率）\",\"多维度综合性格报告（包含优势解读、潜在盲区提示）\",\"职业发展方向推荐（匹配最适合你的职业）\"],\"subtitle\":\"三张照片+问卷，全面解锁你的内在潜能\",\"priceUnit\":\"/小时\",\"actionType\":\"buy\",\"productKey\":\"1772268461984\",\"purchaseButtonText\":\"了解自己并付款\",\"consultWechat\":\"mi5p9-f4gx6-tl4nw-a2qb8-4wgap\",\"serviceWechat\":\"Lkdie01\",\"successMessage\":\"购买成功！我们的顾问会尽快与您联系，为您提供专属深度解读服务。\"},{\"id\":\"deep_personal_vmp\",\"price\":1980,\"title\":\"VMP个人定位\",\"features\":[\"V/M/P报告导读：校准价值观、动机与人格优势\",\"自我认知与角色定位：梳理岗位角色与发展瓶颈\",\"咨询交付：问题界定→方案共创→书面行动清单\",\"适用：转型犹豫、晋升卡顿、职业焦虑与方向不清\"],\"subtitle\":\"2小时深度咨询：聚焦VMP定位与职业破局，交付可执行路径\",\"priceUnit\":\"/2小时\",\"actionType\":\"buy\",\"productKey\":\"deep_personal_vmp\",\"purchaseButtonText\":\"了解套餐并付款\",\"consultWechat\":\"mi5p9-f4gx6-tl4nw-a2qb8-4wgap\",\"serviceWechat\":\"Lkdie01\",\"successMessage\":\"购买成功！顾问将按预约与您对接VMP个人定位服务。\"}]}',
    `updatedAt` = UNIX_TIMESTAMP()
WHERE `type` = 'deep_personal' AND `enterpriseId` IS NULL;

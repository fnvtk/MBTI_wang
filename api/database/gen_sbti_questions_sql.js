/**
 * 生成 SBTI 题库 INSERT SQL（mbti_questions 表）
 * 运行: node gen_sbti_questions_sql.js > sbti_questions_data.sql
 */
const fs = require('fs');
const path = require('path');

const rows = [];

function add(dim, sort, question, options) {
  rows.push({ dim, sort, question, options });
}

// —— 30 道计分题（与 aisbti.com 同源逻辑，value 1/2/3）——
add('S1', 1, '我不仅是屌丝，我还是joker,我还是咸鱼，这辈子没谈过一场恋爱，胆怯又自卑，我的青春就是一场又一场的意淫，每一天幻想着我也能有一个女孩子和我一起压马路，一起逛街，一起玩，现实却是爆了父母金币，读了个烂学校，混日子之后找班上，没有理想，没有目标，没有能力的三无人员，每次看到你能在网上开屌丝的玩笑，我都想哭，我就是地底下的老鼠，透过下水井的缝隙，窥探地上的各种美好，每一次看到这种都是对我心灵的一次伤害，对我生存空间的一次压缩，求求哥们给我们这种小丑一点活路吧，我真的不想在白天把枕巾哭湿一大片', [
  { value: 1, text: '我哭了。。' },
  { value: 2, text: '这是什么。。' },
  { value: 3, text: '这不是我！' },
]);
add('S1', 2, '我不够好，周围的人都比我优秀', [
  { value: 1, text: '确实' },
  { value: 2, text: '有时' },
  { value: 3, text: '不是' },
]);
add('S2', 3, '我很清楚真正的自己是什么样的', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('S2', 4, '我内心有真正追求的东西', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('S3', 5, '我一定要不断往上爬、变得更厉害', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('S3', 6, '外人的评价对我来说无所吊谓。', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('E1', 7, '对象超过5小时没回消息，说自己窜稀了，你会怎么想？', [
  { value: 1, text: '拉稀不可能5小时，也许ta隐瞒了我。' },
  { value: 2, text: '在信任和怀疑之间摇摆。' },
  { value: 3, text: '也许今天ta真的不太舒服。' },
]);
add('E1', 8, '我在感情里经常担心被对方抛弃', [
  { value: 1, text: '是的' },
  { value: 2, text: '偶尔' },
  { value: 3, text: '不是' },
]);
add('E2', 9, '我对天发誓，我对待每一份感情都是认真的！', [
  { value: 1, text: '并没有' },
  { value: 2, text: '也许？' },
  { value: 3, text: '是的！（问心无愧骄傲脸）' },
]);
add('E2', 10, '你的恋爱对象是一个尊老爱幼，温柔敦厚，洁身自好，光明磊落，大义凛然，能言善辩，口才流利，观察入微，见多识广，博学多才，诲人不倦，和蔼可亲，平易近人，心地善良，慈眉善目，积极进取，意气风发，玉树临风，国色天香，倾国倾城，花容月貌的人，此时你会？', [
  { value: 1, text: '就算ta再优秀我也不会陷入太深。' },
  { value: 2, text: '会介于A和C之间。' },
  { value: 3, text: '会非常珍惜ta，也许会变成恋爱脑。' },
]);
add('E3', 11, '恋爱后，对象非常黏人，你作何感想？', [
  { value: 1, text: '那很爽了' },
  { value: 2, text: '都行无所谓' },
  { value: 3, text: '我更喜欢保留独立空间' },
]);
add('E3', 12, '我在任何关系里都很重视个人空间', [
  { value: 1, text: '我更喜欢依赖与被依赖' },
  { value: 2, text: '看情况' },
  { value: 3, text: '是的！（斩钉截铁地说道）' },
]);
add('A1', 13, '大多数人是善良的', [
  { value: 1, text: '其实邪恶的人心比世界上的痔疮更多。' },
  { value: 2, text: '也许吧。' },
  { value: 3, text: '是的，我愿相信好人更多。' },
]);
add('A1', 14, '你走在街上，一位萌萌的小女孩蹦蹦跳跳地朝你走来（正脸、侧脸看都萌，用vivo、苹果、华为、OPPO手机看都萌，实在是非常萌的那种），她递给你一根棒棒糖，此时你作何感想？', [
  { value: 1, text: '这也许是一种新型诈骗？还是走开为好。' },
  { value: 2, text: '一脸懵逼，作挠头状' },
  { value: 3, text: '呜呜她真好真可爱！居然给我棒棒糖！' },
]);
add('A2', 15, '快考试了，学校规定必须上晚自习，请假会扣分，但今晚你约了女/男神一起玩《绝地求生：刺激战场》（一款刺激的游戏），你怎么办？', [
  { value: 1, text: '翘了！反正就一次！' },
  { value: 2, text: '干脆请个假吧。' },
  { value: 3, text: '都快考试了还去啥。' },
]);
add('A2', 16, '我喜欢打破常规，不喜欢被束缚', [
  { value: 1, text: '认同' },
  { value: 2, text: '保持中立' },
  { value: 3, text: '不认同' },
]);
add('A3', 17, '我做事通常有目标。', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('A3', 18, '突然某一天，我意识到人生哪有什么他妈的狗屁意义，人不过是和动物一样被各种欲望支配着，纯纯是被激素控制的东西，饿了就吃，困了就睡，一发情就想交配，我们简直和猪狗一样没什么区别。', [
  { value: 1, text: '是这样的。' },
  { value: 2, text: '也许是，也许不是。' },
  { value: 3, text: '这简直是胡扯' },
]);
add('Ac1', 19, '我做事主要为了取得成果和进步，而不是避免麻烦和风险。', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('Ac1', 20, '你因便秘坐在马桶上（已长达30分钟），拉不出很难受。此时你更像', [
  { value: 1, text: '再坐三十分钟看看，说不定就有了。' },
  { value: 2, text: '用力拍打自己的屁股并说：“死屁股，快拉啊！”' },
  { value: 3, text: '使用开塞露，快点拉出来才好。' },
]);
add('Ac2', 21, '我做决定比较果断，不喜欢犹豫', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('Ac2', 22, '此题没有题目，请盲选', [
  { value: 1, text: '反复思考后感觉应该选A？' },
  { value: 2, text: '啊，要不选B？' },
  { value: 3, text: '不会就选C？' },
]);
add('Ac3', 23, '别人说你“执行力强”，你内心更接近哪句？', [
  { value: 1, text: '我被逼到最后确实执行力超强。。。' },
  { value: 2, text: '啊，有时候吧。' },
  { value: 3, text: '是的，事情本来就该被推进' },
]);
add('Ac3', 24, '我做事常常有计划，____', [
  { value: 1, text: '然而计划不如变化快。' },
  { value: 2, text: '有时能完成，有时不能。' },
  { value: 3, text: '我讨厌被打破计划。' },
]);
add('So1', 25, '你因玩《第五人格》（一款刺激的游戏）而结识许多网友，并被邀请线下见面，你的想法是？', [
  { value: 1, text: '网上口嗨下就算了，真见面还是有点忐忑。' },
  { value: 2, text: '见网友也挺好，反正谁来聊我就聊两句。' },
  { value: 3, text: '我会打扮一番并热情聊天，万一呢，我是说万一呢？' },
]);
add('So1', 26, '朋友带了ta的朋友一起来玩，你最可能的状态是', [
  { value: 1, text: '对“朋友的朋友”天然有点距离感，怕影响二人关系' },
  { value: 2, text: '看对方，能玩就玩。' },
  { value: 3, text: '朋友的朋友应该也算我的朋友！要热情聊天' },
]);
add('So2', 27, '我和人相处主打一个电子围栏，靠太近会自动报警。', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);
add('So2', 28, '我渴望和我信任的人关系密切，熟得像失散多年的亲戚。', [
  { value: 1, text: '认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '不认同' },
]);
add('So3', 29, '有时候你明明对一件事有不同的、负面的看法，但最后没说出来。多数情况下原因是：', [
  { value: 1, text: '这种情况较少。' },
  { value: 2, text: '可能碍于情面或者关系。' },
  { value: 3, text: '不想让别人知道自己是个阴暗的人。' },
]);
add('So3', 30, '我在不同人面前会表现出不一样的自己', [
  { value: 1, text: '不认同' },
  { value: 2, text: '中立' },
  { value: 3, text: '认同' },
]);

// 闸口 / 条件题（不计入 15 维得分，前端按 dimension 识别）
add('DG1', 31, '您平时有什么爱好？', [
  { value: 1, text: '吃喝拉撒' },
  { value: 2, text: '艺术爱好' },
  { value: 3, text: '饮酒' },
  { value: 4, text: '健身' },
]);
add('DG2', 32, '您对饮酒的态度是？', [
  { value: 1, text: '小酌怡情，喝不了太多。' },
  { value: 2, text: '我习惯将白酒灌在保温杯，当白开水喝，酒精令我信服。' },
]);

function sqlEscape(str) {
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "''");
}

const out = [];
out.push('-- ============================================');
out.push('-- SBTI 题库导入（mbti_questions）');
out.push('-- 来源：Silly Big Personality Test 公开页面逻辑');
out.push('-- type=sbti；计分题 dimension=S1..So3；闸口 DG1/DG2');
out.push('-- 执行前请确认已扩展 API 支持 type=sbti（当前 questions 接口默认仅 mbti/disc/pdp）');
out.push('-- ============================================');
out.push('');
out.push('SET NAMES utf8mb4;');
out.push('SET FOREIGN_KEY_CHECKS = 0;');
out.push('');
out.push('-- 清空超管同名题库（可按需注释）');
out.push("DELETE FROM `mbti_questions` WHERE `type` = 'sbti' AND `enterpriseId` IS NULL;");
out.push('');

for (const r of rows) {
  const optJson = JSON.stringify(r.options);
  const line =
    'INSERT INTO `mbti_questions` (`type`, `question`, `options`, `dimension`, `enterpriseId`, `sort`, `status`, `createdAt`, `updatedAt`) VALUES ' +
    `('sbti', '${sqlEscape(r.question)}', '${sqlEscape(optJson)}', '${sqlEscape(r.dim)}', NULL, ${r.sort}, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());`;
  out.push(line);
}

out.push('');
out.push('SET FOREIGN_KEY_CHECKS = 1;');

const dest = path.join(__dirname, 'sbti_questions_data.sql');
fs.writeFileSync(dest, out.join('\n'), 'utf8');
console.log('Written:', dest, 'lines:', rows.length);

const fs = require('fs')
const path = require('path')
const html = fs.readFileSync(path.join(__dirname, '..', '..', '_sbti_src.html'), 'utf8') // api/database -> project root
function grab(name) {
  const re = new RegExp(`const ${name} = ([\\s\\S]*?);\\s*const `)
  const m = html.match(re)
  if (!m) throw new Error('missing ' + name)
  return m[1].trim()
}
const parts = ['TYPE_LIBRARY', 'TYPE_IMAGES', 'NORMAL_TYPES', 'DIM_EXPLANATIONS'].map((n) => `${n}: ${grab(n)}`)
const dest = path.join(__dirname, '..', '..', 'miniprogram', 'utils', 'sbtiData.js')
const head = `// 从 aisbti.com 测试页提取，算法与官方一致；勿手改数据结构\nmodule.exports = {\n`
const tail = `,
  dimensionOrder: ['S1','S2','S3','E1','E2','E3','A1','A2','A3','Ac1','Ac2','Ac3','So1','So2','So3'],
  dimensionMeta: {
    S1: { name: 'S1 自尊自信', model: '自我模型' },
    S2: { name: 'S2 自我清晰度', model: '自我模型' },
    S3: { name: 'S3 核心价值', model: '自我模型' },
    E1: { name: 'E1 依恋安全感', model: '情感模型' },
    E2: { name: 'E2 情感投入度', model: '情感模型' },
    E3: { name: 'E3 边界与依赖', model: '情感模型' },
    A1: { name: 'A1 世界观倾向', model: '态度模型' },
    A2: { name: 'A2 规则与灵活度', model: '态度模型' },
    A3: { name: 'A3 人生意义感', model: '态度模型' },
    Ac1: { name: 'Ac1 动机导向', model: '行动驱力模型' },
    Ac2: { name: 'Ac2 决策风格', model: '行动驱力模型' },
    Ac3: { name: 'Ac3 执行模式', model: '行动驱力模型' },
    So1: { name: 'So1 社交主动性', model: '社交模型' },
    So2: { name: 'So2 人际边界感', model: '社交模型' },
    So3: { name: 'So3 表达与真实度', model: '社交模型' }
  }
};
`
fs.writeFileSync(dest, head + parts.join(',\n') + tail, 'utf8')
console.log('written', dest, fs.statSync(dest).size)

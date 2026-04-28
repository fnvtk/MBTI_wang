#!/usr/bin/env node
// 使用内置 https 模块，无需任何外部依赖
// 将 v0/fnvtk-6c860377 分支强制推送到 main

import https from 'https'

const TOKEN = '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'
const OWNER = 'fnvtk'
const REPO  = 'MBTI_wang'
const SOURCE = 'v0/fnvtk-6c860377'
const TARGET = 'main'

function apiRequest(method, path, body) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null
    const options = {
      hostname: 'api.github.com',
      port: 443,
      path: `/repos/${OWNER}/${REPO}${path}`,
      method,
      headers: {
        'Authorization': `token ${TOKEN}`,
        'Accept': 'application/vnd.github.v3+json',
        'User-Agent': 'v0-push-script',
        'Content-Type': 'application/json',
        ...(data ? { 'Content-Length': Buffer.byteLength(data) } : {}),
      },
    }
    const req = https.request(options, (res) => {
      let raw = ''
      res.on('data', chunk => raw += chunk)
      res.on('end', () => resolve({ status: res.statusCode, body: raw ? JSON.parse(raw) : {} }))
    })
    req.on('error', reject)
    if (data) req.write(data)
    req.end()
  })
}

async function main() {
  // 1. 获取源分支最新 commit SHA
  console.log(`[1] 获取 ${SOURCE} 最新 commit SHA...`)
  const encodedBranch = encodeURIComponent(SOURCE)
  const ref = await apiRequest('GET', `/git/ref/heads/${encodedBranch}`)
  if (ref.status !== 200) {
    console.error(`    失败 (${ref.status}):`, JSON.stringify(ref.body))
    process.exit(1)
  }
  const sha = ref.body.object.sha
  console.log(`    SHA: ${sha}`)

  // 2. 强制更新 main
  console.log(`[2] 强制更新 ${TARGET} -> ${sha.slice(0, 12)}...`)
  const update = await apiRequest('PATCH', `/git/refs/heads/${TARGET}`, { sha, force: true })
  if (update.status === 200) {
    console.log(`    成功！${TARGET} 已更新。`)
    console.log(`    地址: https://github.com/${OWNER}/${REPO}/tree/${TARGET}`)
  } else if (update.status === 422) {
    // main 不存在，创建
    console.log(`    main 不存在，尝试创建...`)
    const create = await apiRequest('POST', '/git/refs', { ref: `refs/heads/${TARGET}`, sha })
    if (create.status === 201) {
      console.log(`    创建成功！`)
    } else {
      console.error(`    创建失败 (${create.status}):`, JSON.stringify(create.body))
      process.exit(1)
    }
  } else {
    console.error(`    失败 (${update.status}):`, JSON.stringify(update.body))
    process.exit(1)
  }

  console.log('\n完成！v0 代码已推送到 main 分支。')
}

main().catch(e => { console.error(e); process.exit(1) })

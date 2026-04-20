#!/usr/bin/env node
/**
 * 生成与 JwtService::generateToken 同格式的 token（签名段为 hex），仅供本机冒烟。
 * 读取 api/.env 中的 JWT_SECRET。
 */
const crypto = require('crypto')
const fs = require('fs')
const path = require('path')

const envPath = path.join(__dirname, '..', '.env')
let secret = 'mbti_jwt_secret_key_2024_change_in_production'
try {
  const raw = fs.readFileSync(envPath, 'utf8')
  const m = raw.match(/JWT_SECRET\s*=\s*(\S+)/)
  if (m) secret = m[1].trim()
} catch (_) {}

const header = Buffer.from(JSON.stringify({ typ: 'JWT', alg: 'HS256' })).toString('base64')
const now = Math.floor(Date.now() / 1000)
const payload = Buffer.from(
  JSON.stringify({
    userId: 1,
    user_id: 1,
    source: 'wechat',
    exp: now + 3600,
    iat: now,
  })
).toString('base64')
const sig = crypto.createHmac('sha256', secret).update(header + '.' + payload).digest('hex')
process.stdout.write(header + '.' + payload + '.' + sig + '\n')

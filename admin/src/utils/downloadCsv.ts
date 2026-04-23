import { getApiV1BaseURL } from '@/utils/request'
import { getBearerTokenForCurrentApp } from '@/utils/authStorage'

/**
 * GET 下载 CSV（带当前后台 Bearer；不走 JSON 拦截器，用于 export 等二进制响应）
 */
export async function downloadCsvGet(path: string, filename: string, query?: Record<string, string | number | undefined>) {
  const base = getApiV1BaseURL().replace(/\/$/, '')
  const p = path.replace(/^\//, '')
  const fullPath = `${base}/${p}`.replace(/\/{2,}/g, '/')
  const u = new URL(fullPath, window.location.origin)
  if (query) {
    Object.entries(query).forEach(([k, v]) => {
      if (v === undefined || v === null || v === '') return
      u.searchParams.set(k, String(v))
    })
  }
  const token = getBearerTokenForCurrentApp()
  const res = await fetch(u.toString(), {
    headers: token ? { Authorization: `Bearer ${token}` } : {}
  })
  if (!res.ok) {
    let msg = `HTTP ${res.status}`
    try {
      const t = await res.text()
      if (t) {
        try {
          const j = JSON.parse(t)
          msg = j.message || j.msg || msg
        } catch {
          msg = t.slice(0, 200)
        }
      }
    } catch {
      // ignore
    }
    throw new Error(msg)
  }
  const blob = await res.blob()
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}

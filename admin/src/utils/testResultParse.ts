/**
 * 统一解析 test_results 单条的 result / resultData（字符串 JSON 或已解析对象）
 */
export function parseTestResultPayload(raw: unknown): Record<string, any> | null {
  if (raw == null || raw === '') return null
  if (typeof raw === 'object' && !Array.isArray(raw)) {
    return raw as Record<string, any>
  }
  if (typeof raw !== 'string') return null
  try {
    const data = JSON.parse(raw) as unknown
    if (!data || typeof data !== 'object' || Array.isArray(data)) return null
    return data as Record<string, any>
  } catch {
    return null
  }
}

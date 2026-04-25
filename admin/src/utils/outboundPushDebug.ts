/**
 * 出站推送「测试与调试」区块是否展示（超管后台）。
 * - 生产构建默认不展示；
 * - 本地 `npm run dev`（import.meta.env.DEV）默认展示；
 * - 生产若需临时调试，在构建前于 .env 设置 VITE_SHOW_OUTBOUND_PUSH_DEBUG=true。
 */
export function isOutboundPushDebugVisible(): boolean {
  if (import.meta.env.VITE_SHOW_OUTBOUND_PUSH_DEBUG === 'false') {
    return false
  }
  return (
    Boolean(import.meta.env.DEV) ||
    import.meta.env.VITE_SHOW_OUTBOUND_PUSH_DEBUG === 'true'
  )
}

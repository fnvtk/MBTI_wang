/**
 * 格式化金额，自动转换为万或亿单位
 * @param amount 金额（元）
 * @param decimals 保留小数位数，默认1位（仅用于万和亿单位）
 * @returns 格式化后的金额字符串，如 "¥1000"、"¥1.2万" 或 "¥3.5亿"
 */
export function formatCurrency(amount: number, decimals: number = 1): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return '¥0'
  }

  const absAmount = Math.abs(amount)
  
  // 大于等于1亿，使用亿单位
  if (absAmount >= 100000000) {
    return `¥${(amount / 100000000).toFixed(decimals)}亿`
  }
  
  // 大于等于1万，使用万单位
  if (absAmount >= 10000) {
    return `¥${(amount / 10000).toFixed(decimals)}万`
  }
  
  // 小于1万，直接显示整数（不带小数）
  return `¥${Math.round(amount).toLocaleString()}`
}

/**
 * 格式化金额（不带符号），自动转换为万或亿单位
 * @param amount 金额（元）
 * @param decimals 保留小数位数，默认1位（仅用于万和亿单位）
 * @returns 格式化后的金额字符串，如 "1000"、"1.2万" 或 "3.5亿"
 */
export function formatCurrencyWithoutSymbol(amount: number, decimals: number = 1): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return '0'
  }

  const absAmount = Math.abs(amount)
  
  // 大于等于1亿，使用亿单位
  if (absAmount >= 100000000) {
    return `${(amount / 100000000).toFixed(decimals)}亿`
  }
  
  // 大于等于1万，使用万单位
  if (absAmount >= 10000) {
    return `${(amount / 10000).toFixed(decimals)}万`
  }
  
  // 小于1万，直接显示整数（不带小数）
  return `¥${Math.round(amount).toLocaleString()}`
}

/**
 * 格式化金额（元），始终保留两位小数，用于订单/财务等精确展示
 * 避免 0.23 元被 formatCurrency 四舍五入成 ¥0
 * @param amount 金额（元）
 */
export function formatMoneyYuan(amount: number): string {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return '¥0.00'
  }
  const n = Number(amount)
  return '¥' + (Number.isFinite(n) ? n.toFixed(2) : '0.00')
}


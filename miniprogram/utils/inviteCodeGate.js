/**
 * 支付前邀请码弹框闸门（与开发文档 §3 一致）
 */
const { shouldHideInviteCodeEntry } = require('./miniprogramAuditGate.js')

const STORAGE_BOUND = 'inviteCodeBound'
const STORAGE_SKIP = 'inviteCodeSkipped'

function shouldPromptInviteCode() {
  try {
    if (wx.getStorageSync(STORAGE_BOUND)) return false
    if (wx.getStorageSync(STORAGE_SKIP)) return false
  } catch (e) {}
  return true
}

function markInviteSkipped() {
  try {
    wx.setStorageSync(STORAGE_SKIP, 1)
  } catch (e) {}
}

function markInviteBound() {
  try {
    wx.setStorageSync(STORAGE_BOUND, 1)
  } catch (e) {}
}

/**
 * @param {WechatMiniprogram.Page.TrivialInstance | null} page
 * @returns {Promise<boolean>} true 表示可继续发起支付
 */
function ensureInviteCodeGate(page) {
  return new Promise((resolve) => {
    try {
      const app = getApp()
      if (shouldHideInviteCodeEntry(app && app.globalData)) {
        resolve(true)
        return
      }
    } catch (e) {}
    if (!shouldPromptInviteCode()) {
      resolve(true)
      return
    }
    if (!page || typeof page.setData !== 'function') {
      resolve(true)
      return
    }
    page._inviteCodeGateResolve = resolve
    page.setData({ showInviteCodeDialog: true })
  })
}

/**
 * @param {WechatMiniprogram.Page.TrivialInstance | null} page
 * @param {boolean} canProceed
 */
function finishInviteCodeGate(page, canProceed) {
  if (page && typeof page.setData === 'function') {
    page.setData({ showInviteCodeDialog: false })
  }
  const fn = page && page._inviteCodeGateResolve
  if (page) {
    page._inviteCodeGateResolve = null
  }
  if (typeof fn === 'function') {
    fn(canProceed !== false)
  }
}

/** 非支付闸门：从推广中心 / 我的 等入口主动打开填写弹框 */
function openInviteCodeDialog(page) {
  if (!page || typeof page.setData !== 'function') return
  page.setData({ showInviteCodeDialog: true })
}

module.exports = {
  shouldPromptInviteCode,
  markInviteSkipped,
  markInviteBound,
  ensureInviteCodeGate,
  finishInviteCodeGate,
  openInviteCodeDialog,
  STORAGE_BOUND,
  STORAGE_SKIP
}

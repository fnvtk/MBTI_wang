/**
 * 管理端与超管端使用独立 localStorage，避免互相覆盖 Token。
 * 兼容旧键 authToken / userRole：首次加载时迁移到新键后删除旧键。
 */

export const ADMIN_TOKEN_KEY = 'adminAuthToken'
export const ADMIN_ROLE_KEY = 'adminUserRole'
export const ADMIN_USER_ID_KEY = 'adminUserId'

export const SUPERADMIN_TOKEN_KEY = 'superadminAuthToken'
export const SUPERADMIN_ROLE_KEY = 'superadminUserRole'
export const SUPERADMIN_USER_ID_KEY = 'superadminUserId'

const LEGACY_TOKEN = 'authToken'
const LEGACY_ROLE = 'userRole'
const LEGACY_USER_ID = 'userId'

/** 应用启动时调用一次 */
export function migrateLegacyAuthStorage(): void {
  if (typeof localStorage === 'undefined') return

  const legacyToken = localStorage.getItem(LEGACY_TOKEN)
  const legacyRole = localStorage.getItem(LEGACY_ROLE)
  if (!legacyToken || !legacyRole) return

  const hasSplit =
    localStorage.getItem(ADMIN_TOKEN_KEY) || localStorage.getItem(SUPERADMIN_TOKEN_KEY)
  if (hasSplit) {
    localStorage.removeItem(LEGACY_TOKEN)
    localStorage.removeItem(LEGACY_ROLE)
    localStorage.removeItem(LEGACY_USER_ID)
    return
  }

  const legacyUserId = localStorage.getItem(LEGACY_USER_ID)
  if (legacyRole === 'superadmin') {
    localStorage.setItem(SUPERADMIN_TOKEN_KEY, legacyToken)
    localStorage.setItem(SUPERADMIN_ROLE_KEY, legacyRole)
    if (legacyUserId) localStorage.setItem(SUPERADMIN_USER_ID_KEY, legacyUserId)
  } else if (['admin', 'enterprise_admin'].includes(legacyRole)) {
    localStorage.setItem(ADMIN_TOKEN_KEY, legacyToken)
    localStorage.setItem(ADMIN_ROLE_KEY, legacyRole)
    if (legacyUserId) localStorage.setItem(ADMIN_USER_ID_KEY, legacyUserId)
  }

  localStorage.removeItem(LEGACY_TOKEN)
  localStorage.removeItem(LEGACY_ROLE)
  localStorage.removeItem(LEGACY_USER_ID)
}

export function getAdminToken(): string | null {
  return localStorage.getItem(ADMIN_TOKEN_KEY)
}

export function getAdminRole(): string | null {
  return localStorage.getItem(ADMIN_ROLE_KEY)
}

export function getSuperadminToken(): string | null {
  return localStorage.getItem(SUPERADMIN_TOKEN_KEY)
}

export function getSuperadminRole(): string | null {
  return localStorage.getItem(SUPERADMIN_ROLE_KEY)
}

/** axios / 上传：按当前页面路径选择 Bearer */
export function getBearerTokenForCurrentApp(): string | null {
  if (typeof window === 'undefined') return null
  if (window.location.pathname.startsWith('/superadmin')) {
    return getSuperadminToken()
  }
  return getAdminToken()
}

export function clearAdminAuthKeys(): void {
  localStorage.removeItem(ADMIN_TOKEN_KEY)
  localStorage.removeItem(ADMIN_ROLE_KEY)
  localStorage.removeItem(ADMIN_USER_ID_KEY)
  localStorage.removeItem('adminLoggedIn')
}

export function clearSuperadminAuthKeys(): void {
  localStorage.removeItem(SUPERADMIN_TOKEN_KEY)
  localStorage.removeItem(SUPERADMIN_ROLE_KEY)
  localStorage.removeItem(SUPERADMIN_USER_ID_KEY)
  localStorage.removeItem('superAdminLoggedIn')
}

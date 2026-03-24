<?php
// API路由定义
use think\facade\Route;

// ==================== 前端用户API路由 ====================
// 为避免匹配混淆，手机号接口单独声明完整路径（优先级更高）
Route::post('api/auth/wechat/phone', 'api.Auth/wechatPhone')->middleware(['cors', 'auth']);

// 前端公开API路由（不需要认证）
Route::group('api', function () {
    // 用户认证相关
    Route::post('auth/login', 'api.Auth/login');
    Route::post('auth/register', 'api.Auth/register');
    Route::post('auth/refresh', 'api.Auth/refresh');
    // 微信小程序登录
    Route::post('auth/wechat', 'api.Auth/wechatLogin');
})->middleware('cors');

// 小程序/前端运行配置与面相分析（可选 token）
Route::group('api', function () {
    Route::get('config/runtime', 'api.AppConfig/runtime');
    Route::get('config/deep-pricing', 'api.AppConfig/deepPricing');
    Route::post('analyze', 'api.Analyze/index');
})->middleware('cors');

// 前端需要认证的API路由
Route::group('api', function () {
    // 用户信息
    Route::get('auth/me', 'api.Auth/me');
    Route::post('auth/logout', 'api.Auth/logout');
    // 小程序扫码企业邀请后绑定企业
    Route::post('enterprise/bind', 'api.Auth/wechatBindEnterprise');
    // 企业版简历上传记录（具体路径放前面，避免被 POST resume-uploads 吞掉）
    Route::get('enterprise/resume-uploads', 'api.EnterpriseResume/list');
    Route::post('enterprise/resume-uploads/set-default', 'api.EnterpriseResume/setDefault');
    Route::post('enterprise/resume-uploads/delete', 'api.EnterpriseResume/delete');
    Route::post('enterprise/resume-uploads', 'api.EnterpriseResume/add');
    // 小程序用户更新资料
    Route::put('auth/wechat/profile', 'api.Auth/updateWechatProfile');
    // 小程序用户上传图片（头像等）
    Route::post('upload/image', 'api.Upload/image');
    // 小程序用户上传文件（候选人简历等）
    Route::post('upload/file', 'api.Upload/file');
    // 当前用户测试历史记录（小程序「测试历史」页）
    Route::get('test/history', 'api.Test/history');
    // 当前用户各类型最新一条记录（小程序「我的」页）
    Route::get('test/recent', 'api.Test/recent');
    // 单条测试详情
    Route::get('test/detail', 'api.Test/detail');
    // 提交测试结果（MBTI/DISC/PDP 等）
    Route::post('test/submit', 'api.Test/submit');
    // 简历综合分析（基于人脸/MBTI/PDP/DISC 最近一次结果）
    Route::post('resume/analyze', 'api.Analyze/resumeAnalysis');
    // 支付与订单
    Route::post('payment/create', 'api.Payment/create');
    Route::post('payment/notify', 'api.Payment/notify');
    Route::get('payment/query', 'api.Payment/query');
    // 分销
    Route::post('distribution/bind', 'api.Distribution/bind');
    Route::get('distribution/stats', 'api.Distribution/stats');
    Route::get('distribution/bindings', 'api.Distribution/bindings');
    Route::get('distribution/commissions', 'api.Distribution/commissions');
    Route::post('distribution/withdraw', 'api.Distribution/withdraw');
    Route::get('distribution/withdrawals', 'api.Distribution/withdrawals');
    Route::post('distribution/withdrawals/query-transfer', 'api.Distribution/queryTransfer');
    Route::get('distribution/qrcode', 'api.Distribution/qrcode');
    Route::get('distribution/poster', 'api.Distribution/poster');
    // 微信商家转账结果回调（无需登录，但需配置到微信商户平台）
    Route::post('wechat/transfer/notify', 'api.WechatTransferNotify/notify')->middleware('cors');
    // 存客宝获客线索上报
    Route::post('crm/report', 'api.CrmReport/report');
})->middleware(['cors', 'auth']);

// ==================== 小程序API路由（匹配前端 /api 路径）====================
// 小程序公开API路由（不需要认证）- 与前端共用上面 api 组，此处可不再重复
// 小程序需要认证的API路由 - 与前端共用上面 api 组

// ==================== 普通管理员API路由（匹配前端 /api/v1/admin 路径）====================
// 普通管理员认证路由（不需要认证）
Route::group('api/v1/admin', function () {
    // 普通管理员登录
    Route::post('auth/login', 'admin.Auth/adminLogin');
    // 刷新Token
    Route::post('auth/refresh', 'admin.Auth/refresh');
})->middleware('cors');

// 普通管理员路由（需要认证）
Route::group('api/v1/admin', function () {
    // 管理员认证
    Route::get('auth/me', 'admin.Auth/me');
    Route::post('auth/logout', 'admin.Auth/logout');
    
    // 仪表盘统计
    Route::get('dashboard', 'admin.Dashboard/index');
    
    // 邀请二维码
    Route::get('invite/qrcode', 'admin.Invite/qrcode');
    
    // 通用上传
    Route::post('upload/image', 'admin.Upload/image');
    
    // 测试用户（小程序用户，只读列表与详情）
    Route::get('app-users/:id', 'admin.AppUser/detail');
    Route::get('app-users', 'admin.AppUser/index');
    // 订单列表（含用户与关联测试数据）
    Route::get('orders', 'admin.Order/index');
    // 用户管理（普通管理员和企业管理员，后台账号）
    Route::get('users', 'admin.User/index');
    Route::get('users/:id', 'admin.User/detail');
    Route::post('users', 'admin.User/create');
    Route::put('users/:id', 'admin.User/update');
    Route::delete('users/:id', 'admin.User/delete');
    Route::put('users/:id/status', 'admin.User/toggleStatus');
    
    // 题库管理（企业管理员和普通管理员）
    Route::get('questions/:id', 'admin.Question/detail');
    Route::get('questions', 'admin.Question/index');
    Route::post('questions', 'admin.Question/create');
    Route::put('questions/:id', 'admin.Question/update');
    Route::delete('questions/:id', 'admin.Question/delete');
    Route::put('questions/:id/status', 'admin.Question/toggleStatus');
    Route::post('questions/batch-import', 'admin.Question/batchImport');
    
    // 定价管理（普通管理员）
    Route::get('pricing', 'admin.Pricing/index');
    Route::put('pricing', 'admin.Pricing/update');
    
    // 系统设置（普通管理员，子路径放前面避免被 settings 吞掉）
    Route::get('settings/miniprogram', 'admin.Settings/getMiniprogramConfig');
    Route::put('settings/miniprogram', 'admin.Settings/updateMiniprogramConfig');
    Route::get('settings/poster', 'admin.Settings/getPosterConfig');
    Route::put('settings/poster', 'admin.Settings/updatePosterConfig');
    Route::get('settings/fonts', 'admin.Settings/getFonts');
    Route::put('settings/credentials', 'admin.Settings/updateCredentials');
    Route::get('settings', 'admin.Settings/index');
    
    // 分销管理（企业管理员）
    Route::get('distribution/overview', 'admin.Distribution/overview');
    Route::get('distribution/distributors', 'admin.Distribution/distributors');
    Route::get('distribution/bindings', 'admin.Distribution/bindings');
    Route::get('distribution/commissions', 'admin.Distribution/commissions');
    Route::get('distribution/withdrawals', 'admin.Distribution/withdrawals');
    Route::post('distribution/withdrawals/:id/approve', 'admin.Distribution/approveWithdrawal');
    Route::post('distribution/withdrawals/:id/reject', 'admin.Distribution/rejectWithdrawal');
    Route::get('distribution/settings', 'admin.Distribution/settings');
    Route::put('distribution/settings', 'admin.Distribution/updateSettings');

    // 企业财务（企业管理员）
    Route::get('finance/overview', 'admin.Finance/overview');
    Route::get('finance/records', 'admin.Finance/records');
    Route::post('finance/recharge-qrcode', 'admin.Finance/rechargeQrcode');
    Route::post('finance/recharge', 'admin.Finance/rechargeQrcode');
})->middleware(['cors', 'auth']);

// ==================== 超级管理员API路由（匹配前端 /api/v1/superadmin 路径）====================
// 超级管理员认证路由（不需要认证）
Route::group('api/v1/superadmin', function () {
    // 超级管理员登录
    Route::post('auth/login', 'superadmin.Auth/login');
    // 刷新Token
    Route::post('auth/refresh', 'superadmin.Auth/refresh');
})->middleware('cors');

// 超级管理员路由（需要认证）
Route::group('api/v1/superadmin', function () {
    // 超级管理员认证
    Route::get('auth/me', 'superadmin.Auth/me');
    Route::post('auth/logout', 'superadmin.Auth/logout');
    
    // 企业管理（超管专用）
    // 注意：带参数的路由要放在不带参数的路由之前，避免路由匹配冲突
    Route::get('enterprises/:id/detail', 'superadmin.Enterprise/detail'); // 详细详情接口
    Route::get('enterprises/:id', 'superadmin.Enterprise/detail');
    Route::get('enterprises', 'superadmin.Enterprise/index');
    Route::post('enterprises', 'superadmin.Enterprise/create');
    Route::put('enterprises/:id', 'superadmin.Enterprise/update');
    Route::delete('enterprises/:id', 'superadmin.Enterprise/delete');
    Route::put('enterprises/:id/status', 'superadmin.Enterprise/toggleStatus');
    
    // 题库管理（超管专用，管理超管题库）
    Route::get('questions/:id', 'superadmin.Question/detail');
    Route::get('questions', 'superadmin.Question/index');
    Route::post('questions', 'superadmin.Question/create');
    Route::put('questions/:id', 'superadmin.Question/update');
    Route::delete('questions/:id', 'superadmin.Question/delete');
    Route::put('questions/:id/status', 'superadmin.Question/toggleStatus');
    Route::post('questions/batch-import', 'superadmin.Question/batchImport');
    
    // 全局定价管理（超管专用）
    Route::get('pricing', 'superadmin.Pricing/index');
    Route::put('pricing', 'superadmin.Pricing/update');
    Route::post('pricing/batch-update', 'superadmin.Pricing/batchUpdate');
    
    // AI服务商配置管理（超管专用）
    Route::get('ai-config', 'superadmin.AiConfig/index');
    Route::put('ai-config', 'superadmin.AiConfig/update');
    Route::post('ai-config/batch-update', 'superadmin.AiConfig/batchUpdate');
    Route::post('ai-config/query-balance', 'superadmin.AiConfig/queryBalance');
    Route::post('ai-config/query-all-balances', 'superadmin.AiConfig/queryAllBalances');
    
    // 数据库管理（超管专用）
    Route::get('database/info', 'superadmin.Database/info');
    Route::get('database/tables', 'superadmin.Database/tables');
    Route::get('database/view-table', 'superadmin.Database/viewTable');
    Route::post('database/export-table', 'superadmin.Database/exportTable');
    Route::post('database/clear-table', 'superadmin.Database/clearTable');
    Route::post('database/backup', 'superadmin.Database/backup');
    Route::get('database/backups', 'superadmin.Database/backups');
    Route::delete('database/backups/:id', 'superadmin.Database/delete');
    Route::post('database/backups/delete', 'superadmin.Database/delete');
    Route::get('database/download', 'superadmin.Database/download');
    Route::post('database/restore', 'superadmin.Database/restore');
    
    // 通用上传（超管）
    Route::post('upload/image', 'admin.Upload/image');

    // 系统设置（超管专用，子路径放前面避免被 settings 吞掉）
    Route::get('settings/fonts', 'superadmin.Settings/getFonts');
    Route::get('settings/poster', 'superadmin.Settings/getPosterConfig');
    Route::put('settings/poster', 'superadmin.Settings/updatePosterConfig');
    Route::put('settings/review-mode', 'superadmin.Settings/updateReviewMode');
    Route::get('settings', 'superadmin.Settings/index');
    Route::put('settings/system', 'superadmin.Settings/updateSystem');
    Route::put('settings/report-requires-payment', 'superadmin.Settings/updateReportRequiresPayment');
    Route::put('settings/notification', 'superadmin.Settings/updateNotification');
    Route::put('settings/prompts', 'superadmin.Settings/updatePrompts');
    Route::put('settings/credentials', 'superadmin.Settings/updateCredentials');
    
    // 测试用户（超管专用）
    Route::get('app-users/overview', 'superadmin.AppUser/overview');
    Route::get('app-users/:id', 'superadmin.AppUser/detail');
    Route::get('app-users', 'superadmin.AppUser/index');
   
    // 数据概览（超管专用，子路径放前面避免被 overview 吞掉）
    Route::get('overview/recent-dynamics', 'superadmin.Overview/recentDynamics');
    Route::get('overview/enterprise-ranking', 'superadmin.Overview/enterpriseRanking');
    Route::get('overview/test-trends', 'superadmin.Overview/testTrends');
    Route::get('overview', 'superadmin.Overview/index');
    
    // 财务管理（超管专用）
    Route::get('finance/overview', 'superadmin.Finance/overview');
    Route::get('finance/revenue-details', 'superadmin.Finance/revenueDetails');
    Route::get('finance/cost-details', 'superadmin.Finance/costDetails');
    Route::get('finance/recharge-records', 'superadmin.Finance/rechargeRecords');
    Route::get('finance/payment-records', 'superadmin.Finance/paymentRecords');
    Route::post('finance/export', 'superadmin.Finance/export');
    // 分销管理（超管专用 - 个人版全平台视图）
    Route::get('distribution/overview', 'superadmin.Distribution/overview');
    Route::get('distribution/bindings', 'superadmin.Distribution/bindings');
    Route::get('distribution/commissions', 'superadmin.Distribution/commissions');
    Route::get('distribution/withdrawals', 'superadmin.Distribution/withdrawals');
    Route::post('distribution/withdrawals/:id/approve', 'superadmin.Distribution/approveWithdrawal');
    Route::post('distribution/withdrawals/:id/reject', 'superadmin.Distribution/rejectWithdrawal');
    Route::get('distribution/settings', 'superadmin.Distribution/settings');
    Route::put('distribution/settings', 'superadmin.Distribution/updateSettings');
})->middleware(['cors', 'auth', 'superadmin']);

// ==================== 兼容旧版路由（保留，逐步废弃）====================
// 管理后台认证路由（不需要认证）- 兼容旧版
Route::group('api/v1', function () {
    // 管理员登录（兼容旧版）
    Route::post('auth/admin/login', 'admin.Auth/adminLogin');
    // 超级管理员登录（兼容旧版）
    Route::post('auth/superadmin/login', 'superadmin.Auth/login');
    // 刷新Token（兼容旧版）
    Route::post('auth/refresh', 'admin.Auth/refresh');
})->middleware('cors');

// 管理后台路由（需要认证）- 兼容旧版
Route::group('api/v1', function () {
    // 管理员认证（兼容旧版）
    Route::get('auth/me', 'admin.Auth/me');
    Route::post('auth/logout', 'admin.Auth/logout');
    
    // 仪表盘统计（兼容旧版）
    Route::get('dashboard', 'admin.Dashboard/index');
    
    // 通用上传（兼容旧版）
    Route::post('upload/image', 'admin.Upload/image');
    
    // 用户管理（兼容旧版）
    Route::get('users', 'admin.User/index');
    Route::get('users/:id', 'admin.User/detail');
    Route::post('users', 'admin.User/create');
    Route::put('users/:id', 'admin.User/update');
    Route::delete('users/:id', 'admin.User/delete');
    Route::put('users/:id/status', 'admin.User/toggleStatus');
    
    // 企业管理（超管，兼容旧版）
    // 注意：带参数的路由要放在不带参数的路由之前，避免路由匹配冲突
    Route::get('enterprises/:id/detail', 'superadmin.Enterprise/detail'); // 详细详情接口
    Route::get('enterprises/:id', 'superadmin.Enterprise/detail');
    Route::get('enterprises', 'superadmin.Enterprise/index');
    Route::post('enterprises', 'superadmin.Enterprise/create');
    Route::put('enterprises/:id', 'superadmin.Enterprise/update');
    Route::delete('enterprises/:id', 'superadmin.Enterprise/delete');
    Route::put('enterprises/:id/status', 'superadmin.Enterprise/toggleStatus');
    
    // 题库管理（兼容旧版）
    Route::get('questions/:id', 'admin.Question/detail');
    Route::get('questions', 'admin.Question/index');
    Route::post('questions', 'admin.Question/create');
    Route::put('questions/:id', 'admin.Question/update');
    Route::delete('questions/:id', 'admin.Question/delete');
    Route::put('questions/:id/status', 'admin.Question/toggleStatus');
    Route::post('questions/batch-import', 'admin.Question/batchImport');

    // 企业财务（兼容旧版）
    Route::get('finance/overview', 'admin.Finance/overview');
    Route::get('finance/records', 'admin.Finance/records');
    Route::post('finance/recharge-qrcode', 'admin.Finance/rechargeQrcode');
    Route::post('finance/recharge', 'admin.Finance/rechargeQrcode');
})->middleware(['cors', 'auth']);

// ==================== 兼容旧版路由（保留）====================
// 后台管理认证路由（不需要认证）- 兼容旧版
Route::group('api/admin', function () {
    // 管理员登录
    Route::post('auth/login', 'admin.Auth/login');
    Route::post('auth/refresh', 'admin.Auth/refresh');
})->middleware('cors');

// 后台管理路由（需要认证）- 兼容旧版
Route::group('api/admin', function () {
    // 管理员认证
    Route::get('auth/me', 'admin.Auth/me');
    Route::post('auth/logout', 'admin.Auth/logout');
    
    // 仪表盘统计
    Route::get('dashboard', 'admin.Dashboard/index');
    
    // 通用上传
    Route::post('upload/image', 'admin.Upload/image');
    
    // 用户管理
    Route::get('users', 'admin.User/index');
    Route::get('users/:id', 'admin.User/detail');
    Route::post('users', 'admin.User/create');
    Route::put('users/:id', 'admin.User/update');
    Route::delete('users/:id', 'admin.User/delete');
    Route::put('users/:id/status', 'admin.User/toggleStatus');

    // 企业财务
    Route::get('finance/overview', 'admin.Finance/overview');
    Route::get('finance/records', 'admin.Finance/records');
    Route::post('finance/recharge-qrcode', 'admin.Finance/rechargeQrcode');
    Route::post('finance/recharge', 'admin.Finance/rechargeQrcode');
})->middleware(['cors', 'auth']);

// ==================== 兼容旧版路由（保留）====================
// 后台管理认证路由（不需要认证）- 兼容旧版
Route::group('api/admin', function () {
    // 管理员登录
    Route::post('auth/login', 'admin.Auth/login');
    Route::post('auth/refresh', 'admin.Auth/refresh');
})->middleware('cors');

// 后台管理路由（需要认证）- 兼容旧版
Route::group('api/admin', function () {
    // 管理员认证
    Route::get('auth/me', 'admin.Auth/me');
    Route::post('auth/logout', 'admin.Auth/logout');
    
    // 仪表盘统计
    Route::get('dashboard', 'admin.Dashboard/index');
    
    // 通用上传
    Route::post('upload/image', 'admin.Upload/image');
    
    // 用户管理
    Route::get('users', 'admin.User/index');
    Route::get('users/:id', 'admin.User/detail');
    Route::post('users', 'admin.User/create');
    Route::put('users/:id', 'admin.User/update');
    Route::delete('users/:id', 'admin.User/delete');
    Route::put('users/:id/status', 'admin.User/toggleStatus');

    // 企业财务
    Route::get('finance/overview', 'admin.Finance/overview');
    Route::get('finance/records', 'admin.Finance/records');
    Route::post('finance/recharge-qrcode', 'admin.Finance/rechargeQrcode');
    Route::post('finance/recharge', 'admin.Finance/rechargeQrcode');
})->middleware(['cors', 'auth']);

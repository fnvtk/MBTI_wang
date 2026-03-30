<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <div v-if="!embedded" class="page-header">
      <div class="header-left">
        <h2>企业管理</h2>
        <p class="subtitle">共{{ total }}家企业·活跃{{ activeCount }}家</p>
      </div>
      <div class="header-actions">
        <el-button variant="outline" @click="handleRefresh">
          <el-icon class="mr-1"><Refresh /></el-icon>刷新
        </el-button>
        <el-button type="primary" color="#3b82f6" @click="showCreateDialog = true">
          <el-icon class="mr-1"><Plus /></el-icon>新建企业
        </el-button>
      </div>
    </div>

    <div class="content-card">
      <div class="toolbar">
        <el-input
          v-model="searchTerm"
          placeholder="搜索企业名称、联系人、电话..."
          clearable
          class="search-input"
          @clear="handleSearch"
          @keyup.enter="handleSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>

        <div class="toolbar-right">
          <div class="filter-group">
            <div
              v-for="item in statusOptions"
              :key="item.value"
              :class="['filter-item', { active: statusFilter === item.value }]"
              @click="statusFilter = item.value; handleStatusFilter()"
            >
              {{ item.label }}
            </div>
          </div>
          <!-- 嵌入「企业管理」页签时不展示顶部 page-header，操作入口放到工具栏 -->
          <div v-if="embedded" class="toolbar-embedded-actions">
            <el-button variant="outline" @click="handleRefresh">
              <el-icon class="mr-1"><Refresh /></el-icon>刷新
            </el-button>
            <el-button type="primary" color="#3b82f6" @click="showCreateDialog = true">
              <el-icon class="mr-1"><Plus /></el-icon>新建企业
            </el-button>
          </div>
        </div>
      </div>

      <el-table :data="enterprises" style="width: 100%" v-loading="loading" class="custom-table">
        <el-table-column label="企业信息" min-width="200">
          <template #default="{ row }">
            <div class="enterprise-info-cell">
              <el-avatar :size="32" class="enterprise-avatar">
                {{ (row.name || '?')[0].toUpperCase() }}
              </el-avatar>
              <div class="enterprise-details">
                <div class="enterprise-name">{{ row.name }}</div>
                <div class="enterprise-code">{{ row.code || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="联系方式" min-width="180">
          <template #default="{ row }">
            <div class="contact-cell">
              <div class="contact-name">{{ row.contactName || '-' }}</div>
              <div class="contact-phone">{{ row.contactPhone || '-' }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag 
              :type="row.status === 'operating' ? 'success' : row.status === 'trial' ? 'warning' : 'info'" 
              size="small"
            >
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="账户余额" width="120" align="right">
          <template #default="{ row }">
            <span class="balance-cell">¥{{ (row.balance || 0).toLocaleString() }}</span>
          </template>
        </el-table-column>

        <el-table-column label="测试用量" width="120" align="right">
          <template #default="{ row }">
            <span class="test-usage-cell">{{ row.testUsage || 0 }}</span>
          </template>
        </el-table-column>

        <el-table-column label="用户数" width="100" align="right">
          <template #default="{ row }">
            <span class="user-count">{{ row.userCount || 0 }}</span>
          </template>
        </el-table-column>

        <el-table-column label="超管授权" min-width="200">
          <template #default="{ row }">
            <div class="perm-tags">
              <el-tag v-for="p in permItems" :key="p.key" size="small"
                :type="permCeilingVal(row, p.key) !== false ? 'success' : 'info'"
                :effect="permCeilingVal(row, p.key) !== false ? 'light' : 'plain'"
                class="perm-tag"
              >{{ p.label }}</el-tag>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="176" fixed="right">
          <template #default="{ row }">
            <div class="action-buttons">
              <el-tooltip content="邀请小程序码" placement="top">
                <el-button link class="action-invite" @click="openInviteQrcodeDialog(row)">
                  <el-icon><Picture /></el-icon>
                </el-button>
              </el-tooltip>
              <el-button link @click="handleView(row)"><el-icon><View /></el-icon></el-button>
              <el-button link @click="handleEdit(row)"><el-icon><Edit /></el-icon></el-button>
              <el-button link type="danger" @click="handleDelete(row)"><el-icon><Delete /></el-icon></el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <div class="empty-state" v-if="enterprises.length === 0 && !loading">
        <span>暂无企业数据</span>
      </div>

      <div class="pagination-container" v-if="total > 0">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="prev, pager, next"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 创建企业对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      width="680px"
      class="custom-dialog"
      :show-close="true"
      align-center
    >
      <template #header>
        <div class="dialog-header">
          <h3 class="dialog-title">创建新企业</h3>
          <p class="dialog-subtitle">设置企业基本信息、管理员账号及联系方式</p>
        </div>
      </template>

      <el-form :model="newEnterprise" label-position="top" class="custom-form optimized-form">
        <el-row :gutter="24">
          <!-- 左侧：账号与基础信息 -->
          <el-col :span="12">
            <div class="form-section-title">基础与账号</div>
            <el-form-item label="企业名称" required>
              <el-input v-model="newEnterprise.name" placeholder="请输入企业名称" />
            </el-form-item>
            
            <el-form-item label="企业代码">
              <el-input v-model="newEnterprise.code" placeholder="建议输入简写或拼音" />
            </el-form-item>

            <el-form-item label="管理员用户名" required>
              <el-input v-model="newEnterprise.adminUsername" placeholder="登录后台使用" />
            </el-form-item>

            <el-form-item label="管理员密码" required>
              <el-input 
                v-model="newEnterprise.adminPassword" 
                type="password" 
                placeholder="请输入密码"
                show-password
              />
            </el-form-item>

            <el-form-item label="确认密码" required>
              <el-input 
                v-model="newEnterprise.adminPasswordConfirm" 
                type="password" 
                placeholder="请再次输入"
                show-password
              />
            </el-form-item>
          </el-col>

          <!-- 右侧：联系信息与状态 -->
          <el-col :span="12">
            <div class="form-section-title">联系信息与状态</div>
            <el-form-item label="联系人姓名">
              <el-input v-model="newEnterprise.contactName" placeholder="姓名" />
            </el-form-item>

            <el-form-item label="联系人电话">
              <el-input v-model="newEnterprise.contactPhone" placeholder="手机或座机" />
            </el-form-item>

            <el-form-item label="联系人邮箱">
              <el-input v-model="newEnterprise.contactEmail" placeholder="example@mail.com" />
            </el-form-item>

            <el-form-item label="企业状态">
              <el-select v-model="newEnterprise.status" class="w-full" placeholder="请选择状态">
                <el-option label="运营中" value="operating" />
                <el-option label="试用" value="trial" />
                <el-option label="已停用" value="disabled" />
              </el-select>
            </el-form-item>

            <el-form-item 
              label="试用到期时间" 
              v-if="newEnterprise.status === 'trial'"
              required
            >
              <el-date-picker
                v-model="newEnterprise.trialExpireAt"
                type="datetime"
                placeholder="选择时间"
                format="YYYY-MM-DD HH:mm"
                value-format="YYYY-MM-DD HH:mm:ss"
                class="w-full"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="功能授权（超管）" class="mt-12">
          <p class="perm-form-hint">决定企业管理员能否在后台为终端用户打开该项；关闭后管理端不会出现对应开关。</p>
          <div class="perm-switch-group">
            <div class="perm-switch-item" v-for="p in permItems" :key="p.key">
              <span class="perm-label">{{ p.label }}</span>
              <el-switch v-model="newEnterprise.permissions[p.key]" />
            </div>
          </div>
        </el-form-item>
      </el-form>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showCreateDialog = false" class="cancel-btn">取消</el-button>
          <el-button type="primary" color="#ef4444" @click="handleCreateEnterprise" class="submit-btn" :loading="creating">
            立即创建
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 编辑企业对话框 -->
    <el-dialog
      v-model="showEditDialog"
      title="编辑企业"
      width="440px"
      class="custom-dialog"
      :show-close="true"
      align-center
      @open="handleEditDialogOpen"
    >
      <template #header>
        <div class="dialog-header">
          <h3 class="dialog-title">编辑企业</h3>
          <p class="dialog-subtitle">修改企业基本信息和配置</p>
        </div>
      </template>

      <el-form :model="editEnterprise" :key="`edit-form-${currentEditId || Date.now()}`" label-position="top" class="custom-form">
        <el-form-item label="企业名称" required>
          <el-input v-model="editEnterprise.name" placeholder="请输入企业名称" />
        </el-form-item>
        
        <el-form-item label="企业代码">
          <el-input v-model="editEnterprise.code" placeholder="请输入企业代码（可选）" />
        </el-form-item>

        <el-form-item label="联系人姓名">
          <el-input v-model="editEnterprise.contactName" placeholder="请输入联系人姓名" />
        </el-form-item>

        <el-form-item label="联系人电话">
          <el-input v-model="editEnterprise.contactPhone" placeholder="请输入联系人电话" />
        </el-form-item>

        <el-form-item label="联系人邮箱">
          <el-input v-model="editEnterprise.contactEmail" placeholder="请输入联系人邮箱" />
        </el-form-item>

        <el-form-item label="状态">
          <el-select v-model="editEnterprise.status" class="w-full" placeholder="请选择状态">
            <el-option label="运营中" value="operating" />
            <el-option label="试用" value="trial" />
            <el-option label="已停用" value="disabled" />
          </el-select>
        </el-form-item>

        <el-form-item 
          label="试用到期时间" 
          v-if="editEnterprise.status === 'trial'"
          required
        >
          <el-date-picker
            v-model="editEnterprise.trialExpireAt"
            type="datetime"
            placeholder="请选择试用到期时间"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            class="w-full"
          />
        </el-form-item>

        <el-form-item label="功能授权（超管）">
          <p class="perm-form-hint">保存后将收紧企业管理员可调范围；对已关闭项，终端与企业后台同步关闭。</p>
          <div class="perm-switch-group">
            <div class="perm-switch-item" v-for="p in permItems" :key="p.key">
              <span class="perm-label">{{ p.label }}</span>
              <el-switch v-model="editEnterprise.permissions[p.key]" />
            </div>
          </div>
        </el-form-item>
      </el-form>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showEditDialog = false" class="cancel-btn">取消</el-button>
          <el-button type="primary" color="#ef4444" @click="handleSaveEdit" class="submit-btn">
            保存修改
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 查看企业详情对话框 -->
    <el-dialog
      v-model="showViewDialog"
      title="企业详情"
      width="min(1180px, 96vw)"
      class="custom-dialog detail-dialog enterprise-detail-dialog"
      top="3vh"
      :show-close="true"
      align-center
    >
      <template #header>
        <div class="dialog-header">
          <h3 class="dialog-title">企业详情</h3>
          <p class="dialog-subtitle">查看企业完整信息和统计数据</p>
        </div>
      </template>

      <div v-loading="viewLoading" class="enterprise-detail-content">
        <div v-if="viewEnterpriseData" class="ud-wrap">
          <!-- 左侧：企业概览（与用户详情弹窗 ud-side 一致） -->
          <aside class="ud-side">
            <div class="ud-avatar-block">
              <div class="ud-avatar-letter">{{ enterpriseAvatarLetter }}</div>
              <div class="ud-name">{{ viewEnterpriseData.name || '未命名企业' }}</div>
            </div>
            <div class="ud-meta">
              <div class="ud-meta-row">
                <el-icon><Key /></el-icon>
                <span>ID {{ viewDetailEnterpriseId ?? '-' }}</span>
              </div>
              <div class="ud-meta-row" v-if="viewEnterpriseData.contactName">
                <el-icon><Postcard /></el-icon>
                <span>{{ viewEnterpriseData.contactName }}</span>
              </div>
              <div class="ud-meta-row" v-if="viewEnterpriseData.contactPhone">
                <el-icon><Phone /></el-icon>
                <span>{{ viewEnterpriseData.contactPhone }}</span>
              </div>
              <div class="ud-meta-row">
                <el-icon><Calendar /></el-icon>
                <span>{{ formatDetailTime(viewEnterpriseData.createdAt) }}</span>
              </div>
            </div>
            <div class="ud-stat-icons">
              <el-tooltip content="小程序用户" placement="top">
                <div class="ud-stat-ic">
                  <el-icon><User /></el-icon>{{ viewEnterpriseData.wechatUsersTotal ?? viewEnterpriseData.wechatUserCount ?? 0 }}
                </div>
              </el-tooltip>
              <el-tooltip content="测试记录" placement="top">
                <div class="ud-stat-ic">
                  <el-icon><TrendCharts /></el-icon>{{ viewEnterpriseData.miniprogramTestResultsTotal ?? 0 }}
                </div>
              </el-tooltip>
              <el-tooltip content="订单数" placement="top">
                <div class="ud-stat-ic">
                  <el-icon><Document /></el-icon>{{ viewEnterpriseData.orderStats?.totalCount ?? 0 }}
                </div>
              </el-tooltip>
              <el-tooltip content="账户余额（元）" placement="top">
                <div class="ud-stat-ic ud-stat-ic--money">
                  <el-icon><Wallet /></el-icon>{{ (viewEnterpriseData.balance || 0).toLocaleString() }}
                </div>
              </el-tooltip>
            </div>
            <div class="ud-tags" v-if="enterpriseDimensionTags.length">
              <div class="ud-tags-title">维度标签</div>
              <el-tag
                v-for="t in enterpriseDimensionTags"
                :key="t"
                size="small"
                class="ud-tag"
                effect="plain"
                type="primary"
              >{{ t }}</el-tag>
            </div>
          </aside>

          <!-- 右侧：详细页签（与用户详情 ud-main / ud-tabs 一致） -->
          <main class="ud-main">
            <el-tabs v-model="enterpriseDetailTab" class="ud-tabs">
              <!-- 经营概览：对齐用户详情「分析结果」版式（三卡 + 双栏卡片） -->
              <el-tab-pane label="经营概览" name="overview">
                <div class="ud-scroll ed-overview-scroll">
                  <div class="ud-radar-row">
                    <div class="ud-radar-cell ed-kpi-cell">
                      <div class="ud-radar-title"><el-icon><User /></el-icon> 小程序用户</div>
                      <div class="ed-kpi-value">{{ viewEnterpriseData.wechatUsersTotal ?? viewEnterpriseData.wechatUserCount ?? 0 }}</div>
                      <div class="ed-kpi-sub">登记用户数</div>
                    </div>
                    <div class="ud-radar-cell ed-kpi-cell">
                      <div class="ud-radar-title"><el-icon><TrendCharts /></el-icon> 测试记录</div>
                      <div class="ed-kpi-value">{{ viewEnterpriseData.miniprogramTestResultsTotal ?? 0 }}</div>
                      <div class="ed-kpi-sub">小程序测评条数</div>
                    </div>
                    <div class="ud-radar-cell ed-kpi-cell">
                      <div class="ud-radar-title"><el-icon><Document /></el-icon> 订单</div>
                      <div class="ed-kpi-value">{{ viewEnterpriseData.orderStats?.totalCount ?? 0 }}</div>
                      <div class="ed-kpi-sub">累计订单</div>
                    </div>
                  </div>

                  <div class="ud-row2">
                    <div class="ud-card">
                      <div class="ud-card-h"><el-icon><Document /></el-icon> 订单与收入</div>
                      <template v-if="(viewEnterpriseData.orderStats?.totalCount ?? 0) > 0">
                        <div class="ud-roles">
                          <div class="ud-role">
                            <span class="ud-role-n">已支付</span>
                            <el-progress :percentage="orderPaidProgressPct" :stroke-width="6" :show-text="false" color="#7c3aed" />
                            <span class="ud-role-p">{{ viewEnterpriseData.orderStats?.paidCount ?? 0 }}</span>
                          </div>
                        </div>
                        <div class="ed-order-sum">
                          已支付金额
                          <strong>¥{{ fenToYuan(viewEnterpriseData.orderStats?.paidAmountFen) }}</strong>
                        </div>
                      </template>
                      <div v-else class="ud-muted">暂无订单数据</div>
                    </div>
                    <div class="ud-card ud-grow">
                      <div class="ud-card-h"><el-icon><DataAnalysis /></el-icon> 近30天埋点</div>
                      <div class="ud-roles">
                        <div class="ud-role">
                          <span class="ud-role-n">事件</span>
                          <el-progress :percentage="analyticsEventProgressPct" :stroke-width="6" :show-text="false" color="#7c3aed" />
                          <span class="ud-role-p">{{ viewEnterpriseData.analyticsStats?.eventTotal ?? 0 }}</span>
                        </div>
                        <div class="ud-role">
                          <span class="ud-role-n">page_view</span>
                          <el-progress :percentage="analyticsPvProgressPct" :stroke-width="6" :show-text="false" color="#a855f7" />
                          <span class="ud-role-p">{{ viewEnterpriseData.analyticsStats?.pageViewCount ?? 0 }}</span>
                        </div>
                      </div>
                      <p v-if="viewEnterpriseData.analyticsStats?.hint" class="stats-hint ed-stats-hint">{{ viewEnterpriseData.analyticsStats.hint }}</p>
                    </div>
                  </div>

                  <div v-if="(viewEnterpriseData.adminAccounts?.length ?? 0) > 0" class="ud-card ed-admin-summary">
                    <div class="ud-card-h"><el-icon><User /></el-icon> 管理员</div>
                    <div class="ed-admin-chips">
                      <el-tag v-for="a in (viewEnterpriseData.adminAccounts || []).slice(0, 6)" :key="a.id" size="small" type="info" effect="plain">
                        {{ a.username }}
                      </el-tag>
                      <span v-if="(viewEnterpriseData.adminAccounts?.length ?? 0) > 6" class="ud-muted">…共 {{ viewEnterpriseData.adminAccounts?.length }} 人</span>
                    </div>
                  </div>
                </div>
              </el-tab-pane>

              <!-- 用户列表 -->
              <el-tab-pane label="小程序用户">
                <div class="tab-content">
                  <el-table
                    :data="viewEnterpriseUsers"
                    style="width: 100%"
                    size="small"
                    v-if="(viewEnterpriseData.wechatUsersTotal ?? viewEnterpriseData.wechatUserCount ?? 0) > 0"
                  >
                    <el-table-column prop="id" label="ID" width="64" />
                    <el-table-column label="昵称" min-width="100">
                      <template #default="{ row }">
                        <span>{{ row.nickname || row.username || '-' }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="phone" label="手机" width="110" />
                    <el-table-column label="注册时间" width="140">
                      <template #default="{ row }">
                        {{ formatDetailTime(row.createdAt) }}
                      </template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="60">
                      <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                          {{ row.status === 1 ? '开' : '关' }}
                        </el-tag>
                      </template>
                    </el-table-column>
                  </el-table>
                  <el-pagination
                    v-if="(viewEnterpriseData.wechatUsersTotal ?? viewEnterpriseData.wechatUserCount ?? 0) > 0"
                    class="detail-pagination"
                    v-model:current-page="detailWechatPage"
                    v-model:page-size="detailWechatPageSize"
                    :total="viewEnterpriseData.wechatUsersTotal ?? viewEnterpriseData.wechatUserCount ?? 0"
                    :page-sizes="[10, 20, 50]"
                    layout="total, prev, pager, next"
                    small
                    @current-change="fetchEnterpriseDetail"
                    @size-change="onDetailWechatSizeChange"
                  />
                  <div v-else class="empty-placeholder-small">
                    <el-icon class="empty-icon"><User /></el-icon>
                    <p class="empty-text">暂无用户数据</p>
                  </div>
                </div>
              </el-tab-pane>

              <!-- 测试记录 -->
              <el-tab-pane label="测试记录">
                <div class="tab-content">
                  <el-table
                    :data="viewEnterpriseTests"
                    style="width: 100%"
                    size="small"
                    v-if="(viewEnterpriseData.miniprogramTestResultsTotal ?? 0) > 0"
                  >
                    <el-table-column prop="testType" label="类型" width="70" />
                    <el-table-column label="测试结果" min-width="150" show-overflow-tooltip>
                      <template #default="{ row }">
                        {{ row.resultSummary || '—' }}
                      </template>
                    </el-table-column>
                    <el-table-column label="用户" min-width="100" show-overflow-tooltip>
                      <template #default="{ row }">
                        {{ row.wechatNickname || row.username || '—' }}
                      </template>
                    </el-table-column>
                    <el-table-column label="测试时间" width="140">
                      <template #default="{ row }">
                        {{ formatDetailTime(row.createdAt) }}
                      </template>
                    </el-table-column>
                  </el-table>
                  <el-pagination
                    v-if="(viewEnterpriseData.miniprogramTestResultsTotal ?? 0) > 0"
                    class="detail-pagination"
                    v-model:current-page="detailTestPage"
                    v-model:page-size="detailTestPageSize"
                    :total="viewEnterpriseData.miniprogramTestResultsTotal ?? 0"
                    :page-sizes="[10, 20, 50]"
                    layout="total, prev, pager, next"
                    small
                    @current-change="fetchEnterpriseDetail"
                    @size-change="onDetailTestSizeChange"
                  />
                  <div v-else class="empty-placeholder-small">
                    <el-icon class="empty-icon"><TrendCharts /></el-icon>
                    <p class="empty-text">暂无测试数据</p>
                  </div>
                </div>
              </el-tab-pane>

              <!-- 订单统计 -->
              <el-tab-pane label="订单与统计">
                <div class="tab-content">
                  <div class="stats-cards">
                    <div class="stats-mini-card">
                      <div class="card-label">订单总数</div>
                      <div class="card-value">{{ viewEnterpriseData.orderStats?.totalCount ?? 0 }}</div>
                    </div>
                    <div class="stats-mini-card">
                      <div class="card-label">已支付</div>
                      <div class="card-value text-success">{{ viewEnterpriseData.orderStats?.paidCount ?? 0 }}</div>
                    </div>
                    <div class="stats-mini-card">
                      <div class="card-label">总金额</div>
                      <div class="card-value">¥{{ fenToYuan(viewEnterpriseData.orderStats?.paidAmountFen) }}</div>
                    </div>
                  </div>

                  <div v-if="(viewEnterpriseData.recentOrdersTotal ?? 0) > 0" class="recent-orders-block">
                    <h5 class="subsection-title">最近订单</h5>
                    <el-table :data="viewEnterpriseData.recentOrders" size="small" style="width: 100%">
                      <el-table-column prop="orderNo" label="订单号" min-width="150" show-overflow-tooltip />
                      <el-table-column label="状态" width="70">
                        <template #default="{ row }">
                          <el-tag size="small" :type="row.status === 'paid' ? 'success' : 'info'">
                            {{ orderStatusLabel(row.status) }}
                          </el-tag>
                        </template>
                      </el-table-column>
                      <el-table-column label="金额" width="70" align="right">
                        <template #default="{ row }">
                          ¥{{ fenToYuan(row.amount) }}
                        </template>
                      </el-table-column>
                      <el-table-column label="日期" width="140">
                        <template #default="{ row }">
                          {{ formatDetailTime(row.createdAt) }}
                        </template>
                      </el-table-column>
                    </el-table>
                    <el-pagination
                      class="detail-pagination"
                      v-model:current-page="detailOrderPage"
                      v-model:page-size="detailOrderPageSize"
                      :total="viewEnterpriseData.recentOrdersTotal ?? 0"
                      layout="total, prev, pager, next"
                      small
                      @current-change="fetchEnterpriseDetail"
                      @size-change="onDetailOrderSizeChange"
                    />
                  </div>
                </div>
              </el-tab-pane>

              <!-- 埋点数据 -->
              <el-tab-pane label="埋点分析">
                <div class="tab-content">
                  <div class="analytics-header">
                    <div class="analytics-main-stat">
                      <span class="label">30天事件总数</span>
                      <span class="value">{{ viewEnterpriseData.analyticsStats?.eventTotal ?? 0 }}</span>
                    </div>
                    <div class="analytics-main-stat">
                      <span class="label">页面曝光 (PV)</span>
                      <span class="value">{{ viewEnterpriseData.analyticsStats?.pageViewCount ?? 0 }}</span>
                    </div>
                  </div>
                  <p v-if="viewEnterpriseData.analyticsStats?.hint" class="stats-hint">{{ viewEnterpriseData.analyticsStats.hint }}</p>
                  
                  <div v-if="viewEnterpriseData.analyticsStats?.byEvent?.length" class="event-dist">
                    <h5 class="subsection-title">事件分布</h5>
                    <el-table :data="viewEnterpriseData.analyticsStats.byEvent" size="small" style="width: 100%" max-height="300">
                      <el-table-column prop="eventName" label="事件名" min-width="160" />
                      <el-table-column prop="cnt" label="次数" width="80" align="right" />
                    </el-table>
                  </div>
                </div>
              </el-tab-pane>

              <!-- 管理员账号 -->
              <el-tab-pane label="管理员">
                <div class="tab-content">
                  <el-table :data="viewEnterpriseData.adminAccounts || []" style="width: 100%" size="small" v-if="viewEnterpriseData.adminAccounts?.length">
                    <el-table-column prop="username" label="用户名" />
                    <el-table-column prop="email" label="邮箱" min-width="120" show-overflow-tooltip />
                    <el-table-column prop="phone" label="电话" width="110" />
                    <el-table-column prop="status" label="状态" width="60">
                      <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                          {{ row.status === 1 ? '开' : '关' }}
                        </el-tag>
                      </template>
                    </el-table-column>
                  </el-table>
                  <div v-else class="empty-placeholder-small">
                    <el-icon class="empty-icon"><User /></el-icon>
                    <p class="empty-text">暂无管理员账户</p>
                  </div>
                </div>
              </el-tab-pane>
            </el-tabs>
          </main>
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showViewDialog = false" class="cancel-btn">关闭</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 邀请小程序码（按当前行企业 ID） -->
    <el-dialog
      v-model="showInviteQrcodeDialog"
      width="440px"
      class="custom-dialog invite-qrcode-dialog"
      align-center
      destroy-on-close
      @closed="resetInviteQrcodeDialog"
    >
      <template #header>
        <div class="dialog-header">
          <h3 class="dialog-title">邀请小程序码</h3>
          <p class="dialog-subtitle">
            {{ inviteDialogEnterprise?.name || '企业' }} · 企业版进企业测评，个人版进小程序首页
          </p>
        </div>
      </template>
      <div v-loading="inviteQrcodeLoading" class="invite-qrcode-dialog-body">
        <div
          v-if="inviteQrcodeEnterpriseB64 || inviteQrcodePersonalB64"
          class="invite-qrcode-pair"
        >
          <div v-if="inviteQrcodeEnterpriseB64" class="invite-qrcode-card">
            <span class="invite-qrcode-label">企业版</span>
            <img :src="inviteQrcodeEnterpriseB64" alt="企业版太阳码" class="invite-qrcode-img" />
          </div>
          <div v-if="inviteQrcodePersonalB64" class="invite-qrcode-card">
            <span class="invite-qrcode-label">个人版</span>
            <img :src="inviteQrcodePersonalB64" alt="个人版太阳码" class="invite-qrcode-img" />
          </div>
        </div>
        <p v-else class="invite-qrcode-placeholder">{{ inviteQrcodeError || '加载中…' }}</p>
      </div>
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showInviteQrcodeDialog = false" class="cancel-btn">关闭</el-button>
          <el-button
            type="primary"
            color="#3b82f6"
            :loading="inviteQrcodeLoading"
            @click="loadInviteQrcodeForDialog"
          >
            刷新
          </el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import {
  Plus,
  Refresh,
  Search,
  View,
  Edit,
  Delete,
  User,
  TrendCharts,
  Key,
  Phone,
  Calendar,
  DataAnalysis,
  Document,
  Wallet,
  Postcard,
  Picture
} from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { request } from '@/utils/request'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const loading = ref(false)
const total = ref(0)
const activeCount = ref(0)
const currentPage = ref(1)
const pageSize = 20
const searchTerm = ref('')
const statusFilter = ref('')
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const showViewDialog = ref(false)
const currentEditId = ref<number | null>(null)
const pendingEditData = ref<any>(null)
const viewEnterpriseData = ref<any>(null)
const viewEnterpriseUsers = ref<any[]>([])
const viewEnterpriseTests = ref<any[]>([])
const viewLoading = ref(false)
/** 详情弹窗当前企业 ID，翻页时复用 */
const viewDetailEnterpriseId = ref<number | null>(null)
const detailWechatPage = ref(1)
const detailWechatPageSize = ref(10)
const detailTestPage = ref(1)
const detailTestPageSize = ref(10)
const detailOrderPage = ref(1)
const detailOrderPageSize = ref(10)
/** 企业详情弹窗当前 Tab（对齐用户详情：先总览再明细） */
const enterpriseDetailTab = ref('overview')
const creating = ref(false)

/** 邀请小程序码（企业列表操作列） */
const showInviteQrcodeDialog = ref(false)
const inviteDialogEnterprise = ref<{ id: number; name: string } | null>(null)
const inviteQrcodeLoading = ref(false)
const inviteQrcodeEnterpriseB64 = ref('')
const inviteQrcodePersonalB64 = ref('')
const inviteQrcodeError = ref('')

const resetInviteQrcodeDialog = () => {
  inviteDialogEnterprise.value = null
  inviteQrcodeEnterpriseB64.value = ''
  inviteQrcodePersonalB64.value = ''
  inviteQrcodeError.value = ''
}

const loadInviteQrcodeForDialog = async () => {
  const ent = inviteDialogEnterprise.value
  if (!ent?.id) return
  inviteQrcodeLoading.value = true
  inviteQrcodeError.value = ''
  try {
    const res: any = await request.get('/superadmin/invite/qrcode', {
      params: { enterpriseId: ent.id }
    })
    const d = res?.data
    const qEnt = d?.enterprise?.qrcode ?? d?.qrcode
    const qPer = d?.personal?.qrcode
    inviteQrcodeEnterpriseB64.value = typeof qEnt === 'string' && qEnt ? qEnt : ''
    inviteQrcodePersonalB64.value = typeof qPer === 'string' && qPer ? qPer : ''
    if (!inviteQrcodeEnterpriseB64.value && !inviteQrcodePersonalB64.value) {
      inviteQrcodeError.value = res?.message || res?.msg || '生成失败，请检查小程序配置'
      ElMessage.error(inviteQrcodeError.value)
    }
  } catch (e: any) {
    inviteQrcodeError.value = e?.message || '加载失败'
    ElMessage.error(inviteQrcodeError.value)
  } finally {
    inviteQrcodeLoading.value = false
  }
}

const openInviteQrcodeDialog = (row: any) => {
  inviteDialogEnterprise.value = {
    id: Number(row.id),
    name: row.name ? String(row.name) : `企业#${row.id}`
  }
  inviteQrcodeEnterpriseB64.value = ''
  inviteQrcodePersonalB64.value = ''
  inviteQrcodeError.value = ''
  showInviteQrcodeDialog.value = true
  nextTick(() => {
    loadInviteQrcodeForDialog()
  })
}

/** 企业侧栏头像首字 */
const enterpriseAvatarLetter = computed(() => {
  const n = viewEnterpriseData.value?.name
  if (!n || typeof n !== 'string') return '?'
  const t = n.trim()
  return t ? t[0].toUpperCase() : '?'
})

/** 侧栏「维度标签」（与用户详情 ud-tags 同级展示习惯） */
const enterpriseDimensionTags = computed(() => {
  const d = viewEnterpriseData.value
  if (!d) return []
  const tags: string[] = []
  tags.push(`状态·${getStatusLabel(d.status)}`)
  if (d.code) tags.push(`代码·${d.code}`)
  tags.push(`后台账号·${d.userCount ?? 0}人`)
  if (d.contactEmail) tags.push('已登记邮箱')
  const bal = Number(d.balance)
  if (Number.isFinite(bal) && bal > 0) tags.push('余额>0')
  if (d.status === 'trial') tags.push('试用企业')
  return tags
})

/** 订单已支付占比（用于进度条） */
const orderPaidProgressPct = computed(() => {
  const o = viewEnterpriseData.value?.orderStats
  if (!o) return 0
  const t = Number(o.totalCount) || 0
  if (t <= 0) return 0
  return Math.min(100, Math.round(((Number(o.paidCount) || 0) / t) * 100))
})

/** 埋点两项进度条刻度（取较大者为 100%） */
const analyticsProgressBase = computed(() => {
  const a = viewEnterpriseData.value?.analyticsStats
  if (!a) return 1
  return Math.max(Number(a.eventTotal) || 0, Number(a.pageViewCount) || 0, 1)
})

const analyticsEventProgressPct = computed(() => {
  const a = viewEnterpriseData.value?.analyticsStats
  const n = Number(a?.eventTotal) || 0
  return Math.min(100, Math.round((n / analyticsProgressBase.value) * 100))
})

const analyticsPvProgressPct = computed(() => {
  const a = viewEnterpriseData.value?.analyticsStats
  const n = Number(a?.pageViewCount) || 0
  return Math.min(100, Math.round((n / analyticsProgressBase.value) * 100))
})

const statusOptions = [
  { label: '全部', value: '' },
  { label: '运营中', value: 'operating' },
  { label: '试用', value: 'trial' },
  { label: '已停用', value: 'disabled' }
]

const permItems = [
  { key: 'face', label: '人脸分析' },
  { key: 'mbti', label: 'MBTI' },
  { key: 'pdp', label: 'PDP' },
  { key: 'disc', label: 'DISC' },
  { key: 'distribution', label: '分销' },
]

const defaultPermissions = () => ({ face: true, mbti: true, pdp: true, disc: true, distribution: true })

/** 列表/展示：超管授权上限（兼容未返回 permissionsCeiling 的旧接口） */
const permCeilingVal = (row: Record<string, any>, key: string) => {
  const c = row.permissionsCeiling
  if (c && typeof c === 'object' && key in c) return c[key]
  const p = row.permissions
  if (p && typeof p === 'object' && key in p) return p[key]
  return true
}

const enterprises = ref<any[]>([])

const newEnterprise = reactive({
  name: '',
  code: '',
  adminUsername: '',
  adminPassword: '',
  adminPasswordConfirm: '',
  contactName: '',
  contactPhone: '',
  contactEmail: '',
  status: 'operating',
  trialExpireAt: '' as string,
  permissions: defaultPermissions() as Record<string, boolean>
})

const editEnterprise = reactive({
  name: '',
  code: '',
  contactName: '',
  contactPhone: '',
  contactEmail: '',
  status: 'operating',
  trialExpireAt: '',
  permissions: defaultPermissions() as Record<string, boolean>
})

const getStatusLabel = (status: string) => {
  const statusMap: Record<string, string> = {
    'operating': '运营中',
    'trial': '试用',
    'disabled': '已停用'
  }
  return statusMap[status] || '未知'
}

/** 详情弹窗：后端多为 Unix 秒 */
function formatDetailTime(val: unknown): string {
  if (val == null || val === '') return '-'
  if (typeof val === 'number' && Number.isFinite(val)) {
    return new Date(val * 1000).toLocaleString()
  }
  const d = new Date(String(val))
  return Number.isNaN(d.getTime()) ? '-' : d.toLocaleString()
}

function fenToYuan(fen: unknown): string {
  const n = Number(fen)
  if (!Number.isFinite(n)) return '0.00'
  return (n / 100).toFixed(2)
}

function orderStatusLabel(status: string): string {
  const map: Record<string, string> = {
    paid: '已支付',
    pending: '待支付',
    cancelled: '已取消',
    failed: '失败',
    closed: '已关闭'
  }
  return map[status] || status || '-'
}

// 加载企业列表
const loadEnterprises = async () => {
  loading.value = true
  try {
    const params: any = {
      page: currentPage.value,
      pageSize: pageSize
    }
    
    if (searchTerm.value) {
      params.keyword = searchTerm.value
    }
    
    if (statusFilter.value) {
      params.status = statusFilter.value
    }
    
    const response = await request.get('/enterprises', { params })
    
    if (response.code === 200 && response.data) {
      enterprises.value = response.data.list || []
      total.value = response.data.total || 0
      activeCount.value = response.data.activeCount || 0
    }
  } catch (error: any) {
    console.error('加载企业列表失败:', error)
    ElMessage.error(error.message || '加载企业列表失败')
  } finally {
    loading.value = false
  }
}

const handleRefresh = () => {
  loadEnterprises()
}

// 搜索
const handleSearch = () => {
  currentPage.value = 1
  loadEnterprises()
}

// 状态筛选
const handleStatusFilter = () => {
  currentPage.value = 1
  loadEnterprises()
}

const handlePageChange = (val: number) => {
  currentPage.value = val
  loadEnterprises()
}

// 创建企业
const handleCreateEnterprise = async () => {
  // 验证必填字段
  if (!newEnterprise.name) {
    ElMessage.warning('请填写企业名称')
    return
  }
  
  if (!newEnterprise.adminUsername) {
    ElMessage.warning('请填写管理员用户名')
    return
  }
  
  if (!newEnterprise.adminPassword) {
    ElMessage.warning('请填写管理员密码')
    return
  }
  
  if (newEnterprise.adminPassword !== newEnterprise.adminPasswordConfirm) {
    ElMessage.warning('两次输入的密码不一致')
    return
  }
  
  if (newEnterprise.adminPassword.length < 6) {
    ElMessage.warning('密码长度至少6位')
    return
  }
  
  creating.value = true
  try {
    const response = await request.post('/enterprises', {
      name: newEnterprise.name,
      code: newEnterprise.code,
      adminUsername: newEnterprise.adminUsername,
      adminPassword: newEnterprise.adminPassword,
      contactName: newEnterprise.contactName,
      contactPhone: newEnterprise.contactPhone,
      contactEmail: newEnterprise.contactEmail,
      status: newEnterprise.status,
      permissions: { ...newEnterprise.permissions }
    })
    
    if (response.code === 200) {
      ElMessage.success('企业创建成功')
      showCreateDialog.value = false
      Object.assign(newEnterprise, {
        name: '',
        code: '',
        adminUsername: '',
        adminPassword: '',
        adminPasswordConfirm: '',
        contactName: '',
        contactPhone: '',
        contactEmail: '',
        status: 'operating',
        trialExpireAt: '',
        permissions: defaultPermissions()
      })
      loadEnterprises()
    }
  } catch (error: any) {
    console.error('创建企业失败:', error)
    ElMessage.error(error.message || '创建企业失败')
  } finally {
    creating.value = false
  }
}

/** 拉取企业详情（支持小程序用户 / 测试记录 / 最近订单分页参数） */
const fetchEnterpriseDetail = async () => {
  const id = viewDetailEnterpriseId.value
  if (id == null) return
  viewLoading.value = true
  try {
    const response = await request.get(`/enterprises/${id}/detail`, {
      params: {
        wechatPage: detailWechatPage.value,
        wechatPageSize: detailWechatPageSize.value,
        testPage: detailTestPage.value,
        testPageSize: detailTestPageSize.value,
        orderPage: detailOrderPage.value,
        orderPageSize: detailOrderPageSize.value
      }
    })
    if (response.code === 200 && response.data) {
      const data = response.data
      viewEnterpriseData.value = data
      viewEnterpriseUsers.value = Array.isArray(data.wechatUsers) ? data.wechatUsers : data.users || []
      viewEnterpriseTests.value = Array.isArray(data.miniprogramTestResults)
        ? data.miniprogramTestResults
        : data.testResults || []
    }
  } catch (error: any) {
    console.error('获取企业详情失败:', error)
    ElMessage.error(error.message || '获取企业详情失败')
  } finally {
    viewLoading.value = false
  }
}

// 查看详情
const handleView = async (row: any) => {
  viewDetailEnterpriseId.value = row.id
  detailWechatPage.value = 1
  detailTestPage.value = 1
  detailOrderPage.value = 1
  enterpriseDetailTab.value = 'overview'
  viewEnterpriseData.value = null
  viewEnterpriseUsers.value = []
  viewEnterpriseTests.value = []
  await fetchEnterpriseDetail()
  if (viewEnterpriseData.value) {
    showViewDialog.value = true
  }
}

const onDetailWechatSizeChange = (size: number) => {
  detailWechatPageSize.value = size
  detailWechatPage.value = 1
  fetchEnterpriseDetail()
}
const onDetailTestSizeChange = (size: number) => {
  detailTestPageSize.value = size
  detailTestPage.value = 1
  fetchEnterpriseDetail()
}
const onDetailOrderSizeChange = (size: number) => {
  detailOrderPageSize.value = size
  detailOrderPage.value = 1
  fetchEnterpriseDetail()
}

// 编辑企业
const handleEdit = async (row: any) => {
  try {
    const response = await request.get(`/enterprises/${row.id}`)
    if (response.code === 200 && response.data) {
      currentEditId.value = row.id
      pendingEditData.value = response.data
      showEditDialog.value = true
    }
  } catch (error: any) {
    console.error('获取企业信息失败:', error)
    ElMessage.error(error.message || '获取企业信息失败')
  }
}

// 对话框打开时填充数据
const handleEditDialogOpen = async () => {
  if (pendingEditData.value) {
    const data = pendingEditData.value
    console.log('填充编辑表单数据:', data)
    
    // 转换时间戳为日期时间字符串
    let trialExpireAtStr = ''
    if (data.trialExpireAt) {
      const date = new Date(data.trialExpireAt * 1000)
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      const hours = String(date.getHours()).padStart(2, '0')
      const minutes = String(date.getMinutes()).padStart(2, '0')
      const seconds = String(date.getSeconds()).padStart(2, '0')
      trialExpireAtStr = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
    }
    
    // 先清空表单
    editEnterprise.name = ''
    editEnterprise.code = ''
    editEnterprise.contactName = ''
    editEnterprise.contactPhone = ''
    editEnterprise.contactEmail = ''
    editEnterprise.status = 'operating'
    editEnterprise.trialExpireAt = ''
    editEnterprise.permissions = defaultPermissions()
    
    // 等待一个tick确保清空完成
    await nextTick()
    
    // 使用 Object.assign 一次性更新所有字段
    const ceilingFromApi =
      data.permissionsCeiling && typeof data.permissionsCeiling === 'object'
        ? { ...defaultPermissions(), ...data.permissionsCeiling }
        : data.permissions && typeof data.permissions === 'object'
          ? { ...defaultPermissions(), ...data.permissions }
          : defaultPermissions()
    Object.assign(editEnterprise, {
      name: data.name || '',
      code: data.code || '',
      contactName: data.contactName || '',
      contactPhone: data.contactPhone || '',
      contactEmail: data.contactEmail || '',
      status: data.status || 'operating',
      trialExpireAt: trialExpireAtStr,
      permissions: ceilingFromApi
    })
    
    console.log('表单数据已填充:', editEnterprise)
    pendingEditData.value = null
  }
}

// 保存编辑
const handleSaveEdit = async () => {
  if (!currentEditId.value) return
  
  if (!editEnterprise.name) {
    ElMessage.warning('请填写企业名称')
    return
  }
  
  // 如果选择试用，验证到期时间
  if (editEnterprise.status === 'trial' && !editEnterprise.trialExpireAt) {
    ElMessage.warning('选择试用状态时，请选择试用到期时间')
    return
  }
  
  try {
    const requestData: any = {
      name: editEnterprise.name,
      code: editEnterprise.code,
      contactName: editEnterprise.contactName,
      contactPhone: editEnterprise.contactPhone,
      contactEmail: editEnterprise.contactEmail,
      status: editEnterprise.status,
      permissions: { ...editEnterprise.permissions }
    }
    
    // 如果选择试用，添加到期时间（转换为时间戳）
    if (editEnterprise.status === 'trial' && editEnterprise.trialExpireAt) {
      requestData.trialExpireAt = Math.floor(new Date(editEnterprise.trialExpireAt).getTime() / 1000)
    } else if (editEnterprise.status !== 'trial') {
      // 如果不是试用状态，清空到期时间
      requestData.trialExpireAt = null
    }
    
    const response = await request.put(`/enterprises/${currentEditId.value}`, requestData)
    
    if (response.code === 200) {
      ElMessage.success('企业更新成功')
      showEditDialog.value = false
      currentEditId.value = null
      loadEnterprises()
    }
  } catch (error: any) {
    console.error('更新企业失败:', error)
    ElMessage.error(error.message || '更新企业失败')
  }
}

// 删除企业
const handleDelete = (row: any) => {
  ElMessageBox.confirm('确定要删除该企业吗？删除后无法恢复。', '警告', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const response = await request.delete(`/enterprises/${row.id}`)
      
      if (response.code === 200) {
        ElMessage.success('删除成功')
        loadEnterprises()
      }
    } catch (error: any) {
      console.error('删除企业失败:', error)
      ElMessage.error(error.message || '删除企业失败')
    }
  }).catch(() => {
    // 取消删除
  })
}

// 初始化加载
onMounted(() => {
  loadEnterprises()
})

// 监听搜索和筛选（使用防抖）
let searchTimer: any = null
watch([searchTerm, statusFilter], () => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadEnterprises()
  }, 500)
})
</script>

<style scoped lang="scss">
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;

  .header-left {
    h2 {
      font-size: 22px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 4px 0;
    }
    .subtitle {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
    }
  }

  .header-actions {
    display: flex;
    gap: 12px;
  }
}

.content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.toolbar {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #f3f4f6;
  gap: 20px;

  .search-input {
    max-width: 320px;
    :deep(.el-input__wrapper) {
      border-radius: 6px;
      background-color: #f9fafb;
      box-shadow: none;
      border: 1px solid #e5e7eb;
      
      &.is-focus {
        border-color: #ef4444;
        background-color: #fff;
      }
    }
  }

  .toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .toolbar-embedded-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  .filter-group {
    display: flex;
    background-color: #f3f4f6;
    padding: 3px;
    border-radius: 6px;
    gap: 2px;

    .filter-item {
      padding: 4px 12px;
      font-size: 12px;
      color: #6b7280;
      cursor: pointer;
      border-radius: 4px;
      transition: all 0.2s;
      white-space: nowrap;

      &:hover {
        color: #111827;
      }

      &.active {
        background-color: #ef4444;
        color: #fff;
        font-weight: 500;
      }
    }
  }
}

.custom-table {
  :deep(.el-table__header) {
    th {
      background-color: #f9fafb;
      color: #6b7280;
      font-weight: 500;
      font-size: 13px;
      padding: 12px 0;
    }
  }

  .enterprise-info-cell {
    display: flex;
    align-items: center;
    gap: 12px;

    .enterprise-avatar {
      background-color: #fee2e2;
      color: #ef4444;
      font-weight: 600;
    }

    .enterprise-details {
      .enterprise-name {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
      }
      .enterprise-code {
        font-size: 12px;
        color: #9ca3af;
      }
    }
  }

  .contact-cell {
    .contact-name {
      color: #111827;
      font-size: 13px;
    }
    .contact-phone {
      color: #9ca3af;
      font-size: 12px;
    }
  }

  .user-count, .balance-cell, .test-usage-cell {
    font-size: 13px;
    color: #374151;
  }

  .action-buttons {
    display: flex;
    gap: 4px;
    
    .el-button {
      padding: 4px;
      font-size: 16px;
      color: #6b7280;
      
      &:hover {
        color: #ef4444;
      }
      
      &.el-button--danger:hover {
        color: #ef4444;
      }

      &.action-invite:hover {
        color: #3b82f6;
      }
    }
  }
}

.invite-qrcode-dialog-body {
  min-height: 140px;
}

.invite-qrcode-pair {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 20px;
  padding: 8px 0;
}

.invite-qrcode-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.invite-qrcode-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}

.invite-qrcode-img {
  width: 120px;
  height: 120px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  object-fit: contain;
  background: #fff;
}

.invite-qrcode-placeholder {
  margin: 0;
  font-size: 13px;
  color: #9ca3af;
  text-align: center;
  padding: 24px 12px;
}

.empty-state {
  padding: 60px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}

.pagination-container {
  padding: 20px;
  display: flex;
  justify-content: center;
}

/* 弹框样式 */
:deep(.custom-dialog) {
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

  .el-dialog__header {
    padding: 20px 20px 0;
    margin-right: 0;
    border: none;
  }

  .el-dialog__body {
    padding: 16px 20px;
  }

  .el-dialog__footer {
    padding: 12px 20px 20px;
    border: none;
  }

  .el-dialog__headerbtn {
    top: 16px;
    right: 16px;
    font-size: 18px;
    
    &:hover .el-dialog__close {
      color: #ef4444;
    }
  }
}

.detail-dialog {
  :deep(.el-dialog__body) {
    padding: 0;
  }
}

.enterprise-detail-dialog {
  :deep(.el-dialog__body) {
    max-height: 86vh;
    overflow: hidden;
  }
}

.dialog-header {
  .dialog-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 6px 0;
    line-height: 1.2;
  }

  .dialog-subtitle {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
    line-height: 1.5;
  }
}

.optimized-form {
  padding: 0 4px;
}

.form-section-title {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  padding-bottom: 8px;
  margin-bottom: 16px;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  align-items: center;

  &::before {
    content: '';
    display: inline-block;
    width: 3px;
    height: 14px;
    background-color: #ef4444;
    border-radius: 2px;
    margin-right: 8px;
  }
}

.custom-form {
  :deep(.el-form-item) {
    margin-bottom: 18px;

    .el-form-item__label {
      font-size: 13px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 6px;
      padding: 0;
      line-height: 1;
    }

    .el-input__wrapper {
      box-shadow: 0 0 0 1px #e5e7eb inset;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.2s;
      background-color: #fff;

      &.is-focus {
        box-shadow: 0 0 0 1px #ef4444 inset, 0 0 0 3px rgba(239, 68, 68, 0.1);
      }

      &:hover {
        box-shadow: 0 0 0 1px #d1d5db inset;
      }
    }

    .el-input__inner {
      height: 24px;
      font-size: 14px;
      color: #111827;

      &::placeholder {
        color: #9ca3af;
      }
    }

    .el-select {
      width: 100%;
      .el-input__wrapper {
        padding: 4px 12px;
      }
    }
  }
}

.dialog-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;

  .el-button {
    height: 38px;
    padding: 0 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;

    &.cancel-btn {
      border: 1px solid #e5e7eb;
      color: #4b5563;
      &:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
        color: #111827;
      }
    }

    &.submit-btn {
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      &:hover {
        opacity: 0.9;
        transform: translateY(-1px);
      }
      &:active {
        transform: translateY(0);
      }
    }
  }
}

.mr-1 {
  margin-right: 4px;
}

.w-full {
  width: 100%;
}

/* 企业详情：与用户详情 UserDetailDialog 相同的 ud-wrap / ud-side / ud-main 布局 */
.enterprise-detail-content {
  max-height: 86vh;
  padding: 8px 4px 4px;
  overflow: hidden;
}

.ud-wrap {
  display: flex;
  gap: 14px;
  align-items: stretch;
  min-height: 200px;
  max-height: min(620px, 78vh);
}

/* 左侧侧栏（与用户详情 UserDetailDialog 同款） */
.ud-side {
  width: 200px;
  flex-shrink: 0;
  background: linear-gradient(180deg, #faf5ff 0%, #fff 40%);
  border: 1px solid #ede9fe;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
}

.ud-avatar-block {
  text-align: center;
  margin-bottom: 10px;
}

.ud-avatar-letter {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #7c3aed;
  color: #fff;
  font-size: 22px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ud-name {
  margin-top: 6px;
  font-weight: 700;
  font-size: 14px;
  color: #111827;
  line-height: 1.3;
  word-break: break-all;
}

.ud-meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #4b5563;
  margin-bottom: 6px;

  .el-icon {
    color: #a855f7;
    flex-shrink: 0;
  }
}

.ud-stat-icons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  margin-top: 10px;
}

.ud-stat-ic {
  background: #fff;
  border-radius: 8px;
  padding: 6px 8px;
  font-size: 11px;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 4px;
  border: 1px solid #f3e8ff;
  min-width: 0;

  .el-icon {
    color: #7c3aed;
    font-size: 14px;
    flex-shrink: 0;
  }
}

.ud-stat-ic--money {
  font-variant-numeric: tabular-nums;
}

.ud-tags {
  margin-top: 12px;
}

.ud-tags-title {
  font-size: 11px;
  color: #9ca3af;
  margin-bottom: 6px;
}

.ud-tag {
  margin: 0 4px 4px 0;
}

/* 右侧「经营概览」滚动区（对齐 ud-scroll） */
.ud-scroll {
  max-height: calc(78vh - 120px);
  overflow-y: auto;
  padding-right: 4px;
}

.ed-overview-scroll {
  padding-bottom: 8px;
}

.ud-radar-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.ud-radar-cell {
  background: #fafafa;
  border-radius: 8px;
  padding: 6px 4px 4px;
  text-align: center;
}

.ed-kpi-cell {
  padding-bottom: 8px;
}

.ud-radar-title {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;

  .el-icon {
    color: #7c3aed;
  }
}

.ed-kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.ed-kpi-sub {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 2px;
}

.ud-row2 {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

.ud-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 8px;
  padding: 8px 10px;
  flex: 1;
  min-width: 0;
}

.ud-grow {
  flex: 1.2;
}

.ud-card-h {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;

  .el-icon {
    color: #7c3aed;
  }
}

.ud-roles {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ud-role {
  display: grid;
  grid-template-columns: 72px 1fr 36px;
  gap: 8px;
  align-items: center;
  font-size: 11px;
}

.ud-role-n {
  color: #4b5563;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ud-role-p {
  text-align: right;
  color: #7c3aed;
  font-weight: 600;
}

.ud-muted {
  font-size: 12px;
  color: #9ca3af;
  padding: 8px 0;
}

.ed-order-sum {
  margin-top: 8px;
  font-size: 12px;
  color: #6b7280;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;

  strong {
    color: #111827;
    font-size: 13px;
  }
}

.ed-stats-hint {
  margin: 8px 0 0;
  font-size: 11px;
  line-height: 1.4;
}

.ed-admin-summary {
  margin-top: 10px;
}

.ed-admin-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.stats-hint {
  margin: 12px 0 0;
  font-size: 12px;
  color: #6b7280;
}

/* 右侧主区（对齐 UserDetailDialog .ud-main） */
.ud-main {
  flex: 1;
  min-width: 0;
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
}

.ud-tabs {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;

  :deep(.el-tabs__header) {
    margin: 0;
    padding: 12px 14px 0;
  }

  :deep(.el-tabs__active-bar) {
    background-color: #7c3aed;
    height: 2px;
  }

  :deep(.el-tabs__item) {
    font-size: 13px;
  }

  :deep(.el-tabs__item.is-active) {
    color: #7c3aed;
    font-weight: 600;
  }

  :deep(.el-tabs__item:hover) {
    color: #9333ea;
  }

  :deep(.el-tabs__content) {
    flex: 1;
    overflow: hidden;
    padding: 0 14px 12px;
  }

  :deep(.el-tab-pane) {
    height: 100%;
    overflow-y: auto;
  }
}

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.stats-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stats-mini-card {
  padding: 16px;
  background-color: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 12px;
  text-align: center;
}

.card-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 8px;
}

.card-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

.text-primary { color: #ef4444; }
.text-success { color: #10b981; }

.analytics-header {
  display: flex;
  gap: 32px;
  padding: 20px;
  background-color: #fff;
  border-radius: 12px;
  border: 1px solid #f3f4f6;
}

.analytics-main-stat {
  display: flex;
  flex-direction: column;
}

.analytics-main-stat .label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.analytics-main-stat .value {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
}

.detail-pagination {
  margin-top: 16px;
  justify-content: center;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.subsection-title {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 10px;
}

.empty-placeholder-small {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #9ca3af;

  .empty-icon {
    font-size: 48px;
    color: #d1d5db;
    margin-bottom: 12px;
  }

  .empty-text {
    font-size: 13px;
    color: #9ca3af;
    margin: 0;
  }
}

.page-container.is-embedded {
  min-height: auto;
}

/* 权限标签 */
.perm-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.perm-tag {
  font-size: 11px;
  border-radius: 4px;
}

.perm-form-hint {
  font-size: 12px;
  color: #6b7280;
  margin: 0 0 10px 0;
  line-height: 1.45;
}

/* 权限开关组（编辑 / 创建弹窗） */
.perm-switch-group {
  display: flex;
  flex-wrap: wrap;
  gap: 14px 24px;
}

.perm-switch-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.perm-label {
  font-size: 13px;
  color: #374151;
  white-space: nowrap;
}

.mt-12 {
  margin-top: 12px;
}

@media (max-width: 900px) {
  .ud-wrap {
    flex-direction: column;
    max-height: none;
  }
  .ud-side {
    width: 100%;
  }
  .ud-radar-row {
    grid-template-columns: 1fr;
  }
}
</style>

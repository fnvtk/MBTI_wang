<template>
  <div class="page-container">
    <div class="page-header">
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

        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <div class="action-buttons">
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
      title="创建新企业"
      width="440px"
      class="custom-dialog"
      :show-close="true"
      align-center
    >
      <template #header>
        <div class="dialog-header">
          <h3 class="dialog-title">创建新企业</h3>
          <p class="dialog-subtitle">添加新企业到系统，设置基本信息和配置</p>
        </div>
      </template>

      <el-form :model="newEnterprise" label-position="top" class="custom-form">
        <el-form-item label="企业名称" required>
          <el-input v-model="newEnterprise.name" placeholder="请输入企业名称" />
        </el-form-item>
        
        <el-form-item label="企业代码">
          <el-input v-model="newEnterprise.code" placeholder="请输入企业代码（可选）" />
        </el-form-item>

        <el-divider content-position="left">企业管理员账号</el-divider>

        <el-form-item label="管理员用户名" required>
          <el-input v-model="newEnterprise.adminUsername" placeholder="请输入管理员用户名" />
        </el-form-item>

        <el-form-item label="管理员密码" required>
          <el-input 
            v-model="newEnterprise.adminPassword" 
            type="password" 
            placeholder="请输入管理员密码"
            show-password
          />
        </el-form-item>

        <el-form-item label="确认密码" required>
          <el-input 
            v-model="newEnterprise.adminPasswordConfirm" 
            type="password" 
            placeholder="请再次输入密码"
            show-password
          />
        </el-form-item>

        <el-divider content-position="left">企业信息</el-divider>

        <el-form-item label="联系人姓名">
          <el-input v-model="newEnterprise.contactName" placeholder="请输入联系人姓名" />
        </el-form-item>

        <el-form-item label="联系人电话">
          <el-input v-model="newEnterprise.contactPhone" placeholder="请输入联系人电话" />
        </el-form-item>

        <el-form-item label="联系人邮箱">
          <el-input v-model="newEnterprise.contactEmail" placeholder="请输入联系人邮箱" />
        </el-form-item>

        <el-form-item label="状态">
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
            placeholder="请选择试用到期时间"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            class="w-full"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showCreateDialog = false" class="cancel-btn">取消</el-button>
          <el-button type="primary" color="#ef4444" @click="handleCreateEnterprise" class="submit-btn">
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
      width="800px"
      class="custom-dialog"
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
        <div v-if="viewEnterpriseData" class="detail-sections">
          <!-- 基本信息 -->
          <div class="detail-section">
            <h4 class="section-title">基本信息</h4>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">企业名称：</span>
                <span class="info-value">{{ viewEnterpriseData.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">企业代码：</span>
                <span class="info-value">{{ viewEnterpriseData.code || '-' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">联系人：</span>
                <span class="info-value">{{ viewEnterpriseData.contactName || '-' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">联系电话：</span>
                <span class="info-value">{{ viewEnterpriseData.contactPhone || '-' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">联系邮箱：</span>
                <span class="info-value">{{ viewEnterpriseData.contactEmail || '-' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">账户余额：</span>
                <span class="info-value">¥{{ (viewEnterpriseData.balance || 0).toLocaleString() }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">状态：</span>
                <el-tag 
                  :type="viewEnterpriseData.status === 'operating' ? 'success' : viewEnterpriseData.status === 'trial' ? 'warning' : 'info'" 
                  size="small"
                >
                  {{ getStatusLabel(viewEnterpriseData.status) }}
                </el-tag>
              </div>
              <div class="info-item" v-if="viewEnterpriseData.status === 'trial' && viewEnterpriseData.trialExpireAt">
                <span class="info-label">试用到期时间：</span>
                <span class="info-value">{{ new Date(viewEnterpriseData.trialExpireAt * 1000).toLocaleString() }}</span>
              </div>
            </div>
          </div>

          <!-- 管理员账号 -->
          <div class="detail-section">
            <h4 class="section-title">管理员账号 ({{ viewEnterpriseData.adminAccounts?.length || 0 }})</h4>
            <el-table :data="viewEnterpriseData.adminAccounts || []" style="width: 100%" size="small" v-if="viewEnterpriseData.adminAccounts && viewEnterpriseData.adminAccounts.length > 0">
              <el-table-column prop="username" label="用户名" />
              <el-table-column prop="email" label="邮箱" />
              <el-table-column prop="phone" label="电话" />
              <el-table-column prop="role" label="角色">
                <template #default="{ row }">
                  <el-tag size="small">{{ row.role === 'enterprise_admin' ? '企业管理员' : row.role }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="status" label="状态">
                <template #default="{ row }">
                  <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                    {{ row.status === 1 ? '启用' : '禁用' }}
                  </el-tag>
                </template>
              </el-table-column>
            </el-table>
            <div v-else class="empty-placeholder-small">
              <el-icon class="empty-icon"><User /></el-icon>
              <p class="empty-text">暂无管理员账户</p>
            </div>
          </div>

          <!-- 用户列表 -->
          <div class="detail-section">
            <h4 class="section-title">用户列表 ({{ viewEnterpriseUsers.length }})</h4>
            <el-table :data="viewEnterpriseUsers" style="width: 100%" size="small" max-height="200" v-if="viewEnterpriseUsers.length > 0">
              <el-table-column prop="username" label="用户名" />
              <el-table-column prop="email" label="邮箱" />
              <el-table-column prop="phone" label="电话" />
              <el-table-column prop="mbtiType" label="MBTI类型" />
              <el-table-column prop="status" label="状态">
                <template #default="{ row }">
                  <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                    {{ row.status === 1 ? '启用' : '禁用' }}
                  </el-tag>
                </template>
              </el-table-column>
            </el-table>
            <div v-else class="empty-placeholder-small">
              <el-icon class="empty-icon"><User /></el-icon>
              <p class="empty-text">暂无用户数据</p>
            </div>
          </div>

          <!-- 测试记录 -->
          <div class="detail-section">
            <h4 class="section-title">测试记录 ({{ viewEnterpriseTests.length }})</h4>
            <el-table :data="viewEnterpriseTests" style="width: 100%" size="small" max-height="200" v-if="viewEnterpriseTests.length > 0">
              <el-table-column prop="testType" label="测试类型" />
              <el-table-column prop="username" label="用户" />
              <el-table-column prop="createdAt" label="测试时间">
                <template #default="{ row }">
                  {{ row.createdAt ? new Date(row.createdAt * 1000).toLocaleString() : '-' }}
                </template>
              </el-table-column>
            </el-table>
            <div v-else class="empty-placeholder-small">
              <el-icon class="empty-icon"><TrendCharts /></el-icon>
              <p class="empty-text">暂无测试数据</p>
            </div>
          </div>

          <!-- 统计数据 -->
          <div class="detail-section">
            <h4 class="section-title">统计数据</h4>
            <div class="stats-grid">
              <div class="stat-item">
                <span class="stat-label">用户总数：</span>
                <span class="stat-value">{{ viewEnterpriseData.userCount || 0 }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">测试总量：</span>
                <span class="stat-value">{{ viewEnterpriseData.testUsage || 0 }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">管理员数：</span>
                <span class="stat-value">{{ viewEnterpriseData.adminAccounts?.length || 0 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showViewDialog = false" class="cancel-btn">关闭</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { Plus, Refresh, Search, View, Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { request } from '@/utils/request'

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

const statusOptions = [
  { label: '全部', value: '' },
  { label: '运营中', value: 'operating' },
  { label: '试用', value: 'trial' },
  { label: '已停用', value: 'disabled' }
]

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
  trialExpireAt: '' as string
})

const editEnterprise = reactive({
  name: '',
  code: '',
  contactName: '',
  contactPhone: '',
  contactEmail: '',
  status: 'operating',
  trialExpireAt: ''
})

const getStatusLabel = (status: string) => {
  const statusMap: Record<string, string> = {
    'operating': '运营中',
    'trial': '试用',
    'disabled': '已停用'
  }
  return statusMap[status] || '未知'
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
  
  try {
    const response = await request.post('/enterprises', {
      name: newEnterprise.name,
      code: newEnterprise.code,
      adminUsername: newEnterprise.adminUsername,
      adminPassword: newEnterprise.adminPassword,
      contactName: newEnterprise.contactName,
      contactPhone: newEnterprise.contactPhone,
      contactEmail: newEnterprise.contactEmail,
      status: newEnterprise.status
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
        trialExpireAt: ''
      })
      loadEnterprises()
    }
  } catch (error: any) {
    console.error('创建企业失败:', error)
    ElMessage.error(error.message || '创建企业失败')
  }
}

// 查看详情
const handleView = async (row: any) => {
  viewLoading.value = true
  try {
    const response = await request.get(`/enterprises/${row.id}/detail`)
    if (response.code === 200 && response.data) {
      viewEnterpriseData.value = response.data
      viewEnterpriseUsers.value = response.data.users || []
      viewEnterpriseTests.value = response.data.testResults || []
      showViewDialog.value = true
    }
  } catch (error: any) {
    console.error('获取企业详情失败:', error)
    ElMessage.error(error.message || '获取企业详情失败')
  } finally {
    viewLoading.value = false
  }
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
    
    // 等待一个tick确保清空完成
    await nextTick()
    
    // 使用 Object.assign 一次性更新所有字段
    Object.assign(editEnterprise, {
      name: data.name || '',
      code: data.code || '',
      contactName: data.contactName || '',
      contactPhone: data.contactPhone || '',
      contactEmail: data.contactEmail || '',
      status: data.status || 'operating',
      trialExpireAt: trialExpireAtStr
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
      status: editEnterprise.status
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
    }
  }
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

/* 企业详情对话框样式 */
.enterprise-detail-content {
  max-height: 70vh;
  overflow-y: auto;
}

.detail-sections {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.detail-section {
  border-bottom: 1px solid #f3f4f6;
  padding-bottom: 16px;
  
  &:last-child {
    border-bottom: none;
  }
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px 24px;
}

.info-item {
  display: flex;
  align-items: center;
}

.info-label {
  font-weight: 500;
  color: #6b7280;
  margin-right: 8px;
  min-width: 80px;
}

.info-value {
  color: #111827;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 20px;
  font-weight: 600;
  color: #111827;
}

.empty-data {
  padding: 20px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
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
</style>

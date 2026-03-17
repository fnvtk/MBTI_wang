<template>
  <div class="page-container" v-loading="loading">
    <div class="page-header">
      <div class="header-left">
        <h2>题库管理</h2>
        <p class="subtitle">管理 MBTI、DISC、PDP 三套测试题库的导入、导出和验证</p>
      </div>
      <div class="header-actions">
        <el-button variant="outline" @click="refresh">
          <el-icon class="mr-1"><Refresh /></el-icon>刷新
        </el-button>
      </div>
    </div>

    <!-- 题库概览 -->
    <div class="stats-grid">
      <div 
        v-for="bank in questionBanks" 
        :key="bank.type"
        :class="['test-type-card', { active: selectedBank === bank.type }]"
        @click="selectedBank = bank.type"
      >
        <div class="card-content">
          <div class="type-info">
            <div :class="['icon-box', bank.color]">
              <el-icon><component :is="bank.icon" /></el-icon>
            </div>
            <div class="text">
              <p class="name">{{ bank.name }}</p>
              <p class="desc">{{ bank.desc }}</p>
            </div>
          </div>
          <div class="count-info">
            <div class="count"><span>{{ bank.count }}</span>题</div>
            <el-tag size="small" :type="bank.count > 0 ? 'success' : 'info'" effect="light">
              {{ bank.count > 0 ? '已加载' : '暂无' }}
            </el-tag>
          </div>
          <div class="dimensions" v-if="bank.dimensions.length > 0">
            <el-tag v-for="d in bank.dimensions" :key="d" size="small" effect="plain" class="dim-tag">{{ d }}</el-tag>
          </div>
          <div v-else class="dimensions empty"></div>
        </div>
      </div>
    </div>

    <!-- 题目列表 -->
    <el-card shadow="hover" class="list-card">
      <template #header>
        <div class="list-header">
          <div class="info">
            <el-icon :class="['mr-1', `${currentBankInfo.color}-text`]">
              <component :is="currentBankInfo.icon" />
            </el-icon>
            <strong>{{ currentBankInfo.name }} 题库管理</strong>
            <span class="count">共 {{ currentBankInfo.count }} 题 · 最后更新: {{ currentBankInfo.lastUpdate || '暂无' }}</span>
          </div>
          <div class="actions">
            <input 
              ref="fileInput" 
              type="file" 
              accept=".json" 
              @change="handleImport" 
              style="display: none"
            />
            <el-button size="small" type="primary" color="#7c3aed" style="color:#fff" @click="openAdd">
              <el-icon class="mr-1"><Plus /></el-icon>新增题目
            </el-button>
            <el-button size="small" @click="triggerImport">
              <el-icon class="mr-1"><Upload /></el-icon>导入JSON
            </el-button>
            <el-button size="small" @click="handleExport">
              <el-icon class="mr-1"><Download /></el-icon>导出JSON
            </el-button>
            <el-button size="small" @click="handleValidate">
              <el-icon class="mr-1"><CircleCheck /></el-icon>验证题库
            </el-button>
          </div>
        </div>
      </template>

      <div class="toolbar">
        <el-input v-model="search" placeholder="搜索题目内容、ID、维度..." class="search-input">
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-button link class="expand-btn" @click="toggleOptions">
          <el-icon class="mr-1"><View /></el-icon>{{ showOptions ? '收起选项' : '展开选项' }}
        </el-button>
      </div>

      <div class="question-list" v-loading="loading">
        <div v-if="currentQuestions.length === 0" class="empty-state">
          <p>暂无题目数据</p>
        </div>
        <div v-for="(question, index) in currentQuestions" :key="question.id || index" class="question-item">
          <div class="index">{{ index + 1 }}</div>
          <div class="content">
            <div class="text">
              {{ question.question }}
              <el-tag v-if="question.dimension" size="small" effect="plain" class="dim-badge">{{ question.dimension }}</el-tag>
            </div>
            <div v-show="showOptions" class="options">
              <span v-for="(opt, optIndex) in question.options" :key="optIndex" class="opt">
                <strong>{{ opt.value || opt.key || String(Number(optIndex) + 1) }}</strong> {{ opt.text }}
              </span>
              <span v-if="!question.options || question.options.length === 0" class="empty-option">暂无选项</span>
            </div>
          </div>
          <div class="item-actions">
            <el-button size="small" text @click="openEdit(question)"><el-icon><Edit /></el-icon></el-button>
            <el-button size="small" text type="danger" @click="deleteQuestion(question)"><el-icon><Delete /></el-icon></el-button>
          </div>
        </div>
      </div>
      
      <div class="list-footer">
        显示 {{ currentQuestions.length }} / {{ currentBankInfo.count }} 题
        <span class="size">文件大小: {{ currentBankInfo.fileSize }}</span>
      </div>
    </el-card>

    <!-- 编辑 / 新增弹窗 -->
    <el-dialog
      v-model="editDialogVisible"
      :title="isEditing ? '编辑题目' : '新增题目'"
      width="640px"
      destroy-on-close
    >
      <el-form :model="editForm" label-width="80px" label-position="top">
        <!-- 题型（新增时可选） -->
        <el-form-item label="题型" v-if="!isEditing">
          <el-radio-group v-model="editForm.type">
            <el-radio-button value="mbti">MBTI</el-radio-button>
            <el-radio-button value="disc">DISC</el-radio-button>
            <el-radio-button value="pdp">PDP</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <!-- 题目内容 -->
        <el-form-item label="题目内容">
          <el-input v-model="editForm.question" type="textarea" :rows="3" placeholder="请输入题目内容" />
        </el-form-item>

        <!-- 维度（仅 MBTI） -->
        <el-form-item label="所属维度" v-if="editForm.type === 'mbti'">
          <el-select v-model="editForm.dimension" placeholder="请选择维度">
            <el-option label="EI（内外向）" value="EI" />
            <el-option label="SN（感觉直觉）" value="SN" />
            <el-option label="TF（思考情感）" value="TF" />
            <el-option label="JP（判断感知）" value="JP" />
          </el-select>
        </el-form-item>

        <!-- 选项列表 -->
        <el-form-item label="选项">
          <div class="option-editor">
            <div v-for="(opt, idx) in editForm.options" :key="idx" class="option-row">
              <el-input v-model="opt.value" placeholder="选项值（如A）" class="opt-value" size="small" />
              <el-input v-model="opt.text" placeholder="选项文字" class="opt-text" size="small" />
              <el-button size="small" text type="danger" @click="removeOption(idx)"><el-icon><Delete /></el-icon></el-button>
            </div>
            <el-button size="small" plain @click="addOption" style="margin-top:6px">
              <el-icon><Plus /></el-icon> 添加选项
            </el-button>
          </div>
        </el-form-item>

        <!-- 排序 -->
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort" :min="0" :step="1" controls-position="right" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" color="#7c3aed" :loading="saving" @click="saveQuestion">
          {{ isEditing ? '保存修改' : '创建题目' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 参考资源 -->
    <div class="resources mt-6">
      <p class="res-title"><el-icon class="mr-1"><InfoFilled /></el-icon>题库参考资源</p>
      <el-row :gutter="16">
        <el-col :span="8" v-for="bank in questionBanks" :key="bank.type">
          <div :class="['res-card', bank.color as string]">
            <p class="name">{{ bank.resourceName }}</p>
            <p v-for="(link, idx) in bank.resources" :key="idx" class="link">{{ link }}</p>
            <p class="stat">{{ bank.resourceStat }}</p>
          </div>
        </el-col>
      </el-row>
      <p class="res-tip">提示：导入格式为 JSON 数组，每题包含 id、question、options 字段。MBTI 还需包含 dimension 字段（EI/SN/TF/JP）。</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Cpu, Aim, MagicStick, Upload, Download, CircleCheck, Search, View, InfoFilled, Plus, Edit, Delete } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

const search = ref('')
const fileInput = ref<HTMLInputElement>()
const showOptions = ref(false)
const loading = ref(false)
const selectedBank = ref('mbti')

// 题库信息
const questionBanks = reactive([
  {
    type: 'mbti',
    name: 'MBTI',
    desc: '16型人格测试 · 4维度',
    count: 0,
    color: 'purple',
    icon: Cpu,
    dimensions: [] as string[],
    lastUpdate: '',
    fileSize: '0KB',
    resourceName: 'MBTI 标准 93 题',
    resources: [
      'GitHub: saogegood/MyMBTI',
      'GitHub: vsme/mbti (Next.js版)'
    ],
    resourceStat: '当前: 0题 / 标准93题',
    questions: [] as any[]
  },
  {
    type: 'disc',
    name: 'DISC',
    desc: '4维行为风格测试',
    count: 0,
    color: 'blue',
    icon: Aim,
    dimensions: [] as string[],
    lastUpdate: '',
    fileSize: '0KB',
    resourceName: 'DISC 标准题库',
    resources: [
      'Thomas International 28题版',
      '经典版 24题 / 扩展版 40题'
    ],
    resourceStat: '当前: 0题',
    questions: [] as any[]
  },
  {
    type: 'pdp',
    name: 'PDP',
    desc: '5种动物性格测试',
    count: 0,
    color: 'green',
    icon: MagicStick,
    dimensions: [] as string[],
    lastUpdate: '',
    fileSize: '0KB',
    resourceName: 'PDP 标准题库',
    resources: [
      'PDP Professional DynaMetric 30题',
      '5种动物特质评估'
    ],
    resourceStat: '当前: 0题',
    questions: [] as any[]
  }
])

const currentBankInfo = computed(() => {
  return questionBanks.find(bank => bank.type === selectedBank.value) || questionBanks[0]
})

const currentQuestions = computed(() => {
  const bank = questionBanks.find(bank => bank.type === selectedBank.value)
  if (!bank) return []
  
  let questions = bank.questions
  
  if (search.value) {
    const term = search.value.toLowerCase()
    questions = questions.filter((q: any) =>
      q.question?.toLowerCase().includes(term) ||
      q.dimension?.toLowerCase().includes(term) ||
      (Array.isArray(q.options) && q.options.some((opt: any) => 
        opt.text?.toLowerCase().includes(term) || opt.value?.toLowerCase().includes(term)
      ))
    )
  }
  
  return questions
})

// 加载所有题库的统计信息
const loadAllBankStats = async () => {
  try {
    // 并行加载所有类型的统计
    const promises = questionBanks.map(async (bank) => {
      try {
        const response: any = await request.get('/admin/questions', {
          params: {
            page: 1,
            pageSize: 1, // 只需要总数，不需要数据
            type: bank.type
          }
        })
        
        if (response.code === 200 && response.data) {
          bank.count = response.data.total || 0
          bank.resourceStat = `当前: ${bank.count}题`
        }
      } catch (error) {
        console.error(`加载${bank.name}统计失败:`, error)
      }
    })
    
    await Promise.all(promises)
  } catch (error) {
    console.error('加载题库统计失败:', error)
  }
}

// 加载题库数据
const loadQuestions = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/questions', {
      params: {
        page: 1,
        pageSize: 1000, // 加载所有题目
        type: selectedBank.value
      }
    })
    
    if (response.code === 200 && response.data) {
      const questions = response.data.list || []
      const bank = questionBanks.find(b => b.type === selectedBank.value)
      if (bank) {
        // 转换数据格式
        bank.questions = questions.map((q: any) => {
          // 解析options字段
          let options = []
          if (Array.isArray(q.options)) {
            // 已经是数组格式
            options = q.options
          } else if (typeof q.options === 'string') {
            // JSON字符串，需要解析
            try {
              const parsed = JSON.parse(q.options)
              // 如果是对象格式，转换为数组
              if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                options = Object.values(parsed)
              } else {
                options = Array.isArray(parsed) ? parsed : []
              }
            } catch (e) {
              console.error('解析options失败:', e, q.options)
              options = []
            }
          } else if (q.options && typeof q.options === 'object') {
            // 对象格式，转换为数组
            options = Object.values(q.options)
          }
          
          return {
            id: q.id,
            question: q.question,
            dimension: q.dimension,
            options: options,
            sort: q.sort,
            status: q.status
          }
        })
        bank.count = response.data.total || 0
        bank.resourceStat = `当前: ${bank.count}题`
        
        // 计算维度统计（仅MBTI）
        if (selectedBank.value === 'mbti') {
          const dimensionCounts: Record<string, number> = {}
          questions.forEach((q: any) => {
            if (q.dimension) {
              dimensionCounts[q.dimension] = (dimensionCounts[q.dimension] || 0) + 1
            }
          })
          bank.dimensions = Object.entries(dimensionCounts).map(([dim, count]) => `${dim}: ${count}`)
        } else {
          bank.dimensions = []
        }
        
        // 更新最后更新时间
        if (questions.length > 0) {
          const latestQuestion = questions[questions.length - 1]
          if (latestQuestion.updatedAt) {
            const date = new Date(latestQuestion.updatedAt * 1000)
            bank.lastUpdate = `${date.getFullYear()}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getDate().toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}:${date.getSeconds().toString().padStart(2, '0')}`
          }
        }
        
        // 计算文件大小（估算）
        const jsonStr = JSON.stringify(questions)
        const sizeInBytes = new Blob([jsonStr]).size
        if (sizeInBytes < 1024) {
          bank.fileSize = `${sizeInBytes}B`
        } else if (sizeInBytes < 1024 * 1024) {
          bank.fileSize = `${(sizeInBytes / 1024).toFixed(1)}KB`
        } else {
          bank.fileSize = `${(sizeInBytes / (1024 * 1024)).toFixed(1)}MB`
        }
      }
    }
  } catch (error: any) {
    console.error('加载题库失败:', error)
    ElMessage.error(error.message || '加载题库失败')
  } finally {
    loading.value = false
  }
}

// 监听选中的题库变化
watch(selectedBank, () => {
  const bank = questionBanks.find(b => b.type === selectedBank.value)
  if (bank && bank.questions.length === 0) {
    loadQuestions()
  }
})

const refresh = async () => {
  await loadAllBankStats()
  await loadQuestions()
  ElMessage.success('题库数据已刷新')
}

// 触发文件选择
const triggerImport = () => {
  fileInput.value?.click()
}

// 处理导入JSON
const handleImport = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (!file) return
  
  const reader = new FileReader()
  reader.onload = async (e) => {
    try {
      const json = JSON.parse(e.target?.result as string)
      if (!Array.isArray(json)) {
        ElMessage.error('JSON 格式错误：必须是数组格式')
        return
      }
      
      // 验证数据格式
      const errors: string[] = []
      json.forEach((q: any, index: number) => {
        if (!q.type) errors.push(`第${index + 1}题：缺少type字段`)
        if (!q.question) errors.push(`第${index + 1}题：缺少question字段`)
        if (!q.options || !Array.isArray(q.options)) errors.push(`第${index + 1}题：options必须是数组`)
        if (q.type === 'mbti' && !q.dimension) errors.push(`第${index + 1}题：MBTI类型必须包含dimension字段`)
      })
      
      if (errors.length > 0) {
        ElMessage.error('数据验证失败：' + errors.slice(0, 3).join('；'))
        return
      }
      
      // 调用API导入
      loading.value = true
      try {
        const response: any = await request.post('/admin/questions/batch-import', {
          questions: json
        })
        
        if (response.code === 200) {
          ElMessage.success(`成功导入 ${response.data?.successCount || json.length} 道题目`)
          await refresh()
        }
      } catch (error: any) {
        ElMessage.error(error.message || '导入失败')
      } finally {
        loading.value = false
      }
    } catch (error) {
      ElMessage.error('JSON 格式错误，请检查文件内容')
      console.error('导入失败:', error)
    }
  }
  reader.readAsText(file)
  
  // 清空input，允许重复选择同一文件
  target.value = ''
}

// 处理导出JSON
const handleExport = () => {
  const bank = questionBanks.find(b => b.type === selectedBank.value)
  if (!bank || bank.questions.length === 0) {
    ElMessage.warning('当前题库没有数据可导出')
    return
  }
  
  const questions = bank.questions.map((q: any) => ({
    id: q.id,
    type: selectedBank.value,
    question: q.question,
    dimension: q.dimension,
    options: q.options,
    sort: q.sort,
    status: q.status
  }))
  
  const dataStr = JSON.stringify(questions, null, 2)
  const dataBlob = new Blob([dataStr], { type: 'application/json' })
  const url = URL.createObjectURL(dataBlob)
  
  const link = document.createElement('a')
  link.href = url
  link.download = `${selectedBank.value}-questions-${Date.now()}.json`
  link.click()
  
  URL.revokeObjectURL(url)
  ElMessage.success('题库已导出')
}

// 验证题库
const handleValidate = async () => {
  const bank = questionBanks.find(b => b.type === selectedBank.value)
  if (!bank || bank.questions.length === 0) {
    ElMessage.warning('当前题库没有数据可验证')
    return
  }
  
  try {
    await ElMessageBox.confirm(
      '将验证题库的完整性、维度分布和格式规范，是否继续？',
      '验证题库',
      {
        confirmButtonText: '开始验证',
        cancelButtonText: '取消',
        type: 'info'
      }
    )
    
    const questions = bank.questions
    const errors: string[] = []
    const dimensionCounts: Record<string, number> = {}
    
    questions.forEach((q: any, index: number) => {
      if (!q.question) errors.push(`第${index + 1}题：缺少题目内容`)
      if (!q.options || !Array.isArray(q.options) || q.options.length === 0) {
        errors.push(`第${index + 1}题：选项格式错误或为空`)
      }
      if (selectedBank.value === 'mbti' && !q.dimension) {
        errors.push(`第${index + 1}题：缺少维度信息`)
      }
      if (q.dimension) {
        dimensionCounts[q.dimension] = (dimensionCounts[q.dimension] || 0) + 1
      }
    })
    
    if (errors.length > 0) {
      ElMessage.error(`验证失败，发现 ${errors.length} 个问题：${errors.slice(0, 3).join('；')}`)
      return
    }
    
    // 检查维度分布（仅MBTI）
    if (selectedBank.value === 'mbti') {
      const expectedDimensions = ['EI', 'SN', 'TF', 'JP']
      const missingDimensions = expectedDimensions.filter(dim => !dimensionCounts[dim])
      if (missingDimensions.length > 0) {
        ElMessage.warning(`缺少维度：${missingDimensions.join('、')}`)
        return
      }
    }
    
    ElMessage.success(`题库验证通过！共${questions.length}题${selectedBank.value === 'mbti' ? '，维度分布：' + Object.entries(dimensionCounts).map(([dim, count]) => `${dim}:${count}`).join(' ') : ''}`)
  } catch {
    // 用户取消
  }
}

// 切换选项显示
const toggleOptions = () => {
  showOptions.value = !showOptions.value
}

// ── 编辑 / 新增 ──
const editDialogVisible = ref(false)
const isEditing = ref(false)
const saving = ref(false)

const editForm = reactive({
  id: 0,
  type: 'mbti',
  question: '',
  dimension: '',
  options: [] as { value: string; text: string }[],
  sort: 0
})

const openAdd = () => {
  isEditing.value = false
  editForm.id = 0
  editForm.type = selectedBank.value
  editForm.question = ''
  editForm.dimension = ''
  editForm.options = [
    { value: 'A', text: '' },
    { value: 'B', text: '' }
  ]
  editForm.sort = 0
  editDialogVisible.value = true
}

const openEdit = (q: any) => {
  isEditing.value = true
  editForm.id = q.id
  editForm.type = selectedBank.value
  editForm.question = q.question || ''
  editForm.dimension = q.dimension || ''
  editForm.options = (q.options || []).map((o: any) => ({
    value: String(o.value ?? o.key ?? ''),
    text: String(o.text ?? '')
  }))
  if (editForm.options.length === 0) {
    editForm.options = [{ value: 'A', text: '' }, { value: 'B', text: '' }]
  }
  editForm.sort = q.sort ?? 0
  editDialogVisible.value = true
}

const addOption = () => {
  const nextVal = String.fromCharCode(65 + editForm.options.length)
  editForm.options.push({ value: nextVal, text: '' })
}

const removeOption = (idx: number) => {
  editForm.options.splice(idx, 1)
}

const saveQuestion = async () => {
  if (!editForm.question.trim()) {
    ElMessage.error('题目内容不能为空')
    return
  }
  if (editForm.options.some(o => !o.text.trim())) {
    ElMessage.error('选项文字不能为空')
    return
  }
  saving.value = true
  try {
    const payload: any = {
      type: editForm.type,
      question: editForm.question.trim(),
      options: editForm.options,
      sort: editForm.sort
    }
    if (editForm.type === 'mbti') payload.dimension = editForm.dimension

    if (isEditing.value) {
      await request.put(`/admin/questions/${editForm.id}`, payload)
      ElMessage.success('题目已更新')
    } else {
      await request.post('/admin/questions', payload)
      ElMessage.success('题目已创建')
    }
    editDialogVisible.value = false
    await refresh()
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

const deleteQuestion = async (q: any) => {
  try {
    await ElMessageBox.confirm(`确定要删除第 ${q.id} 题「${q.question?.slice(0, 20)}…」吗？`, '删除确认', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
      confirmButtonClass: 'el-button--danger'
    })
    await request.delete(`/admin/questions/${q.id}`)
    ElMessage.success('题目已删除')
    await refresh()
  } catch {
    // 取消
  }
}

// 初始化
onMounted(async () => {
  await loadAllBankStats()
  await loadQuestions()
})
</script>

<style scoped lang="scss">
.page-header {
  display: flex;
  align-items: center;
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
    .el-button {
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 8px 12px;
      font-size: 13px;
      color: #374151;
      height: 34px;
      display: flex;
      align-items: center;
      gap: 6px;
      
      &:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
      }
    }
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.test-type-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 12px;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  position: relative;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }

  &.active {
    border: 1.5px solid #7c3aed;
    background-color: #fff;
    
    &::after {
      content: '';
      position: absolute;
      inset: -1.5px;
      border-radius: 12px;
      border: 1.5px solid #7c3aed;
      pointer-events: none;
    }
  }
  
  .card-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    
    .type-info {
      display: flex; gap: 12px; align-items: center; margin-bottom: 16px;
      .icon-box {
        width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;
        &.purple { background-color: #faf5ff; color: #a855f7; }
        &.blue { background-color: #eff6ff; color: #3b82f6; }
        &.green { background-color: #f0fdf4; color: #22c55e; }
      }
      .text {
        .name { font-weight: 700; color: #111827; margin: 0; font-size: 15px; }
        .desc { font-size: 12px; color: #9ca3af; margin: 2px 0 0; }
      }
    }

    .count-info {
      display: flex; justify-content: space-between; align-items: center;
      margin-top: auto;
      .count { font-size: 13px; color: #6b7280; span { font-size: 22px; font-weight: 700; color: #111827; margin-right: 4px; } }
    }

    .dimensions {
      display: flex; gap: 4px; margin-top: 12px; flex-wrap: wrap;
      min-height: 24px;
      
      &.empty {
        visibility: hidden;
      }

      .dim-tag { 
        font-size: 10px; 
        padding: 0 6px; 
        border-radius: 4px;
        background-color: #f9fafb;
        border: 1px solid #f3f4f6;
        color: #6b7280;
      }
    }
  }
}

.list-card {
  border: 1px solid #f3f4f6;
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  
  :deep(.el-card__header) {
    background-color: #fff;
    border-bottom: 1px solid #f3f4f6;
    padding: 16px 20px;
  }
  
  .list-header {
    display: flex; justify-content: space-between; align-items: center;
    .info {
      display: flex; align-items: center; gap: 8px;
      strong { font-size: 16px; color: #111827; font-weight: 700; }
      .count { font-size: 13px; color: #6b7280; }
      .purple-text { color: #a855f7; }
      .blue-text { color: #3b82f6; }
      .green-text { color: #22c55e; }
    }
    .actions { 
      display: flex; 
      gap: 8px; 
      
      .el-button {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        color: #374151;
        height: 32px;
        padding: 0 12px;
        
        &:hover {
          background-color: #f9fafb;
          border-color: #d1d5db;
        }
      }
    }
  }
}

.toolbar {
  padding: 16px 20px; 
  display: flex; 
  justify-content: space-between; 
  align-items: center;
  border-bottom: 1px solid #f3f4f6;
  background-color: #fafafa;
  
  .search-input { 
    max-width: 400px; 
    
    :deep(.el-input__wrapper) {
      border-radius: 6px;
      background-color: #fff;
      box-shadow: none;
      border: 1px solid #e5e7eb;
      
      &.is-focus {
        border-color: #7c3aed;
      }
    }
  }
  
  .expand-btn { 
    color: #6b7280; 
    font-size: 13px;
    padding: 6px 12px;
    transition: all 0.2s;
    
    &:hover {
      color: #7c3aed;
      background-color: #f5f3ff;
    }
  }
}

.question-list {
  max-height: 500px; overflow-y: auto; padding: 12px 20px;
  
  .empty-state {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
  }
  
    .question-item {
    display: flex; 
    gap: 12px; 
    padding: 12px; 
    border: 1px solid #f3f4f6; 
    border-radius: 8px; 
    margin-bottom: 8px;
    background-color: #fff;
    transition: all 0.2s;
    align-items: flex-start;
    
    &:hover { 
      background-color: #f9fafb; 
      border-color: #e5e7eb;

      .item-actions { opacity: 1; }
    }
    
    .index { 
      width: 32px; 
      height: 32px; 
      background-color: #f3f4f6; 
      border-radius: 8px; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-size: 13px; 
      font-weight: 700; 
      color: #6b7280; 
      flex-shrink: 0; 
    }
    
    .content {
      flex: 1;
      
      .text { 
        font-size: 14px; 
        color: #111827; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        margin-bottom: 8px;
        font-weight: 500;
      }
      
      .dim-badge { 
        font-size: 10px; 
        padding: 0 6px;
        background-color: #f3e8ff;
        border: 1px solid #e9d5ff;
        color: #7c3aed;
      }
      
      .options {
        display: flex; 
        gap: 12px; 
        flex-wrap: wrap;
        transition: all 0.3s;
        overflow: hidden;
        
        .opt { 
          font-size: 12px; 
          color: #6b7280; 
          background-color: #f9fafb; 
          padding: 6px 10px; 
          border-radius: 6px;
          border: 1px solid #f3f4f6;
          transition: all 0.2s;
          
          strong { 
            color: #111827; 
            margin-right: 6px;
            font-weight: 600;
          }
          
          &:hover {
            background-color: #f5f3ff;
            border-color: #e9d5ff;
          }
        }
        
        .empty-option {
          color: #9ca3af;
          font-size: 12px;
          font-style: italic;
        }
      }
    }
  }
}

.list-footer { 
  padding: 16px 20px; 
  border-top: 1px solid #f3f4f6; 
  font-size: 13px; 
  color: #6b7280; 
  display: flex; 
  justify-content: space-between;
  background-color: #fafafa;
  
  .size {
    color: #9ca3af;
  }
}

.resources {
  .res-title { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 16px; display: flex; align-items: center; }
  .res-card {
    padding: 20px; border-radius: 12px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;

    &.purple { background-color: #faf5ff; .name { color: #7e22ce; } .link, .stat { color: #9333ea; } }
    &.blue { background-color: #eff6ff; .name { color: #1d4ed8; } .link, .stat { color: #2563eb; } }
    &.green { background-color: #f0fdf4; .name { color: #15803d; } .link, .stat { color: #16a34a; } }
    
    .name { font-size: 15px; font-weight: 700; margin: 0 0 8px; }
    .link { font-size: 13px; margin: 2px 0; opacity: 0.9; line-height: 1.6; }
    .stat { font-size: 13px; font-weight: 600; margin: 8px 0 0; opacity: 1; }
  }
  .res-tip { font-size: 12px; color: #9ca3af; margin-top: 16px; }
}

.item-actions {
  display: flex;
  gap: 2px;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.15s;
  padding-top: 2px;
}

.option-editor {
  width: 100%;

  .option-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 6px;

    .opt-value { width: 80px; flex-shrink: 0; }
    .opt-text  { flex: 1; }
  }
}

.mt-6 { margin-top: 24px; }
.mr-1 { margin-right: 4px; }
</style>

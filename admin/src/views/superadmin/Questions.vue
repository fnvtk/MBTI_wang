<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <div v-if="!embedded" class="page-header">
      <div class="header-left">
        <h2>题库管理</h2>
        <p class="subtitle">管理全局题库，包括 MBTI、SBTI、DISC、PDP 四套测试题库</p>
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
        v-for="(bank, _index) in questionBanks"
        :key="bank.type"
        :class="['test-type-card', { active: selectedBank === bank.type }]"
        @click="selectBank(bank.type)"
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
            <el-tag v-if="bank.count > 0" size="small" type="success" effect="light">已验证</el-tag>
            <el-tag v-else size="small" type="info" effect="light">空题库</el-tag>
          </div>
          <div :class="['dimensions', { empty: !bank.dimensions || bank.dimensions.length === 0 }]">
            <el-tag
              v-for="d in bank.dimensions"
              :key="d"
              size="small"
              effect="plain"
              class="dim-tag"
            >{{ d }}</el-tag>
          </div>
        </div>
      </div>
    </div>

    <!-- 题目列表 -->
    <div class="content-card">
      <div class="list-header">
        <div class="info">
          <el-icon :class="['mr-1', currentBankInfo.color + '-text']">
            <component :is="currentBankInfo.icon" />
          </el-icon>
          <strong>{{ currentBankInfo.name }} 题库管理</strong>
          <span class="count">共 {{ currentBankInfo.count }} 题 · 最后更新: {{ currentBankInfo.lastUpdate }}</span>
        </div>
        <div class="actions">
          <input 
            ref="fileInput" 
            type="file" 
            accept=".json" 
            @change="handleImport" 
            style="display: none"
          />
          <el-button size="small" type="primary" @click="openAdd">
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
              <el-tag
                v-if="question.dimension"
                size="small"
                effect="plain"
                class="dim-badge"
              >{{ question.dimension }}</el-tag>
            </div>
            <div v-show="showOptions" class="options">
              <span
                v-for="(option, optIndex) in (question.options || [])"
                :key="optIndex"
                class="opt"
              >
                <strong v-if="option.value">{{ option.value }}</strong> {{ option.text || option }}
              </span>
              <span v-if="!question.options || question.options.length === 0" class="opt empty-option">
                暂无选项
              </span>
            </div>
          </div>
          <div class="item-actions">
            <el-button size="small" text @click="openEdit(question)"><el-icon><Edit /></el-icon></el-button>
            <el-button size="small" text type="danger" @click="deleteQuestion(question)"><el-icon><Delete /></el-icon></el-button>
          </div>
        </div>
      </div>
      
      <div class="list-footer">
        <div>
          显示 {{ currentQuestions.length }} / {{ currentBankInfo.count }} 题
        </div>
        <div v-if="total > pageSize" class="pagination-info">
          <el-pagination
            v-model:current-page="currentPage"
            :page-size="pageSize"
            :total="total"
            layout="prev, pager, next"
            small
            @current-change="loadQuestions"
          />
        </div>
      </div>
    </div>

    <!-- 编辑 / 新增弹窗 -->
    <el-dialog
      v-model="editDialogVisible"
      :title="isEditing ? '编辑题目' : '新增题目'"
      width="640px"
      destroy-on-close
    >
      <el-form :model="editForm" label-position="top">
        <el-form-item label="题型" v-if="!isEditing">
          <el-radio-group v-model="editForm.type">
            <el-radio-button value="mbti">MBTI</el-radio-button>
            <el-radio-button value="sbti">SBTI</el-radio-button>
            <el-radio-button value="disc">DISC</el-radio-button>
            <el-radio-button value="pdp">PDP</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="题目内容">
          <el-input v-model="editForm.question" type="textarea" :rows="3" placeholder="请输入题目内容" />
        </el-form-item>
        <el-form-item label="所属维度" v-if="editForm.type === 'mbti'">
          <el-select v-model="editForm.dimension" placeholder="请选择维度">
            <el-option label="EI（内外向）" value="EI" />
            <el-option label="SN（感觉直觉）" value="SN" />
            <el-option label="TF（思考情感）" value="TF" />
            <el-option label="JP（判断感知）" value="JP" />
          </el-select>
        </el-form-item>
        <el-form-item label="所属维度" v-else-if="editForm.type === 'sbti'">
          <el-select v-model="editForm.dimension" placeholder="请选择 SBTI 维度" filterable>
            <el-option v-for="d in sbtiDimensionValues" :key="d" :label="d" :value="d" />
          </el-select>
        </el-form-item>
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
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort" :min="0" controls-position="right" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveQuestion">
          {{ isEditing ? '保存修改' : '创建题目' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 参考资源 -->
    <div class="resources mt-6">
      <p class="res-title"><el-icon class="mr-1"><InfoFilled /></el-icon>题库参考资源</p>
      <div class="res-grid">
        <div
          v-for="bank in questionBanks"
          :key="bank.type"
          :class="['res-card', bank.color]"
        >
          <p class="name">{{ bank.resourceName }}</p>
          <p
            v-for="(link, linkIndex) in bank.resources"
            :key="linkIndex"
            class="link"
          >{{ link }}</p>
          <p class="stat">{{ bank.resourceStat }}</p>
        </div>
      </div>
      <p class="res-tip">提示：导入格式为 JSON 数组，每题包含 id、question、options 字段。MBTI 需 dimension（EI/SN/TF/JP）；SBTI 需 dimension（如 S1、DG1、DG2 等）。</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Cpu, Aim, MagicStick, ChatDotRound, Upload, Download, CircleCheck, Search, View, InfoFilled, Plus, Edit, Delete } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

/** SBTI 题库维度（与入库脚本一致） */
const sbtiDimensionValues = [
  'S1', 'S2', 'S3', 'E1', 'E2', 'E3', 'A1', 'A2', 'A3', 'Ac1', 'Ac2', 'Ac3', 'So1', 'So2', 'So3', 'DG1', 'DG2'
] as const

const search = ref('')
const fileInput = ref<HTMLInputElement>()
const showOptions = ref(false)
const selectedBank = ref('mbti')
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

// 题库类型配置
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
  },
  {
    type: 'sbti',
    name: 'SBTI',
    desc: '15 维等级 + 闸口题',
    count: 0,
    color: 'amber',
    icon: ChatDotRound,
    dimensions: [] as string[],
    lastUpdate: '',
    fileSize: '0KB',
    resourceName: 'SBTI 标准题库',
    resources: ['维度：S/E/A/Ac/So + DG1/DG2', '与 aisbti 公开页计分一致'],
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
        const response: any = await request.get('/superadmin/questions', {
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
    const response: any = await request.get('/superadmin/questions', {
      params: {
        page: currentPage.value,
        pageSize: pageSize.value,
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
        
        // 计算维度统计（MBTI / SBTI）
        if (selectedBank.value === 'mbti' || selectedBank.value === 'sbti') {
          const dimensionCounts: Record<string, number> = {}
          questions.forEach((q: any) => {
            if (q.dimension) {
              dimensionCounts[q.dimension] = (dimensionCounts[q.dimension] || 0) + 1
            }
          })
          bank.dimensions = Object.entries(dimensionCounts).map(([dim, count]) => `${dim}: ${count}`)
        }
        
        // 更新最后更新时间
        if (questions.length > 0) {
          const latest = questions[0]
          if (latest.updatedAt) {
            const date = new Date(latest.updatedAt * 1000)
            bank.lastUpdate = date.toLocaleString('zh-CN')
          }
        }
      }
      total.value = response.data.total || 0
    }
  } catch (error: any) {
    console.error('加载题库失败:', error)
    ElMessage.error(error.message || '加载题库失败')
  } finally {
    loading.value = false
  }
}

const selectBank = (type: string) => {
  selectedBank.value = type
  search.value = ''
  showOptions.value = false
  currentPage.value = 1
  loadQuestions()
}

const refresh = async () => {
  await loadAllBankStats()
  await loadQuestions()
  ElMessage.success('刷新成功')
}

const triggerImport = () => {
  fileInput.value?.click()
}

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
      
      // 转换数据格式
      const questions = json.map((item: any) => {
        const question: any = {
          type: selectedBank.value,
          question: item.question || item.text || '',
          options: item.options || [],
          sort: item.sort || 0,
          status: item.status !== undefined ? item.status : 1
        }
        
        // MBTI / SBTI 需要 dimension
        if (selectedBank.value === 'mbti' || selectedBank.value === 'sbti') {
          question.dimension = item.dimension || ''
        }
        
        return question
      })
      
      // 调用批量导入接口
      loading.value = true
      try {
        const response: any = await request.post('/superadmin/questions/batch-import', {
          questions
        })
        
        if (response.code === 200) {
          ElMessage.success(`成功导入 ${response.data.successCount || 0} 道题目`)
          if (response.data.failCount > 0) {
            ElMessage.warning(`失败 ${response.data.failCount} 道题目`)
          }
          await loadQuestions()
        }
      } catch (error: any) {
        ElMessage.error(error.message || '导入失败')
      } finally {
        loading.value = false
        if (target) target.value = ''
      }
    } catch (error) {
      ElMessage.error('JSON 格式错误，请检查文件内容')
      console.error('JSON解析错误:', error)
    }
  }
  reader.readAsText(file)
}

const handleExport = () => {
  const bank = questionBanks.find(bank => bank.type === selectedBank.value)
  if (!bank || bank.questions.length === 0) {
    ElMessage.warning('当前没有可导出的题目')
    return
  }
  
  const exportData = bank.questions.map((q: any) => {
    const item: any = {
      id: q.id,
      question: q.question,
      options: Array.isArray(q.options) ? q.options : []
    }
    
    if (q.dimension) {
      item.dimension = q.dimension
    }
    
    return item
  })
  
  const jsonStr = JSON.stringify(exportData, null, 2)
  const blob = new Blob([jsonStr], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${bank.type}-questions-${new Date().toISOString().split('T')[0]}.json`
  a.click()
  URL.revokeObjectURL(url)
  ElMessage.success(`${bank.name} 题库已导出`)
}

const handleValidate = () => {
  const bank = questionBanks.find(bank => bank.type === selectedBank.value)
  if (!bank) return
  
  if (bank.questions.length === 0) {
    ElMessage.warning('当前题库为空，无法验证')
    return
  }
  
  ElMessageBox.confirm('确定要验证题库吗？', '验证题库', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'info'
  }).then(() => {
    // 验证逻辑
    let isValid = true
    let errors: string[] = []
    
    bank.questions.forEach((q: any, index: number) => {
      if (!q.question || q.question.trim() === '') {
        isValid = false
        errors.push(`第${index + 1}题：题目内容为空`)
      }
      
      if (!q.options || !Array.isArray(q.options) || q.options.length === 0) {
        isValid = false
        errors.push(`第${index + 1}题：选项为空`)
      }
      
      if ((selectedBank.value === 'mbti' || selectedBank.value === 'sbti') && !q.dimension) {
        isValid = false
        errors.push(`第${index + 1}题：缺少维度信息`)
      }
    })
    
    if (isValid) {
      ElMessage.success(`${bank.name} 题库验证通过：共 ${bank.count} 题，所有题目格式正确`)
    } else {
      ElMessage.error(`题库验证失败：${errors.slice(0, 3).join('；')}${errors.length > 3 ? '...' : ''}`)
    }
  }).catch(() => {})
}

// 监听题库类型变化
watch(selectedBank, () => {
  currentPage.value = 1
  loadQuestions()
})

// 组件挂载时加载数据
onMounted(async () => {
  // 先加载所有题库的统计信息
  await loadAllBankStats()
  // 然后加载当前选中题库的详细数据
  await loadQuestions()
})

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
  if ((editForm.type === 'mbti' || editForm.type === 'sbti') && !String(editForm.dimension || '').trim()) {
    ElMessage.error('请选择所属维度')
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
    if (editForm.type === 'mbti' || editForm.type === 'sbti') payload.dimension = editForm.dimension

    if (isEditing.value) {
      await request.put(`/superadmin/questions/${editForm.id}`, payload)
      ElMessage.success('题目已更新')
    } else {
      await request.post('/superadmin/questions', payload)
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
    await ElMessageBox.confirm(
      `确定要删除「${q.question?.slice(0, 20)}…」吗？`,
      '删除确认',
      { confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning' }
    )
    await request.delete(`/superadmin/questions/${q.id}`)
    ElMessage.success('题目已删除')
    await refresh()
  } catch {
    // 取消
  }
}
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

/* 卡片均分：大屏四等分，中屏两等分，小屏单列 */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 24px;
  align-items: stretch;
}

.test-type-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: all 0.2s;
  height: 100%;
  cursor: pointer;

  &:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  &.active {
    border-color: #ef4444;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.1);
    position: relative;
    
    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background-color: #ef4444;
      border-radius: 10px 10px 0 0;
    }
  }

  .card-content {
    .type-info {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;

      .icon-box {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;

        &.purple { background-color: #faf5ff; color: #a855f7; }
        &.blue { background-color: #eff6ff; color: #3b82f6; }
        &.green { background-color: #f0fdf4; color: #22c55e; }
        &.amber { background-color: #fffbeb; color: #d97706; }
      }

      .text {
        flex: 1;
        .name {
          font-size: 16px;
          font-weight: 700;
          color: #111827;
          margin: 0 0 2px 0;
        }
        .desc {
          font-size: 12px;
          color: #6b7280;
          margin: 0;
        }
      }
    }

    .count-info {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;

      .count {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        span {
          font-size: 28px;
        }
      }
    }

    .dimensions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      min-height: 24px;

      &.empty {
        visibility: hidden;
      }

      .dim-tag {
        font-size: 11px;
        padding: 2px 8px;
      }
    }
  }
}

.purple-text {
  color: #a855f7;
}

.blue-text {
  color: #3b82f6;
}

.green-text {
  color: #22c55e;
}

.amber-text {
  color: #d97706;
}

.content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  margin-bottom: 24px;

  .list-header {
    padding: 20px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;

    .info {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: #111827;

      .purple-text {
        color: #a855f7;
      }

      strong {
        font-weight: 600;
      }

      .count {
        color: #6b7280;
        margin-left: 8px;
      }
    }

    .actions {
      display: flex;
      gap: 8px;
    }
  }

  .toolbar {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f3f4f6;
    gap: 16px;

    .search-input {
      flex: 1;
      max-width: 400px;
    }

    .expand-btn {
      color: #6b7280;
      font-size: 13px;
    }
  }

  .question-list {
    padding: 20px;
    max-height: 600px;
    overflow-y: auto;

    .question-item {
      display: flex;
      gap: 16px;
      padding: 16px 0;
      border-bottom: 1px solid #f3f4f6;
      align-items: flex-start;

      &:hover .item-actions { opacity: 1; }

      &:last-child {
        border-bottom: none;
      }

      .index {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background-color: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #6b7280;
        font-size: 13px;
        flex-shrink: 0;
      }

      .content {
        flex: 1;

        .text {
          font-size: 14px;
          color: #111827;
          margin-bottom: 8px;
          display: flex;
          align-items: center;
          gap: 8px;

          .dim-badge {
            font-size: 11px;
            padding: 2px 6px;
          }
        }

        .options {
          display: flex;
          flex-direction: column;
          gap: 6px;
          margin-top: 8px;

          .opt {
            font-size: 13px;
            color: #6b7280;
            padding: 6px 12px;
            background-color: #f9fafb;
            border-radius: 6px;

            strong {
              color: #ef4444;
              margin-right: 6px;
            }
            
            &.empty-option {
              color: #9ca3af;
              font-style: italic;
            }
          }
        }
      }
    }
  }

  .list-footer {
    padding: 16px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: #6b7280;

    .pagination-info {
      display: flex;
      align-items: center;
    }
  }

  .empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #9ca3af;
    font-size: 14px;
  }
}

.resources {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  padding: 24px;

  .res-title {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .res-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 16px;
    align-items: stretch;
  }

  .res-card {
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;

    &.purple { background-color: #faf5ff; border-color: #e9d5ff; }
    &.blue { background-color: #eff6ff; border-color: #dbeafe; }
    &.green { background-color: #f0fdf4; border-color: #bbf7d0; }
    &.amber { background-color: #fffbeb; border-color: #fde68a; }

    .name {
      font-size: 15px;
      font-weight: 600;
      color: #111827;
      margin: 0 0 8px 0;
    }

    .link {
      font-size: 12px;
      color: #6b7280;
      margin: 4px 0;
      line-height: 1.4;
    }

    .stat {
      font-size: 12px;
      color: #9ca3af;
      margin: 8px 0 0 0;
      font-weight: 500;
    }
  }

  .res-tip {
    font-size: 12px;
    color: #9ca3af;
    margin: 16px 0 0 0;
    line-height: 1.5;
  }
}

.item-actions {
  display: flex;
  gap: 2px;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.15s;
  padding-top: 4px;
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

.mt-6 {
  margin-top: 24px;
}

.mr-1 {
  margin-right: 4px;
}

@media (max-width: 1024px) {
  .stats-grid,
  .resources .res-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .stats-grid,
  .resources .res-grid {
    grid-template-columns: 1fr;
  }
}

.page-container.is-embedded {
  min-height: auto;
}
</style>

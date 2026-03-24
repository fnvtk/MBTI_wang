<template>
  <div class="poster-editor-root">
    <!-- 顶部工具栏 -->
    <div class="editor-toolbar">
      <div class="toolbar-group">
        <el-button size="small" @click="addElement('text')">
          <el-icon><EditPen /></el-icon> 添加文字
        </el-button>
        <el-button size="small" @click="addElement('nickname')">
          <el-icon><User /></el-icon> 用户昵称
        </el-button>
        <el-button size="small" @click="addElement('avatar')">
          <el-icon><Avatar /></el-icon> 用户头像
        </el-button>
        <el-button size="small" @click="addElement('qrcode')">
          <el-icon><Grid /></el-icon> 小程序码
        </el-button>
        <el-button size="small" @click="triggerImageAdd">
          <el-icon><Picture /></el-icon> 上传图片
        </el-button>
        <el-divider direction="vertical" />
        <el-button size="small" type="warning" plain @click="addElement('mbti')">MBTI</el-button>
        <el-button size="small" type="success" plain @click="addElement('pdp')">PDP</el-button>
        <el-button size="small" type="danger" plain @click="addElement('disc')">DISC</el-button>
      </div>
      <div class="toolbar-right">
        <el-button size="small" :disabled="!selectedId" @click="selectedId = null">取消选择</el-button>
        <el-button size="small" @click="loadConfig">重置</el-button>
        <el-button size="small" type="primary" color="#6366f1" :loading="saving" @click="saveConfig">
          保存配置
        </el-button>
      </div>
    </div>

    <!-- 主体：图层 | 画布 | 属性 -->
    <div class="editor-body">
      <!-- 左侧图层面板 -->
      <div class="editor-layers">
        <div class="panel-title">图层</div>
        <div class="layers-list">
          <div
            v-for="el in [...elements].reverse()"
            :key="el.id"
            :class="['layer-item', { active: selectedId === el.id }]"
            @click="selectedId = el.id"
          >
            <el-icon class="layer-icon">
              <component :is="typeIconMap[el.type]" />
            </el-icon>
            <span class="layer-name">{{ typeLabel(el) }}</span>
            <div class="layer-actions">
              <el-icon class="action-icon" @click.stop="moveLayer(el.id, -1)" title="上移"><ArrowUp /></el-icon>
              <el-icon class="action-icon" @click.stop="moveLayer(el.id, 1)" title="下移"><ArrowDown /></el-icon>
              <el-icon class="action-icon danger" @click.stop="removeElement(el.id)"><Delete /></el-icon>
            </div>
          </div>
          <div v-if="elements.length === 0" class="layers-empty">暂无元素</div>
        </div>
      </div>

      <!-- 中间画布 -->
      <div class="editor-canvas-area" @click.self="selectedId = null">
        <div class="canvas-scale-wrap">
          <div
            class="poster-canvas"
            :style="posterCanvasStyle"
            @click.self="selectedId = null"
          >
            <!-- 背景图（点击空白处取消选择） -->
            <img v-if="config.bgImage" :src="config.bgImage" class="canvas-bg-img" @click="selectedId = null" />
            <!-- 无背景图时的可点击底衬 -->
            <div
              v-else
              class="canvas-bg-click"
              @click="selectedId = null"
            />

            <!-- 各元素（configReady 后才渲染，避免组件初始化覆盖数据） -->
            <Vue3DraggableResizable
              v-for="el in elements"
              v-if="configReady"
              :key="`${el.id}_${configVersion}`"
              :initW="el.w"
              :initH="el.h"
              :x="el.x"
              :y="el.y"
              :w="el.w"
              :h="el.h"
              :active="selectedId === el.id"
              :parent="true"
              :draggable="true"
              :resizable="true"
              :min-w="20"
              :min-h="20"
              class-name-active="vdr-active"
              @update:x="el.x = Math.round($event)"
              @update:y="el.y = Math.round($event)"
              @update:w="el.w = Math.round($event)"
              @update:h="el.h = Math.round($event)"
              @activated="selectedId = el.id"
            >
              <!-- 文字 -->
              <div v-if="el.type === 'text'" class="el-text" :style="textElemStyle(el)">
                {{ el.content || '文字内容' }}
              </div>

              <!-- 昵称占位 -->
              <div v-else-if="el.type === 'nickname'" class="el-text el-nickname" :style="textElemStyle(el)">
                用户昵称
              </div>

              <!-- 头像占位 -->
              <div v-else-if="el.type === 'avatar'" class="el-placeholder" :style="shapeElemStyle(el)">
                <el-icon><Avatar /></el-icon>
                <span>头像</span>
              </div>

              <!-- 小程序码占位 -->
              <div v-else-if="el.type === 'qrcode'" class="el-placeholder el-qrcode" :style="shapeElemStyle(el)">
                <el-icon><Grid /></el-icon>
                <span>小程序码</span>
              </div>

              <!-- 上传图片 -->
              <div v-else-if="el.type === 'image'" class="el-placeholder el-image" :style="shapeElemStyle(el)">
                <img v-if="el.url" :src="el.url" :style="{ borderRadius: el.shape === 'circle' ? '50%' : '0', width: '100%', height: '100%', objectFit: 'cover' }" />
                <template v-else>
                  <el-icon><Picture /></el-icon>
                  <span>图片</span>
                </template>
              </div>

              <!-- MBTI 结果 -->
              <div v-else-if="el.type === 'mbti'" class="el-text el-test-result mbti-result" :style="textElemStyle(el)">
                {{ el.content || 'INTJ' }}
              </div>

              <!-- PDP 结果 -->
              <div v-else-if="el.type === 'pdp'" class="el-text el-test-result pdp-result" :style="textElemStyle(el)">
                {{ el.content || '猫头鹰' }}
              </div>

              <!-- DISC 结果 -->
              <div v-else-if="el.type === 'disc'" class="el-text el-test-result disc-result" :style="textElemStyle(el)">
                {{ el.content || 'C型' }}
              </div>
            </Vue3DraggableResizable>
          </div>
        </div>
        <div class="canvas-hint">画布尺寸：375 × 667 px（标准小程序屏幕）</div>
      </div>

      <!-- 右侧属性面板 -->
      <div class="editor-props">
        <!-- 无选中：显示画布设置 -->
        <template v-if="!selectedElement">
          <div class="panel-title">画布设置</div>
          <div class="prop-item">
            <label class="prop-label">背景颜色</label>
            <el-color-picker v-model="config.bgColor" show-alpha />
          </div>
          <div class="prop-item">
            <label class="prop-label">背景图片</label>
            <div v-if="config.bgImage" class="img-preview-row">
              <img :src="config.bgImage" class="img-preview" />
              <el-button size="small" type="danger" plain @click="config.bgImage = ''">清除</el-button>
            </div>
            <el-button size="small" :loading="uploadingBg" @click="triggerBgUpload">
              <el-icon><Upload /></el-icon>
              {{ config.bgImage ? '更换背景' : '上传背景' }}
            </el-button>
          </div>
          <div class="props-tip">点击画布上的元素以编辑属性</div>
        </template>

        <!-- 有选中：显示元素属性 -->
        <template v-else>
          <div class="panel-title">
            {{ typeLabel(selectedElement) }}
            <el-icon class="delete-icon" @click="removeElement(selectedElement.id)" title="删除"><Delete /></el-icon>
          </div>

          <!-- 文字内容（text 类型） -->
          <div v-if="selectedElement.type === 'text'" class="prop-item">
            <label class="prop-label">文字内容</label>
            <el-input v-model="selectedElement.content" type="textarea" :rows="2" placeholder="请输入文字" />
          </div>

          <!-- 占位文字（mbti / pdp / disc） -->
          <div v-if="['mbti','pdp','disc'].includes(selectedElement.type)" class="prop-item">
            <label class="prop-label">预览占位文字</label>
            <el-input v-model="selectedElement.content" placeholder="如：INTJ" size="small" />
            <span class="form-hint">实际生成时自动读取用户最近测试结果</span>
          </div>

          <!-- 字体/字号/颜色/加粗/居中（text / nickname / mbti / pdp / disc） -->
          <template v-if="['text','nickname','mbti','pdp','disc'].includes(selectedElement.type)">
            <div class="prop-item">
              <label class="prop-label">字体</label>
              <el-select
                v-model="selectedElement.fontFamily"
                placeholder="默认字体"
                size="small"
                clearable
              >
                <el-option
                  v-for="f in fontList"
                  :key="f.key"
                  :label="f.name"
                  :value="f.key"
                />
              </el-select>
              <span v-if="serverFontsLoaded && fontList.length === 0" class="form-hint" style="color:#ef4444">
                服务器未安装字体，请上传字体到 public/fonts/
              </span>
              <span v-else-if="selectedElement?.fontFamily && !fontList.find(f => f.key === selectedElement?.fontFamily)" class="form-hint" style="color:#f59e0b">
                当前字体在服务器上不可用，将使用默认字体
              </span>
            </div>
            <div class="prop-item">
              <label class="prop-label">字号 (px)</label>
              <div class="font-size-row">
                <el-slider
                  :model-value="selectedElement.fontSize ?? 16"
                  :min="10"
                  :max="120"
                  :show-tooltip="false"
                  @input="onFontSizeChange"
                  style="flex:1"
                />
                <el-input-number
                  :model-value="selectedElement.fontSize ?? 16"
                  :min="10"
                  :max="200"
                  :step="1"
                  size="small"
                  controls-position="right"
                  @change="onFontSizeChange"
                  style="width:80px;margin-left:8px"
                />
              </div>
            </div>
            <div class="prop-item">
              <label class="prop-label">颜色</label>
              <el-color-picker v-model="selectedElement.color" show-alpha />
            </div>
            <div class="prop-item inline">
              <label class="prop-label">加粗</label>
              <el-switch v-model="selectedElement.bold" />
            </div>
            <div class="prop-item">
              <label class="prop-label">对齐</label>
              <el-radio-group v-model="selectedElement.align" size="small">
                <el-radio-button value="left">左对齐</el-radio-button>
                <el-radio-button value="center">居中</el-radio-button>
                <el-radio-button value="right">右对齐</el-radio-button>
              </el-radio-group>
            </div>
          </template>

          <!-- 形状（avatar / image） -->
          <template v-if="['avatar','image'].includes(selectedElement.type)">
            <div class="prop-item">
              <label class="prop-label">形状</label>
              <el-radio-group v-model="selectedElement.shape" size="small">
                <el-radio-button label="circle">圆形</el-radio-button>
                <el-radio-button label="square">正方形</el-radio-button>
              </el-radio-group>
            </div>
          </template>

          <!-- 图片上传（image） -->
          <div v-if="selectedElement.type === 'image'" class="prop-item">
            <label class="prop-label">图片</label>
            <div v-if="selectedElement.url" class="img-preview-row">
              <img :src="selectedElement.url" class="img-preview" />
              <el-button size="small" type="danger" plain @click="selectedElement.url = ''">清除</el-button>
            </div>
            <el-button size="small" :loading="uploadingImg" @click="triggerElementImageUpload">
              <el-icon><Upload /></el-icon>
              {{ selectedElement.url ? '更换图片' : '上传图片' }}
            </el-button>
          </div>

          <!-- 位置与大小 -->
          <div class="props-section-title">位置与大小</div>
          <div class="prop-row">
            <div class="prop-item half">
              <label class="prop-label">X</label>
              <el-input-number v-model="selectedElement.x" :min="0" size="small" controls-position="right" />
            </div>
            <div class="prop-item half">
              <label class="prop-label">Y</label>
              <el-input-number v-model="selectedElement.y" :min="0" size="small" controls-position="right" />
            </div>
          </div>
          <div class="prop-row">
            <div class="prop-item half">
              <label class="prop-label">宽</label>
              <el-input-number v-model="selectedElement.w" :min="20" size="small" controls-position="right" />
            </div>
            <div class="prop-item half">
              <label class="prop-label">高</label>
              <el-input-number v-model="selectedElement.h" :min="20" size="small" controls-position="right" />
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- 隐藏文件输入 -->
    <input ref="bgInputRef" type="file" accept="image/*" style="display:none" @change="onBgFileChange" />
    <input ref="imgInputRef" type="file" accept="image/*" style="display:none" @change="onImgFileChange" />
    <input ref="addImgInputRef" type="file" accept="image/*" style="display:none" @change="onAddImgFileChange" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import Vue3DraggableResizable from 'vue3-draggable-resizable'
import 'vue3-draggable-resizable/dist/Vue3DraggableResizable.css'
import {
  EditPen, User, Avatar, Grid, Picture, Delete, Upload,
  ArrowUp, ArrowDown, TrophyBase, Odometer, DataAnalysis
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import axios from 'axios'

// ────── 类型 ──────
type ElemType = 'text' | 'nickname' | 'avatar' | 'qrcode' | 'image' | 'mbti' | 'pdp' | 'disc'
type Shape = 'circle' | 'square'
type TextAlign = 'left' | 'center' | 'right'

interface PosterElement {
  id: string
  type: ElemType
  x: number
  y: number
  w: number
  h: number
  // text / nickname
  content?: string
  fontSize?: number
  color?: string
  bold?: boolean
  align?: TextAlign
  fontFamily?: string
  // avatar / image
  shape?: Shape
  // image only
  url?: string
}

interface FontItem {
  key: string
  name: string
  available?: boolean
}

interface PosterConfig {
  bgColor: string
  bgImage: string
  elements: PosterElement[]
}

// ────── 状态 ──────
const saving = ref(false)
const uploadingBg = ref(false)
const uploadingImg = ref(false)
const selectedId = ref<string | null>(null)
const configReady = ref(false)
const configVersion = ref(0)
const fontList = ref<FontItem[]>([])
const serverFontsLoaded = ref(false)

const bgInputRef = ref<HTMLInputElement | null>(null)
const imgInputRef = ref<HTMLInputElement | null>(null)
const addImgInputRef = ref<HTMLInputElement | null>(null)

const config = reactive<PosterConfig>({
  bgColor: '#ffffff',
  bgImage: '',
  elements: []
})

const elements = computed(() => config.elements)

const selectedElement = computed(() =>
  selectedId.value ? config.elements.find(e => e.id === selectedId.value) ?? null : null
)

// ────── 工具映射 ──────
const typeIconMap: Record<ElemType, any> = {
  text: EditPen,
  nickname: User,
  avatar: Avatar,
  qrcode: Grid,
  image: Picture,
  mbti: TrophyBase,
  pdp: Odometer,
  disc: DataAnalysis
}

const typeLabel = (el: PosterElement) => {
  const labels: Record<ElemType, string> = {
    text: `文字：${el.content?.slice(0, 6) || '文字内容'}`,
    nickname: '用户昵称',
    avatar: '用户头像',
    qrcode: '小程序码',
    image: '图片',
    mbti: `MBTI：${el.content || 'INTJ'}`,
    pdp: `PDP：${el.content || '猫头鹰'}`,
    disc: `DISC：${el.content || 'C型'}`
  }
  return labels[el.type]
}

// ────── 画布样式 ──────
const posterCanvasStyle = computed(() => ({
  width: '375px',
  height: '667px',
  backgroundColor: config.bgColor,
  position: 'relative' as const,
  overflow: 'hidden',
  flexShrink: 0
}))

// ────── 字体 CSS 映射（用于画布预览近似渲染） ──────
const fontCssMap: Record<string, string> = {
  'noto-sans':    '"Noto Sans SC", "Source Han Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif',
  'noto-serif':   '"Noto Serif SC", "Source Han Serif SC", "STSong", "SimSun", serif',
  'alimama':      '"AlimamaFangYuanTiVF", "PingFang SC", "Microsoft YaHei", sans-serif',
  'wqy-microhei': '"WenQuanYi Micro Hei", "Noto Sans SC", "Microsoft YaHei", sans-serif',
  'simhei':       '"SimHei", "Heiti SC", sans-serif',
  'msyh':         '"Microsoft YaHei", "PingFang SC", sans-serif',
}

const getFontCss = (fontKey?: string) => {
  if (!fontKey) return undefined
  return fontCssMap[fontKey] ?? undefined
}

// ────── 元素样式 ──────
const alignToJustify: Record<TextAlign, string> = {
  left: 'flex-start',
  center: 'center',
  right: 'flex-end'
}

const textElemStyle = (el: PosterElement) => {
  const align = el.align ?? 'left'
  return {
    fontSize: `${el.fontSize ?? 16}px`,
    color: el.color ?? '#333333',
    fontWeight: el.bold ? 'bold' : 'normal',
    textAlign: align,
    justifyContent: alignToJustify[align],
    fontFamily: getFontCss(el.fontFamily),
    width: '100%',
    height: '100%',
    display: 'flex',
    alignItems: 'center',
    padding: '2px 4px',
    boxSizing: 'border-box' as const,
    wordBreak: 'break-all' as const,
    lineHeight: 1.4,
    userSelect: 'none' as const
  }
}

const shapeElemStyle = (el: PosterElement) => ({
  width: '100%',
  height: '100%',
  borderRadius: el.shape === 'circle' ? '50%' : '4px',
  overflow: 'hidden'
})

// ────── 添加元素 ──────
let idCounter = 1
const genId = () => `el_${Date.now()}_${idCounter++}`

const addElement = (type: ElemType) => {
  const defaults: Record<ElemType, Partial<PosterElement>> = {
    text: { w: 160, h: 40, content: '文字内容', fontSize: 18, color: '#333333', bold: false, align: 'left' },
    nickname: { w: 150, h: 36, fontSize: 16, color: '#333333', bold: false, align: 'left' },
    avatar: { w: 80, h: 80, shape: 'circle' },
    qrcode: { w: 120, h: 120 },
    image: { w: 100, h: 100, shape: 'square', url: '' },
    mbti: { w: 80, h: 32, content: 'INTJ', fontSize: 16, color: '#6366f1', bold: true, align: 'center' },
    pdp: { w: 80, h: 32, content: '猫头鹰', fontSize: 16, color: '#10b981', bold: true, align: 'center' },
    disc: { w: 80, h: 32, content: 'C型', fontSize: 16, color: '#f43f5e', bold: true, align: 'center' }
  }
  const el: PosterElement = {
    id: genId(),
    type,
    x: 100,
    y: 100,
    w: 100,
    h: 100,
    ...defaults[type]
  }
  config.elements.push(el)
  selectedId.value = el.id
}

// ────── 属性修改 ──────
const onFontSizeChange = (val: number | number[] | undefined) => {
  const el = selectedElement.value
  const v = Array.isArray(val) ? val[0] : val
  if (el && typeof v === 'number' && v >= 10) {
    el.fontSize = v
  }
}

// ────── 删除/移动图层 ──────
const removeElement = (id: string) => {
  const idx = config.elements.findIndex(e => e.id === id)
  if (idx !== -1) config.elements.splice(idx, 1)
  if (selectedId.value === id) selectedId.value = null
}

const moveLayer = (id: string, dir: -1 | 1) => {
  const arr = config.elements
  const idx = arr.findIndex(e => e.id === id)
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= arr.length) return
  const tmp = arr[idx]
  arr[idx] = arr[newIdx]
  arr[newIdx] = tmp
}

// ────── 上传工具 ──────
const uploadFile = async (file: File): Promise<string> => {
  const formData = new FormData()
  formData.append('file', file)
  const token = localStorage.getItem('authToken')
  const baseURL = import.meta.env.VITE_API_BASE_URL
    ? (import.meta.env.VITE_API_BASE_URL.endsWith('/')
      ? `${import.meta.env.VITE_API_BASE_URL}api/v1`
      : `${import.meta.env.VITE_API_BASE_URL}/api/v1`)
    : '/api/v1'
  const res = await axios.post(`${baseURL}/superadmin/upload/image`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      ...(token ? { Authorization: `Bearer ${token}` } : {})
    }
  })
  if (res.data?.data?.url) return res.data.data.url
  throw new Error(res.data?.message || '上传失败')
}

// 背景上传
const triggerBgUpload = () => bgInputRef.value?.click()

const onBgFileChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploadingBg.value = true
  try {
    config.bgImage = await uploadFile(file)
    ElMessage.success('背景图片上传成功')
  } catch (err: any) {
    ElMessage.error(err.message || '上传失败')
  } finally {
    uploadingBg.value = false
    if (bgInputRef.value) bgInputRef.value.value = ''
  }
}

// 图片元素上传（更换已有图片元素）
const triggerElementImageUpload = () => imgInputRef.value?.click()

const onImgFileChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !selectedElement.value) return
  uploadingImg.value = true
  try {
    selectedElement.value.url = await uploadFile(file)
    ElMessage.success('图片上传成功')
  } catch (err: any) {
    ElMessage.error(err.message || '上传失败')
  } finally {
    uploadingImg.value = false
    if (imgInputRef.value) imgInputRef.value.value = ''
  }
}

// 添加图片元素时先上传
const triggerImageAdd = () => addImgInputRef.value?.click()

const onAddImgFileChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploadingImg.value = true
  try {
    const url = await uploadFile(file)
    const el: PosterElement = {
      id: genId(),
      type: 'image',
      x: 100,
      y: 100,
      w: 120,
      h: 120,
      shape: 'square',
      url
    }
    config.elements.push(el)
    selectedId.value = el.id
    ElMessage.success('图片上传成功')
  } catch (err: any) {
    ElMessage.error(err.message || '上传失败')
  } finally {
    uploadingImg.value = false
    if (addImgInputRef.value) addImgInputRef.value.value = ''
  }
}

// ────── 读取 / 保存配置 ──────
const loadConfig = async () => {
  configReady.value = false
  selectedId.value = null
  try {
    const res = await request.get<any>('/superadmin/settings/poster')
    const data = res?.data?.poster
    if (data) {
      config.bgColor = data.bgColor ?? '#ffffff'
      config.bgImage = data.bgImage ?? ''
      config.elements = (data.elements ?? []).map((el: any) => {
        // 兼容旧数据：center: true → align: 'center'
        const align = el.align ?? (el.center ? 'center' : 'left')
        const { center: _center, ...rest } = el
        return {
          ...rest,
          align,
          x: Number(el.x) || 0,
          y: Number(el.y) || 0,
          w: Math.max(20, Number(el.w) || 100),
          h: Math.max(20, Number(el.h) || 100),
        }
      })
    }
  } catch {
    // 未配置时静默失败
  }
  configVersion.value++
  await nextTick()
  configReady.value = true
}

const saveConfig = async () => {
  saving.value = true
  try {
    await request.put('/superadmin/settings/poster', {
      bgColor: config.bgColor,
      bgImage: config.bgImage,
      elements: config.elements
    })
    ElMessage.success('海报配置已保存')
  } catch (err: any) {
    ElMessage.error(err.message || '保存失败')
  } finally {
    saving.value = false
  }
}

const loadFonts = async () => {
  try {
    const res = await request.get<any>('/superadmin/settings/fonts')
    const remote = res?.data?.fonts
    serverFontsLoaded.value = true
    if (Array.isArray(remote)) {
      fontList.value = remote
    }
  } catch {
    serverFontsLoaded.value = false
    fontList.value = [
      { key: 'noto-sans', name: '思源黑体' },
      { key: 'noto-serif', name: '思源宋体' },
      { key: 'alimama', name: '阿里妈妈方圆体' },
      { key: 'wqy-microhei', name: '文泉驿微米黑' },
    ]
  }
}

onMounted(async () => {
  await Promise.all([loadConfig(), loadFonts()])
})
</script>

<style scoped lang="scss">
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&family=Noto+Serif+SC:wght@400;700&display=swap');

.poster-editor-root {
  display: flex;
  flex-direction: column;
  gap: 12px;
  height: 100%;
}

/* 工具栏 */
.editor-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  gap: 8px;
  flex-wrap: wrap;

  .toolbar-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  .toolbar-right {
    display: flex;
    gap: 6px;
  }
}

/* 主体三栏 */
.editor-body {
  display: grid;
  grid-template-columns: 180px 1fr 260px;
  gap: 12px;
  min-height: 700px;
}

/* 通用面板 */
.editor-layers,
.editor-props {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  overflow-y: auto;
}

.panel-title {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;

  .delete-icon {
    cursor: pointer;
    color: #ef4444;
    font-size: 14px;
    &:hover { color: #dc2626; }
  }
}

/* 图层列表 */
.layers-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.layer-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  border-radius: 6px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s;
  font-size: 12px;
  color: #6b7280;

  &:hover { background: #f3f4f6; border-color: #d1d5db; }
  &.active { background: #ede9fe; border-color: #8b5cf6; color: #6d28d9; }

  .layer-icon { font-size: 13px; flex-shrink: 0; }
  .layer-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  .layer-actions {
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.15s;

    .action-icon {
      font-size: 12px;
      padding: 2px;
      border-radius: 3px;
      cursor: pointer;
      &:hover { background: #e5e7eb; }
      &.danger { color: #ef4444; }
    }
  }

  &:hover .layer-actions { opacity: 1; }
}

.layers-empty {
  text-align: center;
  color: #9ca3af;
  font-size: 12px;
  padding: 20px 0;
}

/* 画布区域 */
.editor-canvas-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px;
  background: #e5e7eb;
  border-radius: 8px;
  overflow: auto;
}

.canvas-scale-wrap {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.poster-canvas {
  position: relative;
  width: 375px;
  height: 667px;
  overflow: hidden;
  user-select: none;
}

.canvas-bg-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 0;
  cursor: default;
}

.canvas-bg-click {
  position: absolute;
  inset: 0;
  z-index: 0;
  cursor: default;
}

.canvas-hint {
  font-size: 11px;
  color: #9ca3af;
}

/* 元素内容 */
.el-text,
.el-nickname {
  width: 100%;
  height: 100%;
  overflow: hidden;
  pointer-events: none;
}

.el-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  background: rgba(99, 102, 241, 0.08);
  border: 1.5px dashed rgba(99, 102, 241, 0.4);
  color: #6366f1;
  font-size: 12px;
  pointer-events: none;

  .el-icon { font-size: 20px; }

  &.el-qrcode {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.4);
    color: #10b981;
  }
}

/* MBTI / PDP / DISC 测试结果占位 */
.el-test-result {
  border: 1.5px dashed rgba(99, 102, 241, 0.3);
  border-radius: 4px;
  justify-content: center;

  &.mbti-result { background: rgba(99, 102, 241, 0.06); }
  &.pdp-result  { background: rgba(16, 185, 129, 0.06); border-color: rgba(16, 185, 129, 0.3); }
  &.disc-result { background: rgba(244, 63, 94, 0.06); border-color: rgba(244, 63, 94, 0.3); }
}

/* vue3-draggable-resizable 激活样式覆盖 */
:deep(.vdr-active) {
  outline: 2px solid #6366f1 !important;
  outline-offset: 1px;
}

:deep(.vdr) {
  z-index: 1;
}

/* 字号行 */
.font-size-row {
  display: flex;
  align-items: center;
}

/* 属性面板 */
.prop-item {
  margin-bottom: 12px;

  &.inline {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &.half {
    flex: 1;

    :deep(.el-input-number) {
      width: 100%;
    }
  }
}

.prop-row {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}

.prop-label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.props-section-title {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin: 14px 0 8px;
  padding-top: 10px;
  border-top: 1px solid #e5e7eb;
}

.props-tip {
  font-size: 12px;
  color: #9ca3af;
  text-align: center;
  padding: 20px 0;
}

.img-preview-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.img-preview {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #e5e7eb;
}

.form-hint {
  display: block;
  font-size: 11px;
  color: #9ca3af;
  margin-top: 4px;
}
</style>

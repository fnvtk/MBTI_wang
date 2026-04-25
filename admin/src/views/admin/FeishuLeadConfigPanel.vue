<template>
  <div class="tab-content" v-loading="loading">
    <div class="content-header">
      <h3>飞书获客推送</h3>
      <p class="content-description">
        参考 Soul 创业派对：支付成功（含 1 元测试、充值）、用户首次授权手机号时，向飞书群机器人推送「新获客」卡片文案；含最近行为（依赖小程序埋点）。
        在飞书群添加自定义机器人，复制 Webhook 地址填入下方。
      </p>
    </div>
    <div class="form-section">
      <div class="form-item row-line">
        <label>启用推送</label>
        <el-switch v-model="form.enabled" />
      </div>
      <div class="form-item">
        <label>飞书 Webhook 地址</label>
        <el-input
          v-model="form.webhookUrl"
          type="textarea"
          :rows="2"
          placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/……"
          class="w-full"
        />
      </div>
      <div class="form-item">
        <label>对接人（展示在卡片上）</label>
        <el-input v-model="form.contactPerson" placeholder="如：卡若" class="w-full" />
      </div>
      <p class="hint">
        幂等：同一订单仅推一次；同一用户首次绑定手机号仅推一次。支付结果可能经「前端 notify」与「查单」两条路径，后台以订单维度去重。
      </p>
    </div>
    <div class="save-actions">
      <el-button type="primary" class="save-btn" :loading="loading" @click="save">
        保存配置
      </el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'

const loading = ref(false)
const form = reactive({
  enabled: false,
  webhookUrl: '',
  contactPerson: '运营'
})

const load = async () => {
  loading.value = true
  try {
    const res: any = await request.get('/admin/settings/feishu-lead')
    if (res.code === 200 && res.data) {
      form.enabled = !!res.data.enabled
      form.webhookUrl = res.data.webhookUrl || ''
      form.contactPerson = res.data.contactPerson || '运营'
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const save = async () => {
  loading.value = true
  try {
    const res: any = await request.put('/admin/settings/feishu-lead', { ...form })
    if (res.code === 200) {
      ElMessage.success('已保存')
    } else {
      ElMessage.error(res.msg || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<style scoped>
.content-header h3 {
  margin: 0 0 8px;
  font-size: 18px;
}
.content-description {
  margin: 0 0 20px;
  color: #64748b;
  font-size: 14px;
  line-height: 1.5;
}
.form-section {
  max-width: 640px;
}
.form-item {
  margin-bottom: 16px;
}
.form-item label {
  display: block;
  margin-bottom: 6px;
  font-size: 14px;
  color: #334155;
}
.row-line {
  display: flex;
  align-items: center;
  gap: 12px;
}
.row-line label {
  margin-bottom: 0;
}
.hint {
  font-size: 13px;
  color: #94a3b8;
  line-height: 1.5;
  margin-top: 8px;
}
.save-actions {
  margin-top: 24px;
}
.w-full {
  width: 100%;
}
</style>

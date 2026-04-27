<template>
  <div class="analyze-page gradient-bg" style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:32px">
    <div style="text-align:center">
      <div style="font-size:52px;margin-bottom:16px">🤖</div>
      <h2 style="color:white;font-size:22px;font-weight:800;margin-bottom:8px">AI 正在综合分析</h2>
      <p style="color:rgba(255,255,255,.8);font-size:14px;line-height:1.6">整合面相特征、成绩数据与 MBTI 性格<br>生成个性化高考志愿方案</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px;width:240px">
      <div v-for="(step, i) in steps" :key="step" style="display:flex;align-items:center;gap:12px">
        <div :style="{ width:'28px',height:'28px',border:'2px solid rgba(255,255,255,.4)',borderTopColor:i<=currentStep?'white':'transparent',borderRadius:'50%',animation: i<=currentStep?'spin .8s linear infinite':'none' }" />
        <span :style="{ color: i<=currentStep?'white':'rgba(255,255,255,.45)', fontSize:'13.5px', fontWeight: i===currentStep?700:400 }">{{ step }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const currentStep = ref(0)
const steps = ['正在读取面相分析结果…', '正在匹配成绩数据…', '性格类型建模中…', 'AI 生成志愿方案…']

onMounted(() => {
  const iv = setInterval(() => {
    if (currentStep.value < steps.length - 1) currentStep.value++
    else { clearInterval(iv); router.replace('/gaokao/result') }
  }, 1000)
})
</script>

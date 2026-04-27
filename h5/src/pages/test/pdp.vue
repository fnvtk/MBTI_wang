<template>
  <QuizEngine test-type="pdp" theme-color="#10B981" api-path="/api/test/pdp/submit" result-storage-key="pdpResult" result-path="/result/pdp"/>
</template>
<script setup lang="ts">
import QuizEngine from './QuizEngine.vue'
</script>

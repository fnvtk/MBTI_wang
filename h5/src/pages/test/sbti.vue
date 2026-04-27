<template>
  <QuizEngine test-type="sbti" theme-color="#F59E0B" api-path="/api/test/sbti/submit" result-storage-key="sbtiResult" result-path="/result/sbti"/>
</template>
<script setup lang="ts">
import QuizEngine from './QuizEngine.vue'
</script>

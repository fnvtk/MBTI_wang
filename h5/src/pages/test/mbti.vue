<template>
  <QuizEngine
    test-type="mbti"
    theme-color="#6C3EF6"
    api-path="/api/test/mbti/submit"
    result-storage-key="mbtiResult"
    result-path="/result/mbti"
  />
</template>
<script setup lang="ts">
import QuizEngine from './QuizEngine.vue'
</script>

<template>
  <QuizEngine test-type="disc" theme-color="#06B6D4" api-path="/api/test/disc/submit" result-storage-key="discResult" result-path="/result/disc"/>
</template>
<script setup lang="ts">
import QuizEngine from './QuizEngine.vue'
</script>

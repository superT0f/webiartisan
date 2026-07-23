<template>
  <div class="quiz-game">
    <p class="quiz-question">{{ content.question }}</p>
    <button
      v-for="(choice, i) in content.choices"
      :key="i"
      type="button"
      class="quiz-choice"
      :class="{ 'quiz-choice--good': reveal !== null && i === reveal, 'quiz-choice--bad': reveal !== null && picked === i && i !== reveal }"
      :disabled="reveal !== null"
      @click="$emit('answer', { answer_index: i }); picked = i"
    >{{ choice }}</button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
defineProps({
  content: { type: Object, required: true },
  reveal: { type: Number, default: null },
})
defineEmits(['answer'])
const picked = ref(-1)
</script>

<style scoped>
.quiz-game { display: flex; flex-direction: column; gap: 10px; }
.quiz-question { font-size: 1.05rem; font-weight: 600; margin: 0 0 6px; }
.quiz-choice {
  padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px;
  background: #fff; font-size: 0.95rem; text-align: left; cursor: pointer;
}
.quiz-choice:active { transform: scale(0.98); }
.quiz-choice--good { border-color: #10b981; background: #d1fae5; }
.quiz-choice--bad { border-color: #dc2626; background: #fee2e2; }
</style>

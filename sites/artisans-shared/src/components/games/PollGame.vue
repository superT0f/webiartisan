<template>
  <div class="poll-game">
    <h4>{{ config.question }}</h4>
    <div v-if="!result" class="poll-game__options">
      <button
        v-for="opt in config.options"
        :key="opt"
        type="button"
        :disabled="loading"
        @click="choose(opt)"
      >
        {{ opt }}
      </button>
    </div>
    <p v-if="result">Merci pour votre réponse !</p>
    <p v-if="errorMessage" class="poll-game__error">{{ errorMessage }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)
const loading = ref(false)
const errorMessage = ref('')

async function choose(option) {
  errorMessage.value = ''
  loading.value = true
  try {
    const res = await playGame(props.instanceId, { choice: option })
    if (res.success) {
      result.value = res.data
      emit('played', res.data)
    } else {
      errorMessage.value = res.error || 'Une erreur est survenue.'
    }
  } catch (e) {
    errorMessage.value = 'Erreur réseau. Veuillez réessayer.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.poll-game__options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 1rem;
}
.poll-game__options button {
  padding: 0.6rem;
  border: 1px solid #ccc;
  background: #fff;
  border-radius: 0.5rem;
  cursor: pointer;
}
.poll-game__options button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.poll-game__error {
  color: #c62828;
  margin-top: 0.75rem;
  font-size: 0.9rem;
}
</style>

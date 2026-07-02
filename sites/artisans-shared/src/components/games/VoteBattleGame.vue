<template>
  <div class="vote-game">
    <h4>{{ config.question }}</h4>
    <div v-if="!result" class="vote-game__options">
      <button
        v-for="(opt, idx) in config.options"
        :key="idx"
        type="button"
        :disabled="loading"
        @click="vote(opt)"
      >
        {{ opt }}
      </button>
    </div>
    <p v-if="result">Vote enregistré !</p>
    <p v-if="errorMessage" class="vote-game__error">{{ errorMessage }}</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: [String, Number], required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)
const loading = ref(false)
const errorMessage = ref('')

watch(() => props.instanceId, () => {
  result.value = null
  loading.value = false
  errorMessage.value = ''
})

async function vote(option) {
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
.vote-game__options {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
}
.vote-game__options button {
  flex: 1;
  padding: 0.75rem;
  border: 1px solid #ccc;
  background: #fff;
  border-radius: 0.5rem;
  cursor: pointer;
}
.vote-game__options button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.vote-game__error {
  color: #c62828;
  margin-top: 0.75rem;
  font-size: 0.9rem;
}
</style>

<template>
  <div class="coupon-game">
    <p class="coupon-game__intro">{{ config.reveal_text || 'Cliquez pour révéler votre offre' }}</p>
    <button v-if="!result" type="button" class="coupon-game__btn" :disabled="loading" @click="play">
      {{ loading ? 'Chargement…' : 'Révéler' }}
    </button>
    <div v-else class="coupon-game__result">
      <h4>{{ result.reward?.label || 'Merci d\'avoir joué !' }}</h4>
      <p v-if="result.reward?.reward_value?.code">Code : <strong>{{ result.reward.reward_value.code }}</strong></p>
      <button v-if="result.reward" type="button" class="coupon-game__share" @click="$emit('share', result.reward)">
        ↗ Partager mon coupon
      </button>
    </div>
    <p v-if="errorMessage" class="coupon-game__error">{{ errorMessage }}</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: [String, Number], required: true }, config: Object })
const emit = defineEmits(['played', 'share'])

const result = ref(null)
const loading = ref(false)
const errorMessage = ref('')

watch(() => props.instanceId, () => {
  result.value = null
  loading.value = false
  errorMessage.value = ''
})

async function play() {
  errorMessage.value = ''
  loading.value = true
  try {
    const res = await playGame(props.instanceId)
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
.coupon-game {
  text-align: center;
  padding: 1rem;
}
.coupon-game__btn {
  padding: 0.75rem 1.5rem;
  background: var(--c-green);
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  font-size: 1rem;
  cursor: pointer;
}
.coupon-game__btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.coupon-game__result {
  border: 2px dashed var(--c-green);
  padding: 1rem;
  border-radius: 0.5rem;
}
.coupon-game__error {
  color: #c62828;
  margin-top: 0.75rem;
  font-size: 0.9rem;
}
.coupon-game__share {
  margin-top: 0.75rem;
  padding: 0.5rem 1rem;
  background: transparent;
  color: var(--c-green);
  border: 1px solid var(--c-green);
  border-radius: 0.5rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
}
.coupon-game__share:hover { background: rgba(45, 106, 79, 0.08); }
</style>

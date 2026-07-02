<template>
  <div class="coupon-game">
    <p class="coupon-game__intro">{{ config.reveal_text || 'Cliquez pour révéler votre offre' }}</p>
    <button v-if="!result" type="button" class="coupon-game__btn" @click="play">
      Révéler
    </button>
    <div v-else class="coupon-game__result">
      <h4>{{ result.reward?.label || 'Merci d\'avoir joué !' }}</h4>
      <p v-if="result.reward?.reward_value?.code">Code : <strong>{{ result.reward.reward_value.code }}</strong></p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)
const loading = ref(false)

async function play() {
  loading.value = true
  const res = await playGame(props.instanceId)
  loading.value = false
  if (res.success) {
    result.value = res.data
    emit('played', res.data)
  } else {
    alert(res.error || 'Erreur')
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
  background: #2d6a4f;
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  font-size: 1rem;
  cursor: pointer;
}
.coupon-game__result {
  border: 2px dashed #2d6a4f;
  padding: 1rem;
  border-radius: 0.5rem;
}
</style>

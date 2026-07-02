<template>
  <div class="poll-game">
    <h4>{{ config.question }}</h4>
    <div class="poll-game__options">
      <button
        v-for="opt in config.options"
        :key="opt"
        type="button"
        @click="choose(opt)"
      >
        {{ opt }}
      </button>
    </div>
    <p v-if="result">Merci pour votre réponse !</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)

async function choose(option) {
  const res = await playGame(props.instanceId, { choice: option })
  if (res.success) {
    result.value = res.data
    emit('played', res.data)
  } else {
    alert(res.error || 'Erreur')
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
</style>

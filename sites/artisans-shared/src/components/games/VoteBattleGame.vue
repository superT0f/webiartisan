<template>
  <div class="vote-game">
    <h4>{{ config.question }}</h4>
    <div class="vote-game__options">
      <button
        v-for="opt in config.options"
        :key="opt"
        type="button"
        @click="vote(opt)"
      >
        {{ opt }}
      </button>
    </div>
    <p v-if="result">Vote enregistré !</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)

async function vote(option) {
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
</style>

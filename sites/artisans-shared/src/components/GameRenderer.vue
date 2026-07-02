<template>
  <component
    :is="engineComponent"
    v-if="engineComponent"
    :instance-id="instanceId"
    :config="config"
    @played="$emit('played', $event)"
  />
  <p v-else>Moteur de jeu non disponible.</p>
</template>

<script setup>
import { computed } from 'vue'
import CouponGame from './games/CouponGame.vue'
import PollGame from './games/PollGame.vue'
import VoteBattleGame from './games/VoteBattleGame.vue'

const props = defineProps({
  instanceId: { type: Number, required: true },
  gameType: { type: String, required: true },
  config: { type: Object, default: () => ({}) },
})
defineEmits(['played'])

const engines = {
  CouponGame,
  PollGame,
  VoteBattleGame,
}

const engineComponent = computed(() => {
  const key = props.gameType === 'coupon' ? 'CouponGame'
    : props.gameType === 'poll' ? 'PollGame'
    : props.gameType === 'vote' ? 'VoteBattleGame'
    : null
  return engines[key]
})
</script>

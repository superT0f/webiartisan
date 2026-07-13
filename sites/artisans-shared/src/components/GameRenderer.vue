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

const props = defineProps({
  instanceId: { type: [String, Number], required: true },
  gameType: { type: String, required: true },
  config: { type: Object, default: () => ({}) },
})
defineEmits(['played'])

const engineComponent = computed(() => props.gameType === 'coupon' ? CouponGame : null)
</script>

<template>
  <div class="cards-game">
    <p class="cards-hint">Choisis ta carte — le Big Brother révèle la sienne !</p>
    <div class="cards-hand">
      <button
        v-for="(card, i) in content.cards"
        :key="i"
        type="button"
        class="duel-card"
        :class="{ 'duel-card--played': played === i }"
        :disabled="played !== -1"
        @click="played = i; $emit('answer', { card_index: i })"
      >
        <span class="duel-card__value">{{ card.value }}</span>
        <span class="duel-card__element">{{ ELEMENT_EMOJI[card.element] }}</span>
      </button>
    </div>
    <div v-if="reveal" class="cards-boss">
      <small>Le Big Brother avait :</small>
      <span class="duel-card duel-card--boss">
        <span class="duel-card__value">{{ reveal.value }}</span>
        <span class="duel-card__element">{{ ELEMENT_EMOJI[reveal.element] }}</span>
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { ELEMENT_EMOJI } from '../../utils/cardsDuel.js'

defineProps({
  content: { type: Object, required: true },
  reveal: { type: Object, default: null },
})
defineEmits(['answer'])
const played = ref(-1)
</script>

<style scoped>
.cards-game { display: flex; flex-direction: column; align-items: center; gap: 14px; }
.cards-hint { margin: 0; font-size: 0.9rem; }
.cards-hand { display: flex; gap: 12px; }
.duel-card {
  width: 72px; height: 104px;
  background: #fff; border: 2px solid #5b4636; border-radius: 10px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 6px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
  transition: transform 0.15s;
}
.duel-card:active { transform: translateY(-6px); }
.duel-card__value { font-size: 1.6rem; font-weight: 800; }
.duel-card__element { font-size: 1.4rem; }
.duel-card--played { outline: 3px solid #f59e0b; }
.duel-card--boss { border-color: #dc2626; cursor: default; }
.cards-boss { display: flex; flex-direction: column; align-items: center; gap: 6px; }
</style>

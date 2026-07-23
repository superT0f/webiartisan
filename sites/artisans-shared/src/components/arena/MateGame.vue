<template>
  <div class="mate-game">
    <div class="mate-board">
      <div
        v-for="(row, r) in board" :key="r"
        class="mate-row"
      >
        <button
          v-for="(piece, c) in row" :key="c"
          type="button"
          class="mate-cell"
          :class="{ 'mate-cell--dark': (r + c) % 2 === 1, 'mate-cell--selected': selected && selected.row === r && selected.col === c }"
          @click="onCell(r, c)"
        >{{ piece ? PIECE_GLYPHS[piece] : '' }}</button>
      </div>
    </div>
    <p class="mate-hint">Touche une pièce puis sa case d'arrivée — mat en 1 coup !</p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { parseFen, PIECE_GLYPHS, coordsToSquare } from '../../utils/chessBoard.js'

const props = defineProps({
  content: { type: Object, required: true },
})
const emit = defineEmits(['answer'])

const board = computed(() => parseFen(props.content.fen))
const selected = ref(null)

function onCell(row, col) {
  if (!selected.value) {
    if (board.value[row][col]) selected.value = { row, col }
    return
  }
  const from = coordsToSquare(selected.value.row, selected.value.col)
  const to = coordsToSquare(row, col)
  selected.value = null
  if (from !== to) emit('answer', { move: from + to })
}
</script>

<style scoped>
.mate-game { display: flex; flex-direction: column; align-items: center; gap: 10px; }
.mate-board { border: 3px solid #5b4636; border-radius: 8px; overflow: hidden; }
.mate-row { display: flex; }
.mate-cell {
  width: clamp(34px, 9.5vw, 52px);
  aspect-ratio: 1;
  border: none;
  background: #f0d9b5;
  font-size: clamp(1.4rem, 6vw, 2rem);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; padding: 0;
}
.mate-cell--dark { background: #b58863; }
.mate-cell--selected { outline: 3px solid #f59e0b; outline-offset: -3px; }
.mate-hint { font-size: 0.8rem; color: #64748b; margin: 0; }
</style>

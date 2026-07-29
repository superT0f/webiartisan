<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { createSplashScene } from './splashScene.js'

const emit = defineEmits(['done'])
const container = ref(null)
const fading = ref(false)
let scene = null
let closed = false

function close() {
  if (closed) return
  closed = true
  fading.value = true
  setTimeout(() => {
    scene?.destroy()
    emit('done')
  }, 450)
}

onMounted(async () => {
  try {
    scene = await createSplashScene(container.value, { onDone: close })
  } catch (e) {
    close() // Phaser KO → on laisse entrer quand même
  }
})

onUnmounted(() => { scene?.destroy() })
</script>

<template>
  <div class="splash-overlay" :class="{ fading }" @click="close">
    <div ref="container" class="splash-canvas"></div>
    <span class="splash-skip">Toucher pour passer ↗</span>
  </div>
</template>

<style scoped>
.splash-overlay {
  position: fixed;
  inset: 0;
  z-index: 300;
  background: #1a1330;
  transition: opacity 0.45s ease;
}
.splash-overlay.fading { opacity: 0; pointer-events: none; }
.splash-canvas { width: 100%; height: 100%; }
.splash-skip {
  position: absolute;
  bottom: 14px;
  right: 16px;
  color: rgba(255,255,255,0.65);
  font-size: 0.85rem;
  pointer-events: none;
}
</style>

<template>
  <div id="layout">
    <AppNav />
    <main>
      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
    <AppFooter />
    <div class="toast-container" role="status" aria-live="polite">
      <TransitionGroup name="toast">
        <div v-for="t in toasts" :key="t.id" class="toast">{{ t.message }}</div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import AppNav    from './components/AppNav.vue'
import AppFooter from './components/AppFooter.vue'
import { onUnmounted } from 'vue'
import { useGamification } from './composables/useGamification.js'

const { toasts, clearToasts } = useGamification()

onUnmounted(() => {
  clearToasts()
})
</script>

<style>
#layout { display: flex; flex-direction: column; min-height: 100vh; }
main { flex: 1; }
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 1000; display: flex; flex-direction: column; gap: 8px; }
.toast { background: var(--c-text); color: #fff; padding: 10px 16px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>

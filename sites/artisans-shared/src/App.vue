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
  </div>
</template>

<script setup>
import AppNav    from './components/AppNav.vue'
import AppFooter from './components/AppFooter.vue'
</script>

<style>
#layout { display: flex; flex-direction: column; min-height: 100vh; }
main { flex: 1; }
</style>

<template>
  <div>
    <h1>Live Logs</h1>
    <button @click="trigger" :disabled="running">Run suite now</button>
    <pre>{{ logs.join('\n') }}</pre>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { streamLogs } from '../api';

const logs = ref<string[]>([]);
const running = ref(false);
let cleanup = () => {};

onMounted(() => {
  cleanup = streamLogs((data) => {
    logs.value.push(JSON.stringify(data));
    if (logs.value.length > 200) logs.value.shift();
  });
});

onUnmounted(() => cleanup());

async function trigger() {
  running.value = true;
  await fetch('/api/runs/trigger', { method: 'POST' });
  running.value = false;
}
</script>

<template>
  <div>
    <h1>Live Logs</h1>
    <button @click="trigger" :disabled="running">Run suite now</button>
    <pre>{{ logs.join('\n') }}</pre>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { streamLogs, triggerRun } from '../api';

const logs = ref<string[]>([]);
const running = ref(false);
let cleanup = () => {};

const token = localStorage.getItem('e2e_token');

onMounted(() => {
  if (token) {
    cleanup = streamLogs(token, (data) => {
      logs.value.push(JSON.stringify(data));
      if (logs.value.length > 200) logs.value.shift();
    });
  }
});

onUnmounted(() => cleanup());

async function trigger() {
  running.value = true;
  try {
    const t = localStorage.getItem('e2e_token');
    if (t) await triggerRun(t);
  } finally {
    running.value = false;
  }
}
</script>

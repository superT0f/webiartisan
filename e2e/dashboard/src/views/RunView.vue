<template>
  <div v-if="run">
    <h1>Run #{{ run.id }}</h1>
    <pre>{{ JSON.stringify(run, null, 2) }}</pre>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { fetchRun } from '../api';

const route = useRoute();
const run = ref<any>(null);

onMounted(async () => {
  run.value = await fetchRun(Number(route.params.id));
});
</script>

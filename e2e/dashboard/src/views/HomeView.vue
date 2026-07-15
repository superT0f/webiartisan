<template>
  <div>
    <h1>Test Runs</h1>
    <table>
      <thead>
        <tr><th>ID</th><th>Started</th><th>Status</th><th>Passed</th><th>Failed</th></tr>
      </thead>
      <tbody>
        <tr v-for="run in runs" :key="run.id">
          <td><router-link :to="`/runs/${run.id}`">{{ run.id }}</router-link></td>
          <td>{{ run.started_at }}</td>
          <td>{{ run.status }}</td>
          <td>{{ run.passed }}</td>
          <td>{{ run.failed }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { fetchRuns } from '../api';

const runs = ref<any[]>([]);

onMounted(async () => {
  runs.value = await fetchRuns();
});
</script>

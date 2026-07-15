<template>
  <div>
    <nav>
      <router-link to="/">Runs</router-link> |
      <router-link to="/live">Live</router-link>
      <span v-if="token"> | <button @click="logout">Logout</button></span>
    </nav>
    <router-view />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const token = ref<string | null>(null);
const router = useRouter();
const route = useRoute();

function readToken() {
  token.value = localStorage.getItem('e2e_token');
}

onMounted(readToken);
watch(() => route.path, readToken);

function logout() {
  localStorage.removeItem('e2e_token');
  router.push('/login');
}
</script>

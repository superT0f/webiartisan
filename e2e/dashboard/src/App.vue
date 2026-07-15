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
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const token = computed(() => localStorage.getItem('e2e_token'));
const router = useRouter();

function logout() {
  localStorage.removeItem('e2e_token');
  router.push('/login');
}
</script>

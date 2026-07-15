<template>
  <form @submit.prevent="submit">
    <h1>Login E2E Dashboard</h1>
    <input v-model="username" placeholder="Username" />
    <input v-model="password" type="password" placeholder="Password" />
    <button type="submit">Login</button>
    <p v-if="error">{{ error }}</p>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../api';

const username = ref('');
const password = ref('');
const error = ref('');
const router = useRouter();

async function submit() {
  try {
    const token = await login(username.value, password.value);
    localStorage.setItem('e2e_token', token);
    router.push('/');
  } catch (e) {
    error.value = (e as Error).message;
  }
}
</script>

import { createRouter, createWebHistory } from 'vue-router';
import HomeView from './views/HomeView.vue';
import LoginView from './views/LoginView.vue';
import LiveView from './views/LiveView.vue';
import RunView from './views/RunView.vue';

const routes = [
  { path: '/', component: HomeView, meta: { requiresAuth: true } },
  { path: '/login', component: LoginView },
  { path: '/live', component: LiveView, meta: { requiresAuth: true } },
  { path: '/runs/:id', component: RunView, meta: { requiresAuth: true } },
];

const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('e2e_token');
  if (to.meta.requiresAuth && !token) next('/login');
  else next();
});

export default router;

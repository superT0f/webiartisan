import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './style.css'

const routes = [
  { path: '/', component: () => import('./views/Home.vue'), meta: { title: 'Artisans de ' + import.meta.env.VITE_CITY_NAME } },
  { path: '/artisan/:id', component: () => import('./views/Artisan.vue'), meta: { title: 'Fiche artisan' } },
  { path: '/inscrire', component: () => import('./views/Register.vue'), meta: { title: 'Inscrire mon entreprise' } },
  { path: '/espace', component: () => import('./views/Dashboard.vue'), meta: { title: 'Mon espace artisan' } },
  { path: '/flyers', component: () => import('./views/Flyer.vue'), meta: { title: 'Imprimer les flyers / plaquettes' } },
  { path: '/plaquette', redirect: '/flyers' },
  { path: '/:pathMatch(.*)*', redirect: '/' }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 })
})

router.afterEach((to) => {
  document.title = (to.meta.title || 'Artisans Locaux') + ' — ' + import.meta.env.VITE_CITY_NAME + ' ' + import.meta.env.VITE_CITY_CP
})

createApp(App).use(router).mount('#app')

import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './style.css'

const routes = [
  { path: '/', component: () => import('./views/Home.vue'), meta: { title: 'Artisans de ' + import.meta.env.VITE_CITY_NAME } },
  { path: '/artisan/:id', component: () => import('./views/Artisan.vue'), meta: { title: 'Fiche artisan' } },
  { path: '/inscrire', component: () => import('./views/Register.vue'), meta: { title: 'Inscrire mon entreprise' } },
  { path: '/espace', component: () => import('./views/Dashboard.vue'), meta: { title: 'Mon espace artisan' } },
  { path: '/prospection', component: () => import('./views/Prospects.vue'), meta: { title: 'Prospection locale' } },
  { path: '/prospect/:id', component: () => import('./views/ProspectDetail.vue'), meta: { title: 'Fiche prospect' } },
  { path: '/recettes', component: () => import('./views/Recipes.vue'), meta: { title: 'Recettes locales' } },
  { path: '/recette/:slug', component: () => import('./views/RecipeDetail.vue'), meta: { title: 'Fiche recette' } },
  { path: '/recette/nouvelle', component: () => import('./views/RecipeForm.vue'), meta: { title: 'Proposer une recette' } },
  { path: '/recette/:id/suggérer', component: () => import('./views/RecipeForm.vue'), meta: { title: 'Proposer un complément' } },
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

import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './style.css'
import { authUser } from './api.js'
import { consumeTokenFromQuery } from './auth.js'
import { useGamification } from './composables/useGamification.js'

const routes = [
  { path: '/', redirect: '/carte' },
  { path: '/annuaire', component: () => import('./views/Home.vue'), meta: { title: 'Artisans de ' + import.meta.env.VITE_CITY_NAME } },
  { path: '/carte', name: 'Map', component: () => import('./views/MapView.vue'), meta: { title: 'Carte des artisans' } },
  { path: '/artisan/:id', component: () => import('./views/Artisan.vue'), meta: { title: 'Fiche artisan' } },
  { path: '/temoignages', name: 'Testimonials', component: () => import('./views/Testimonials.vue'), meta: { title: 'Avis et recommandations locales' } },
  { path: '/inscrire', component: () => import('./views/Register.vue'), meta: { title: 'Inscrire mon entreprise' } },
  { path: '/espace', component: () => import('./views/Dashboard.vue'), meta: { title: 'Mon espace artisan' } },
  { path: '/artisan/services', component: () => import('./views/artisan/ServicesConfig.vue'), props: true, meta: { title: 'Mes services' } },
  { path: '/artisan/jeux', component: () => import('./views/artisan/GamesConfig.vue'), props: true, meta: { title: 'Mes mini-jeux' } },
  { path: '/espace/quartier', component: () => import('./views/artisan/MyPois.vue'), props: true, meta: { title: 'Mon quartier' } },
  { path: '/prospection', component: () => import('./views/Prospects.vue'), meta: { title: 'Prospection locale' } },
  { path: '/prospect/:id', component: () => import('./views/ProspectDetail.vue'), meta: { title: 'Fiche prospect' } },
  { path: '/recettes', component: () => import('./views/Recipes.vue'), meta: { title: 'Recettes locales' } },
  { path: '/recette/:slug', component: () => import('./views/RecipeDetail.vue'), meta: { title: 'Fiche recette' } },
  { path: '/recette/nouvelle', component: () => import('./views/RecipeForm.vue'), meta: { title: 'Proposer une recette' } },
  { path: '/recette/:id/suggérer', component: () => import('./views/RecipeForm.vue'), meta: { title: 'Proposer un complément' } },
  { path: '/espace/admin-recettes', component: () => import('./views/AdminRecipes.vue'), meta: { title: 'Modération recettes' } },
  { path: '/espace/admin', component: () => import('./views/AdminDashboard.vue'), meta: { title: 'Administration' } },
  { path: '/espace/admin/artisans/:id', component: () => import('./views/AdminArtisanEdit.vue'), meta: { title: 'Modifier un artisan' } },
  { path: '/espace/admin/pois', component: () => import('./views/AdminPois.vue'), meta: { title: 'Gestion des POI' } },
  { path: '/espace/admin/comptes', component: () => import('./views/AdminAccounts.vue'), meta: { title: 'Gestion des comptes' } },
  { path: '/espace/spin-offers', component: () => import('./views/SpinOffers.vue'), meta: { title: 'Mes offres roue' } },
  { path: '/espace/spin-wins',   component: () => import('./views/SpinWins.vue'),   meta: { title: 'Validation des gains' } },
  { path: '/flyers', component: () => import('./views/Flyer.vue'), meta: { title: 'Imprimer les flyers / plaquettes' } },
  { path: '/plaquette', redirect: '/flyers' },
  { path: '/roue', redirect: '/carte' },
  { path: '/profil', component: () => import('./views/UserProfile.vue'), meta: { title: 'Mon profil' } },
  { path: '/reinitialiser', component: () => import('./views/ResetPassword.vue'), meta: { title: 'Réinitialiser le mot de passe' } },
  { path: '/personnage', component: () => import('./views/CharacterEdit.vue'), meta: { title: 'Mon personnage' } },
  { path: '/abonnement/success', component: () => import('./views/SubscriptionSuccess.vue'), meta: { title: 'Abonnement activé' } },
  { path: '/abonnement/cancel', component: () => import('./views/SubscriptionCancel.vue'), meta: { title: 'Paiement annulé' } },
  { path: '/:pathMatch(.*)*', redirect: '/carte' }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to) => (to.hash ? { el: to.hash, behavior: 'smooth' } : { top: 0 })
})

// Connexion automatique par lien magique : consomme ?token= sur n'importe
// quelle page, puis redirige vers la même URL nettoyée.
// (/reinitialiser?token= est exclu : reset mot de passe, géré par ResetPassword.vue)
router.beforeEach(async (to) => {
  const result = await consumeTokenFromQuery(to, authUser)
  if (!result) return true
  const query = { ...to.query }
  delete query.token
  delete query.rememberMe
  if (result.type === 'user') {
    const { showToast } = useGamification()
    showToast(result.success ? 'Connexion réussie !' : (result.error || 'Lien invalide'))
  }
  return { path: to.path, query, hash: to.hash, replace: true }
})

router.afterEach((to) => {
  document.title = (to.meta.title || 'Artisans Locaux') + ' — ' + import.meta.env.VITE_CITY_NAME + ' ' + import.meta.env.VITE_CITY_CP
})

createApp(App).use(router).mount('#app')

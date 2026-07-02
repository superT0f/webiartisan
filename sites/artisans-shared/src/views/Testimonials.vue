<template>
  <main class="testimonials-page">
    <BetaBanner message="Les témoignages sont en version bêta. Connectez-vous gratuitement pour partager le vôtre." />
    <h1>Avis et recommandations locales</h1>

    <div class="filters">
      <ServiceTag
        v-for="cat in catalog"
        :key="cat.key"
        :service-key="cat.key"
        :label="cat.label"
        :icon="cat.icon"
        :is-active="selectedService === cat.key"
        @toggle="toggleService"
      />
    </div>

    <div v-if="error" class="error-banner">{{ error }}</div>

    <div v-if="testimonials.length" class="testimonial-list">
      <TestimonialCard
        v-for="t in testimonials"
        :key="t.id"
        :testimonial="t"
        :catalog-map="catalogMap"
        @helpful="markHelpful"
        @report="openReport"
      />
    </div>
    <p v-else>Aucun témoignage pour cette sélection.</p>
  </main>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  fetchTestimonials,
  fetchServiceCatalog,
  markTestimonialHelpful,
  reportTestimonial,
} from '../api.js'
import TestimonialCard from '../components/TestimonialCard.vue'
import ServiceTag from '../components/ServiceTag.vue'
import BetaBanner from '../components/BetaBanner.vue'

const testimonials = ref([])
const catalog = ref([])
const selectedService = ref(null)
const error = ref('')

const catalogMap = computed(() => {
  const map = {}
  for (const c of catalog.value) {
    map[c.key] = { label: c.label, icon: c.icon }
  }
  return map
})

async function loadCatalog() {
  error.value = ''
  try {
    const res = await fetchServiceCatalog()
    catalog.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement catalogue', e)
    error.value = 'Impossible de charger le catalogue de services.'
    catalog.value = []
  }
}

async function loadTestimonials() {
  error.value = ''
  const filters = { limit: 50 }
  if (selectedService.value) filters.service_type = selectedService.value
  try {
    const res = await fetchTestimonials(filters)
    testimonials.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement témoignages', e)
    error.value = 'Impossible de charger les témoignages.'
    testimonials.value = []
  }
}

async function markHelpful(id) {
  try {
    await markTestimonialHelpful(id)
    await loadTestimonials()
  } catch (e) {
    console.error('Erreur lors du vote utile', e)
    error.value = 'Impossible d\'enregistrer votre vote.'
  }
}

async function openReport(id) {
  const reason = window.prompt('Pourquoi signalez-vous cet avis ?')
  if (!reason) return
  try {
    await reportTestimonial(id, reason)
    await loadTestimonials()
  } catch (e) {
    console.error('Erreur lors du signalement', e)
    error.value = 'Impossible d\'envoyer le signalement.'
  }
}

function toggleService(key) {
  selectedService.value = selectedService.value === key ? null : key
}

watch(selectedService, loadTestimonials)
onMounted(() => { loadCatalog(); loadTestimonials() })
</script>

<style scoped>
.testimonials-page {
  padding: 1rem;
  max-width: 800px;
  margin: 0 auto;
}
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 1rem 0;
}
.testimonial-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.error-banner {
  background: #ffebee;
  color: #b71c1c;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
}
</style>

<script setup>
import { ref, computed, watch } from 'vue'
import { createTestimonial, fetchTestimonialTemplates } from '../api.js'
import BetaBanner from './BetaBanner.vue'

const props = defineProps({
  artisanId: { type: Number, required: true },
  services: { type: Array, default: () => [] },
})
const emit = defineEmits(['posted'])

const form = ref({
  artisan_service_id: null,
  rating: null,
  title: '',
  content: '',
})
const templates = ref([])
const submitting = ref(false)
const error = ref('')

const selectedCatalogKey = computed(() => {
  if (!form.value.artisan_service_id) return null
  const s = props.services.find(s => s.id === form.value.artisan_service_id)
  return s?.catalog_key || null
})

watch(selectedCatalogKey, async (key) => {
  if (!key) {
    templates.value = []
    return
  }
  const requestedKey = key
  try {
    const res = await fetchTestimonialTemplates(key)
    if (requestedKey !== selectedCatalogKey.value) return
    templates.value = res.data?.[0]?.templates || []
  } catch {
    if (requestedKey !== selectedCatalogKey.value) return
    templates.value = []
  }
})

function useTemplate(text) {
  form.value.content = text
}

async function submit() {
  error.value = ''
  submitting.value = true
  try {
    const res = await createTestimonial({
      artisan_id: props.artisanId,
      artisan_service_id: form.value.artisan_service_id,
      rating: form.value.rating,
      title: form.value.title,
      content: form.value.content,
    })
    if (!res.success) {
      error.value = res.error || 'Erreur lors de la publication'
      return
    }
    form.value = { artisan_service_id: null, rating: null, title: '', content: '' }
    emit('posted')
  } catch {
    error.value = 'Problème de connexion. Veuillez réessayer.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="testimonial-composer">
    <BetaBanner message="Les témoignages sont en version beta. Merci pour votre patience." />

    <form @submit.prevent="submit">
      <label>
        Service concerné
        <select v-model="form.artisan_service_id">
          <option :value="null">Général</option>
          <option v-for="s in services" :key="s.id" :value="s.id">
            {{ s.icon || s.catalog_icon }} {{ s.name }}
          </option>
        </select>
      </label>

      <label>
        Note (optionnel)
        <select v-model.number="form.rating">
          <option :value="null">—</option>
          <option v-for="n in 5" :key="n" :value="n">{{ n }} étoile{{ n > 1 ? 's' : '' }}</option>
        </select>
      </label>

      <label>
        Titre (optionnel)
        <input v-model="form.title" type="text" maxlength="150" />
      </label>

      <label>
        Votre témoignage
        <textarea v-model="form.content" rows="4" required maxlength="2000"></textarea>
      </label>

      <div v-if="templates.length" class="testimonial-composer__templates">
        <p>Idées de formulation :</p>
        <button
          v-for="(t, i) in templates"
          :key="i"
          type="button"
          class="template-chip"
          @click="useTemplate(t)"
        >
          {{ t }}
        </button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <button type="submit" :disabled="submitting">
        {{ submitting ? 'Envoi...' : 'Publier mon témoignage' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.testimonial-composer label {
  display: block;
  margin-bottom: 0.75rem;
}
.testimonial-composer input,
.testimonial-composer select,
.testimonial-composer textarea {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 0.5rem;
  margin-top: 0.25rem;
}
.testimonial-composer__templates {
  margin-bottom: 0.75rem;
}
.template-chip {
  display: inline-block;
  margin: 0.25rem 0.25rem 0 0;
  padding: 0.35rem 0.6rem;
  border: 1px solid #2d6a4f;
  background: #fff;
  color: #2d6a4f;
  border-radius: 999px;
  cursor: pointer;
  font-size: 0.8rem;
}
.error {
  color: #c0392b;
}
</style>

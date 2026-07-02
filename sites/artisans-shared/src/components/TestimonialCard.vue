<template>
  <article class="testimonial-card">
    <header class="testimonial-card__header">
      <img
        v-if="testimonial.avatar_url"
        :src="testimonial.avatar_url"
        alt=""
        class="testimonial-card__avatar"
      />
      <div v-else class="testimonial-card__avatar testimonial-card__avatar--placeholder">
        {{ initials }}
      </div>
      <div class="testimonial-card__meta">
        <strong>{{ displayName }}</strong>
        <span v-if="testimonial.service_label" class="testimonial-card__service">
          {{ testimonial.service_icon }} {{ testimonial.service_label }}
        </span>
        <time :datetime="testimonial.created_at">{{ formattedDate }}</time>
      </div>
      <div v-if="testimonial.rating" class="testimonial-card__rating">
        {{ '★'.repeat(testimonial.rating) }}{{ '☆'.repeat(5 - testimonial.rating) }}
      </div>
    </header>

    <h3 v-if="testimonial.title" class="testimonial-card__title">{{ testimonial.title }}</h3>
    <p class="testimonial-card__content">{{ testimonial.content }}</p>

    <div v-if="testimonial.media?.length" class="testimonial-card__media">
      <img
        v-for="m in testimonial.media"
        :key="m.id"
        :src="m.media_url"
        alt=""
        class="testimonial-card__media-item"
      />
    </div>

    <footer class="testimonial-card__actions">
      <button type="button" @click="$emit('helpful', testimonial.id)">
        Utile ({{ testimonial.helpful_count }})
      </button>
      <button type="button" @click="$emit('report', testimonial.id)">
        Signaler
      </button>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  testimonial: { type: Object, required: true },
})
defineEmits(['helpful', 'report'])

const displayName = computed(() => props.testimonial.display_name || 'Utilisateur anonyme')
const initials = computed(() => displayName.value.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase())
const formattedDate = computed(() => {
  if (!props.testimonial.created_at) return ''
  return new Date(props.testimonial.created_at).toLocaleDateString('fr-FR')
})
</script>

<style scoped>
.testimonial-card {
  border: 1px solid #eee;
  border-radius: 0.75rem;
  padding: 1rem;
  background: #fff;
}
.testimonial-card__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.testimonial-card__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  object-fit: cover;
}
.testimonial-card__avatar--placeholder {
  display: grid;
  place-items: center;
  background: #2d6a4f;
  color: #fff;
  font-size: 0.75rem;
  font-weight: bold;
}
.testimonial-card__meta {
  display: flex;
  flex-direction: column;
  font-size: 0.85rem;
  flex: 1;
}
.testimonial-card__service {
  color: #666;
}
.testimonial-card__rating {
  color: #f5a623;
}
.testimonial-card__title {
  font-size: 1rem;
  margin: 0 0 0.5rem;
}
.testimonial-card__content {
  margin: 0 0 0.75rem;
  line-height: 1.5;
}
.testimonial-card__media {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
.testimonial-card__media-item {
  width: 5rem;
  height: 5rem;
  object-fit: cover;
  border-radius: 0.5rem;
}
.testimonial-card__actions {
  display: flex;
  gap: 1rem;
}
.testimonial-card__actions button {
  background: none;
  border: none;
  color: #2d6a4f;
  cursor: pointer;
  font-size: 0.85rem;
}
</style>

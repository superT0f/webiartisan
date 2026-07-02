<template>
  <div class="games-config">
    <h2>Mes mini-jeux</h2>
    <FreemiumLimitBanner
      v-if="activeCount >= 2"
      message="Limite de 2 jeux actifs atteinte en version gratuite."
    />

    <form @submit.prevent="createGame">
      <label>
        Type de jeu
        <select v-model="newGame.game_type_key" required>
          <option v-for="t in freeTypes" :key="t.key" :value="t.key">{{ t.label_fr }}</option>
        </select>
      </label>
      <label>
        Titre
        <input v-model="newGame.title" type="text" required />
      </label>
      <label>
        Description
        <textarea v-model="newGame.description" rows="2"></textarea>
      </label>

      <div v-if="newGame.game_type_key === 'coupon'">
        <label>Texte de révélation <input v-model="newGame.config.reveal_text" type="text" /></label>
      </div>
      <div v-if="newGame.game_type_key === 'poll' || newGame.game_type_key === 'vote'">
        <label>Question <input v-model="newGame.config.question" type="text" /></label>
        <label>Options (séparées par virgule) <input v-model="optionsInput" type="text" /></label>
      </div>

      <button type="submit" :disabled="activeCount >= 2">Créer le jeu</button>
    </form>

    <ul class="game-list">
      <li v-for="g in games" :key="g.id" class="game-item">
        <div>
          <strong>{{ g.title }}</strong>
          <span class="game-type">{{ g.game_type_label }}</span>
        </div>
        <div class="game-actions">
          <button @click="toggleActive(g)">{{ g.is_active ? 'Désactiver' : 'Activer' }}</button>
          <button @click="deleteGame(g.id)">Supprimer</button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchGameTypes, fetchMyGames, createArtisanGame, updateArtisanGame, deleteArtisanGame } from '../../api.js'
import FreemiumLimitBanner from '../../components/FreemiumLimitBanner.vue'

const props = defineProps({ token: { type: String, required: true } })

const types = ref([])
const games = ref([])
const optionsInput = ref('')
const newGame = ref({
  game_type_key: 'coupon',
  title: '',
  description: '',
  config: {},
})

const freeTypes = computed(() => types.value.filter(t => !t.is_premium))
const activeCount = computed(() => games.value.filter(g => g.is_active).length)

async function load() {
  const [tRes, gRes] = await Promise.all([fetchGameTypes(), fetchMyGames(props.token)])
  types.value = tRes.data || []
  games.value = gRes.data || []
}

async function createGame() {
  const config = { ...newGame.value.config }
  if (newGame.game_type_key === 'poll' || newGame.game_type_key === 'vote') {
    config.options = optionsInput.value.split(',').map(s => s.trim()).filter(Boolean)
  }
  await createArtisanGame(props.token, {
    game_type_key: newGame.value.game_type_key,
    title: newGame.value.title,
    description: newGame.value.description,
    config,
  })
  newGame.value = { game_type_key: 'coupon', title: '', description: '', config: {} }
  optionsInput.value = ''
  await load()
}

async function toggleActive(g) {
  await updateArtisanGame(props.token, g.id, { is_active: !g.is_active })
  await load()
}

async function deleteGame(id) {
  if (!confirm('Supprimer ce jeu ?')) return
  await deleteArtisanGame(props.token, id)
  await load()
}

onMounted(load)
</script>

<style scoped>
.games-config form label {
  display: block;
  margin-bottom: 0.5rem;
}
.games-config input,
.games-config select,
.games-config textarea {
  width: 100%;
  padding: 0.4rem;
  margin-top: 0.2rem;
}
.game-list {
  list-style: none;
  padding: 0;
  margin-top: 1rem;
}
.game-item {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  border: 1px solid #eee;
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
}
.game-type {
  display: block;
  font-size: 0.8rem;
  color: #666;
}
.game-actions {
  display: flex;
  gap: 0.5rem;
}
</style>

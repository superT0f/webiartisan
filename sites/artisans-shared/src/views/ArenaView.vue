<template>
  <div class="arena">
    <div ref="sceneEl" class="arena__scene"></div>

    <button type="button" class="arena__close" @click="leave">✕</button>

    <div class="arena__hud">
      <HpBar :hp="state.player_hp" label="Toi" />
      <HpBar :hp="state.boss_hp" label="Big Brother" />
    </div>

    <div class="arena__panel card">
      <template v-if="state.status === 'ongoing'">
        <template v-if="!round">
          <h2>Choisis ton arme !</h2>
          <p v-if="!fightId" class="arena__loading">Connexion au combat…</p>
          <p v-else-if="errorMessage" class="arena__error">{{ errorMessage }}</p>
          <div class="arena__games">
            <button type="button" class="btn btn-primary" :disabled="busy || !fightId" @click="startRound('quiz')">📚 Quiz savoir-faire</button>
            <button type="button" class="btn btn-primary" :disabled="busy || !fightId" @click="startRound('mate')">♟️ Mat en 1 coup</button>
            <button type="button" class="btn btn-primary" :disabled="busy || !fightId" @click="startRound('cards')">🃏 Duel de cartes</button>
          </div>
        </template>

        <QuizGame v-else-if="round.game === 'quiz'" :content="round.content" :reveal="reveal?.answer_index ?? null" @answer="answer" />
        <MateGame v-else-if="round.game === 'mate'" :content="round.content" @answer="answer" />
        <CardsGame v-else-if="round.game === 'cards'" :content="round.content" :reveal="reveal?.boss_card ?? null" @answer="answer" />
      </template>

      <div v-else-if="state.status === 'won'" class="arena__end arena__end--won">
        <span class="arena__end-emoji">🏆</span>
        <h2>Big Brother terrassé !</h2>
        <p>+150 XP — la ville respire (3 déchets nettoyés) 🌿</p>
        <button type="button" class="btn btn-primary" @click="leave">Retour à la carte</button>
      </div>

      <div v-else class="arena__end arena__end--lost">
        <span class="arena__end-emoji">💸</span>
        <h2>Le Big Brother t'a écrasé…</h2>
        <p>+5 XP de consolation. Recharge ton énergie et reviens !</p>
        <button type="button" class="btn btn-primary" @click="leave">Retour à la carte</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  startBossFight, getBossFight, startBossRound, answerBossRound,
  getUserToken, haptic,
} from '../api.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { playSound } from '../utils/sounds.js'
import { createArenaScene } from '../components/arena/arenaScene.js'
import HpBar from '../components/arena/HpBar.vue'
import QuizGame from '../components/arena/QuizGame.vue'
import MateGame from '../components/arena/MateGame.vue'
import CardsGame from '../components/arena/CardsGame.vue'

const route = useRoute()
const router = useRouter()
const sceneEl = ref(null)

const bossId = Number(route.query.boss || 0)
const fightId = ref(Number(route.query.fight || 0))
const state = reactive({ boss_hp: 3, player_hp: 3, rounds_won: 0, rounds_lost: 0, status: 'ongoing' })
const round = ref(null)
const reveal = ref(null)
const busy = ref(false)
const errorMessage = ref('')
let scene = null

const { position, start: startGeolocation } = useGeolocation()

function applyState(data) {
  state.boss_hp = data.boss_hp
  state.player_hp = data.player_hp
  state.rounds_won = data.rounds_won ?? state.rounds_won
  state.rounds_lost = data.rounds_lost ?? state.rounds_lost
  state.status = data.status
}

async function startRound(game) {
  if (!fightId.value) return // combat pas encore initialisé (fix GPS en cours)
  busy.value = true
  errorMessage.value = ''
  reveal.value = null
  const res = await startBossRound(fightId.value, game)
  busy.value = false
  if (res.success) {
    round.value = { game: res.data.game, content: res.data.content }
    applyState(res.data)
  } else if (res.status === 410) {
    leave()
  } else {
    // Erreur transitoire : rester dans l'arène avec un message
    errorMessage.value = 'Manche indisponible — réessaie dans un instant.'
  }
}

async function answer(payload) {
  if (busy.value) return
  busy.value = true
  const res = await answerBossRound(fightId.value, payload)
  busy.value = false
  if (!res.success) {
    round.value = null
    return
  }
  reveal.value = res.data.reveal || {}
  applyState(res.data)
  if (res.data.round_won) {
    playSound('xp-boost')
    haptic('medium')
    scene?.hitBoss()
  } else {
    haptic('light')
    scene?.hitPlayer()
  }

  if (res.data.status === 'won') {
    playSound('success')
    haptic('heavy')
    scene?.celebrate()
    round.value = null
  } else if (res.data.status === 'lost') {
    round.value = null
  } else {
    // Manche terminée : retour au choix d'arme après un court délai
    setTimeout(() => { round.value = null; reveal.value = null }, 1400)
  }
}

function leave() {
  router.push('/carte')
}

onMounted(async () => {
  if (!getUserToken()) {
    router.push('/carte')
    return
  }
  scene = await createArenaScene(sceneEl.value)
  startGeolocation()

  if (fightId.value) {
    const res = await getBossFight(fightId.value)
    if (res.success) {
      applyState(res.data)
      return
    }
  }
  if (!bossId) return

  // Position passée par la carte dans l'URL : le combat démarre immédiatement,
  // sans attendre un nouveau fix GPS (le bridge peut être long).
  const queryLat = Number(route.query.lat)
  const queryLng = Number(route.query.lng)
  if (queryLat && queryLng) {
    await engageFight(bossId, queryLat, queryLng)
    return
  }

  // Sinon : attendre un fix GPS valide avant d'engager (422 distance sinon)
  let fightRequested = false
  const stopWatch = watch(position, async (pos) => {
    if (!pos || fightRequested) return
    fightRequested = true
    stopWatch()
    await engageFight(bossId, pos.latitude, pos.longitude)
  }, { immediate: true })
})

async function engageFight(id, lat, lng) {
  const res = await startBossFight(id, lat, lng)
  if (res.success) {
    fightId.value = res.data.fight_id
    applyState(res.data)
  } else if (res.status === 409 && res.data?.fight_id) {
    fightId.value = res.data.fight_id
    const st = await getBossFight(fightId.value)
    if (st.success) applyState(st.data)
  } else if (res.status === 410) {
    leave()
  } else {
    errorMessage.value = res.error === 'distance'
      ? `Trop loin du Big Brother (${res.data?.distance_m ?? '?'} m, 500 m max)`
      : 'Impossible d\'engager le combat — réessaie.'
  }
}

onUnmounted(() => {
  scene?.destroy()
})
</script>

<style scoped>
.arena { position: fixed; inset: 0; z-index: 120; background: #1a1330; display: flex; flex-direction: column; }
.arena__scene { position: absolute; inset: 0; }
.arena__close {
  position: absolute; top: 12px; right: 12px; z-index: 3;
  background: rgba(255,255,255,0.15); color: #fff; border: none;
  width: 40px; height: 40px; border-radius: 50%; font-size: 1.1rem; cursor: pointer;
}
.arena__hud {
  position: relative; z-index: 2;
  display: flex; justify-content: space-between; padding: 14px 18px;
}
.arena__panel {
  position: relative; z-index: 2;
  margin: auto 14px 20px;
  background: #fff; border-radius: 18px; padding: 20px;
  max-height: 62vh; overflow-y: auto;
}
.arena__panel h2 { margin: 0 0 12px; font-size: 1.1rem; text-align: center; }
.arena__loading { text-align: center; color: #7a6f63; font-size: 0.85rem; margin: 0 0 10px; }
.arena__error { text-align: center; color: #dc2626; font-size: 0.85rem; font-weight: 600; margin: 0 0 10px; }
.arena__games { display: flex; flex-direction: column; gap: 10px; }
.arena__end { text-align: center; display: flex; flex-direction: column; gap: 10px; align-items: center; }
.arena__end-emoji { font-size: 3rem; }
</style>

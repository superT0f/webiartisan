// Scène Phaser de l'arène Affamer de Gaffe : rue des artisans au crépuscule
// vs son usine GAFAM. Dégradé gracieux : si Phaser échoue, l'UI DOM reste jouable.
export async function createArenaScene(container, callbacks = {}) {
  try {
    const Phaser = await import('phaser')
    const state = { boss: null, sign: null, smoke: [] }

    const SKY_TOP = 0x1a1330
    const SKY_HORIZON = 0xb5541e
    const WALL = 0x2B2118
    const WALL_2 = 0x3a2f24
    const WINDOW_WARM = 0xE8A33D
    const FACTORY = 0x14101d

    class ArenaScene extends Phaser.Scene {
      preload() {
        this.load.svg('gafam', '/boss/gafam.svg', { width: 260, height: 312 })
        this.load.image('gafam-sign', '/boss/gafam-sign.jpg')
      }

      create() {
        const { width: w, height: h } = this.scale

        // --- Ciel crépuscule (dégradé violet → orange) ---
        const sky = this.add.graphics()
        sky.fillGradientStyle(SKY_TOP, SKY_TOP, SKY_HORIZON, SKY_HORIZON, 1)
        sky.fillRect(0, 0, w, h)

        // Quelques étoiles
        const stars = this.add.graphics()
        stars.fillStyle(0xffffff, 0.8)
        for (let i = 0; i < 24; i++) {
          stars.fillCircle(Math.random() * w, Math.random() * h * 0.35, Math.random() * 1.4 + 0.4)
        }

        // --- Usine d'Affamer (fond droit) ---
        const fx = w * 0.66
        const fy = h * 0.52
        const factory = this.add.graphics()
        factory.fillStyle(FACTORY, 1)
        factory.fillRect(fx, fy, w * 0.34, h * 0.28)
        // Cheminées
        factory.fillRect(fx + w * 0.05, fy - h * 0.14, w * 0.045, h * 0.14)
        factory.fillRect(fx + w * 0.16, fy - h * 0.10, w * 0.045, h * 0.10)
        // Fenêtres froides de l'usine
        factory.fillStyle(0x6b5ce7, 0.5)
        for (let r = 0; r < 3; r++) {
          for (let c = 0; c < 5; c++) {
            factory.fillRect(fx + 12 + c * (w * 0.34 - 24) / 5, fy + 14 + r * 26, (w * 0.34 - 24) / 5 - 8, 12)
          }
        }

        // Fumée animée
        for (let i = 0; i < 5; i++) {
          const puff = this.add.circle(fx + w * 0.072, fy - h * 0.14, 8 + i * 2, 0x9a94a8, 0.35)
          state.smoke.push(puff)
          this.tweens.add({
            targets: puff,
            y: puff.y - 70 - i * 12,
            x: puff.x + 20 + i * 6,
            alpha: 0,
            scale: 1.8,
            duration: 2600 + i * 500,
            repeat: -1,
            delay: i * 600,
          })
        }

        // Enseigne GAFAM sur le toit de l'usine
        state.sign = this.add.image(fx + w * 0.17, fy - 24, 'gafam-sign')
        const signTargetW = Math.min(w * 0.30, 220)
        state.sign.setScale(signTargetW / state.sign.width)

        // --- Rue des artisans (premier plan, chaleureuse) ---
        const groundY = h * 0.78
        const street = this.add.graphics()
        // Sol
        street.fillStyle(0x241c14, 1)
        street.fillRect(0, groundY, w, h - groundY)

        // Devantures d'artisans (bois chaud, fenêtres ambrées)
        const shops = [
          { x: 0.02, w: 0.22, h: 0.20, awning: 0x2D6A4F },
          { x: 0.27, w: 0.20, h: 0.17, awning: 0xC07A2E },
          { x: 0.50, w: 0.19, h: 0.22, awning: 0x8c2f39 },
        ]
        for (const s of shops) {
          const sx = s.x * w
          const sw = s.w * w
          const sh = s.h * h
          street.fillStyle(WALL, 1)
          street.fillRect(sx, groundY - sh, sw, sh)
          // Toit
          street.fillStyle(WALL_2, 1)
          street.fillTriangle(sx - 6, groundY - sh, sx + sw + 6, groundY - sh, sx + sw / 2, groundY - sh - 18)
          // Auvent
          street.fillStyle(s.awning, 1)
          street.fillRect(sx - 3, groundY - sh * 0.55, sw + 6, 8)
          // Fenêtres chaudes
          street.fillStyle(WINDOW_WARM, 0.95)
          street.fillRect(sx + sw * 0.16, groundY - sh * 0.42, sw * 0.22, sh * 0.28)
          street.fillRect(sx + sw * 0.62, groundY - sh * 0.42, sw * 0.22, sh * 0.28)
          // Porte
          street.fillStyle(0x17110b, 1)
          street.fillRect(sx + sw * 0.42, groundY - sh * 0.28, sw * 0.16, sh * 0.28)
        }

        // Guirlandes lumineuses entre les devantures
        const garland = this.add.graphics()
        for (let i = 0; i <= 16; i++) {
          const t = i / 16
          const gx = t * w * 0.75
          const gy = groundY - h * 0.24 + Math.sin(t * Math.PI) * 26
          const colors = [0xE8A33D, 0x2D6A4F, 0xC07A2E, 0x8c2f39]
          garland.fillStyle(colors[i % colors.length], 0.95)
          garland.fillCircle(gx, gy, 3)
        }

        // --- Affamer de Gaffe, en personne ---
        state.boss = this.add.image(w * 0.5, groundY - 118, 'gafam')
        const bossTargetH = Math.min(h * 0.34, 300)
        state.boss.setScale(bossTargetH / state.boss.height)
        state.boss.setOrigin(0.5, 0.5)
        this.tweens.add({
          targets: state.boss,
          y: state.boss.y - 10,
          duration: 950,
          yoyo: true,
          repeat: -1,
          ease: 'Sine.easeInOut',
        })

        // Ombre au sol
        const shadow = this.add.ellipse(w * 0.5, groundY - 8, 130, 18, 0x000000, 0.35)
        this.tweens.add({
          targets: shadow,
          scaleX: 0.85,
          duration: 950,
          yoyo: true,
          repeat: -1,
          ease: 'Sine.easeInOut',
        })

        // Petit point pour les particules
        const dot = this.add.graphics()
        dot.fillStyle(0xffffff, 1)
        dot.fillCircle(4, 4, 4)
        dot.generateTexture('dot', 8, 8)
        dot.destroy()
      }

      update() {
        // les guirlandes scintillent doucement
      }
    }

    const game = new Phaser.Game({
      type: Phaser.AUTO,
      parent: container,
      width: container.clientWidth,
      height: container.clientHeight,
      transparent: false,
      backgroundColor: '#1a1330',
      scene: ArenaScene,
    })

    return {
      /** Manche gagnée : le boss prend un coup (recul + flash rouge + saut). */
      hitBoss() {
        const s = game.scene.scenes[0]
        if (!s || !state.boss) return
        state.boss.setTintFill(0xff5a5a)
        s.tweens.add({
          targets: state.boss,
          x: state.boss.x + 26,
          duration: 70,
          yoyo: true,
          repeat: 2,
          onComplete: () => state.boss.clearTint(),
        })
      },

      /** Manche perdue : secousse de caméra. */
      hitPlayer() {
        const s = game.scene.scenes[0]
        if (s) s.cameras.main.shake(200, 0.009)
      },

      /** Victoire : le boss tombe, confettis, l'enseigne vacille. */
      celebrate() {
        const s = game.scene.scenes[0]
        if (!s || !state.boss) return
        // Chute du boss
        s.tweens.add({
          targets: state.boss,
          angle: 92,
          y: state.boss.y + 70,
          alpha: 0.25,
          duration: 900,
          ease: 'Bounce.easeOut',
        })
        // Confettis
        if (s.particles) {
          const emitter = s.add.particles(0, 0, 'dot', {
            x: { min: 0, max: s.scale.width },
            y: -10,
            lifespan: 2600,
            speedY: { min: 120, max: 260 },
            speedX: { min: -60, max: 60 },
            gravityY: 140,
            scale: { min: 0.4, max: 1.1 },
            rotate: { min: 0, max: 360 },
            quantity: 3,
            frequency: 60,
            tint: [0xE8A33D, 0x2D6A4F, 0xC07A2E, 0x8c2f39, 0xffffff],
          })
          s.time.delayedCall(3200, () => emitter.stop())
        }
        // L'enseigne clignote
        if (state.sign) {
          s.tweens.add({ targets: state.sign, alpha: 0.3, duration: 160, yoyo: true, repeat: 5 })
        }
      },

      /** Défaite : le boss ricane (rebond moqueur). */
      mock() {
        const s = game.scene.scenes[0]
        if (!s || !state.boss) return
        s.tweens.add({
          targets: state.boss,
          scaleX: state.boss.scaleX * 1.12,
          scaleY: state.boss.scaleY * 0.92,
          duration: 220,
          yoyo: true,
          repeat: 3,
        })
      },

      destroy() { game.destroy(true) },
    }
  } catch (e) {
    console.warn('Phaser indisponible, arène en mode dégradé', e)
    return null
  }
}

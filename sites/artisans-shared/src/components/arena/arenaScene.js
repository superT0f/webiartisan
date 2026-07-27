// Scène Phaser de l'arène Affamer de Gaffe : backdrop illustré (Leonardo.ai,
// crops ImageMagick) + animations par-dessus. Dégradé gracieux : si Phaser
// échoue, l'UI DOM reste jouable.
export async function createArenaScene(container, callbacks = {}) {
  try {
    const Phaser = await import('phaser')
    const state = { bg: null }

    class ArenaScene extends Phaser.Scene {
      preload() {
        this.load.image('arena-bg', '/boss/arena-backdrop.jpg')
      }

      create() {
        const { width: w, height: h } = this.scale
        state.bg = this.add.image(0, 0, 'arena-bg').setOrigin(0, 0)

        // Cover-fit centré sur le visage du boss (688, 345 dans l'image 1376×768)
        const s = Math.max(w / 1376, h / 768) * 1.12
        state.bg.setScale(s)
        state.bg.setPosition(w / 2 - 688 * s, h * 0.34 - 345 * s)
        state.bg.baseX = state.bg.x
        state.bg.baseY = state.bg.y

        // Flottement doux (respiration de la scène)
        this.tweens.add({
          targets: state.bg,
          y: state.bg.baseY - 6,
          duration: 1600,
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
      /** Manche gagnée : flash rouge + recul de la scène. */
      hitBoss() {
        const s = game.scene.scenes[0]
        if (!s || !state.bg) return
        state.bg.setTintFill(0xff6b6b)
        s.tweens.add({
          targets: state.bg,
          x: state.bg.baseX + 18,
          duration: 70,
          yoyo: true,
          repeat: 2,
          onComplete: () => {
            state.bg.clearTint()
            state.bg.x = state.bg.baseX
          },
        })
      },

      /** Manche perdue : secousse de caméra. */
      hitPlayer() {
        const s = game.scene.scenes[0]
        if (s) s.cameras.main.shake(200, 0.009)
      },

      /** Victoire : zoom sur le boss, confettis multicolores. */
      celebrate() {
        const s = game.scene.scenes[0]
        if (!s) return
        if (state.bg) {
          s.tweens.add({
            targets: state.bg,
            scale: state.bg.scale * 1.06,
            duration: 1200,
            ease: 'Sine.easeInOut',
          })
        }
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
      },

      /** Défaite : pulsation moqueuse (zoom rapide + teinte dorée). */
      mock() {
        const s = game.scene.scenes[0]
        if (!s || !state.bg) return
        state.bg.setTint(0xE8A33D)
        s.tweens.add({
          targets: state.bg,
          scale: state.bg.scale * 1.04,
          duration: 220,
          yoyo: true,
          repeat: 3,
          onComplete: () => state.bg.clearTint(),
        })
      },

      destroy() { game.destroy(true) },
    }
  } catch (e) {
    console.warn('Phaser indisponible, arène en mode dégradé', e)
    return null
  }
}

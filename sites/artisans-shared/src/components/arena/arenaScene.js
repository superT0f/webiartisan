// Scène Phaser de l'arène (fond + Big Brother animé). Dégradé gracieux :
// si Phaser échoue à init, l'UI DOM reste jouable (scene null).
export async function createArenaScene(container, callbacks = {}) {
  try {
    const Phaser = await import('phaser')
    const sceneState = { bossText: null }

    class ArenaScene extends Phaser.Scene {
      create() {
        const { width, height } = this.scale
        const g = this.add.graphics()
        g.fillGradientStyle(0x1a1330, 0x1a1330, 0x3b1d4f, 0x3b1d4f, 1)
        g.fillRect(0, 0, width, height)
        sceneState.bossText = this.add.text(width / 2, height * 0.22, '🎩🏭', { fontSize: '72px' })
          .setOrigin(0.5)
        this.tweens.add({
          targets: sceneState.bossText,
          y: sceneState.bossText.y - 12,
          duration: 900,
          yoyo: true,
          repeat: -1,
          ease: 'Sine.easeInOut',
        })
      }
    }

    const game = new Phaser.Game({
      type: Phaser.AUTO,
      parent: container,
      width: container.clientWidth,
      height: container.clientHeight,
      transparent: true,
      scene: ArenaScene,
    })

    return {
      hitBoss() {
        const s = game.scene.scenes[0]
        if (s && sceneState.bossText) {
          s.tweens.add({ targets: sceneState.bossText, alpha: 0.2, duration: 90, yoyo: true, repeat: 3 })
        }
      },
      hitPlayer() {
        const s = game.scene.scenes[0]
        if (s) s.cameras.main.shake(180, 0.008)
      },
      celebrate() {
        const s = game.scene.scenes[0]
        if (s && sceneState.bossText) {
          s.tweens.add({ targets: sceneState.bossText, angle: 25, scale: 1.3, duration: 600, ease: 'Back.easeOut' })
        }
      },
      destroy() { game.destroy(true) },
    }
  } catch (e) {
    console.warn('Phaser indisponible, arène en mode dégradé', e)
    return null
  }
}

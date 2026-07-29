// Splash de démarrage WebiArtisan : l'avatar (gauche) affronte Affamer de
// Gaffe (droite) — pluie de baguettes L→R vs pluie de billets R→L.
// La scène appelle onDone() à la fin (~6 s) ou au premier tap.
import { CITY_NAME } from '../api.js'

export async function createSplashScene(container, { onDone } = {}) {
  const Phaser = await import('phaser')
  let done = false
  const finish = (scene) => {
    if (done) return
    done = true
    try { scene?.sound?.stopAll() } catch (e) { /* noop */ }
    onDone?.()
  }

  class SplashScene extends Phaser.Scene {
    preload() {
      this.load.image('splash-bg', '/boss/arena-backdrop.jpg')
      this.load.image('avatar', '/avatar/player-512.png')
      this.load.image('boss', '/boss/affamer-512.png')
      this.load.svg('baguette-logo', '/logo-baguette.svg', { width: 96, height: 96 })
      this.load.audio('splash-music', '/sounds/splash.mp3')
    }

    create() {
      const { width: w, height: h } = this.scale

      // Backdrop (rue des artisans) en cover
      const bg = this.add.image(w / 2, h / 2, 'splash-bg')
      const s = Math.max(w / 1376, h / 768) * 1.1
      bg.setScale(s).setAlpha(0.9)
      this.add.rectangle(0, 0, w, h, 0x1a1330, 0.45).setOrigin(0, 0)

      // Titre
      const title = this.add.text(w / 2, h * 0.1, 'WebiArtisan', {
        fontFamily: 'Outfit, sans-serif', fontSize: `${Math.round(h * 0.055)}px`,
        fontStyle: 'bold', color: '#C07A2E',
      }).setOrigin(0.5).setAlpha(0)
      this.tweens.add({ targets: title, alpha: 1, duration: 800 })

      const groundY = h * 0.68
      const scaleH = h * 0.34

      // Petit point lumineux pour les feux d'artifice
      const spark = this.add.graphics()
      spark.fillStyle(0xffffff, 1)
      spark.fillCircle(3, 3, 3)
      spark.generateTexture('spark', 6, 6)
      spark.destroy()

      // Avatar (gauche) et boss (droite, plus petit) — à distance respectable
      const avatar = this.add.image(-80, groundY, 'avatar')
      avatar.setScale(scaleH / avatar.height)
      const boss = this.add.image(w + 80, groundY, 'boss').setFlipX(true)
      boss.setScale((scaleH * 0.62) / boss.height)
      this.tweens.add({ targets: avatar, x: w * 0.22, duration: 1400, ease: 'Sine.easeOut' })
      this.tweens.add({ targets: boss, x: w * 0.78, duration: 1400, ease: 'Sine.easeOut' })
      this.tweens.add({ targets: avatar, y: groundY - 8, duration: 900, yoyo: true, repeat: -1, ease: 'Sine.easeInOut' })

      // Logo baguette BBR au centre, les sépare (rotation « horloge » à la fin)
      const baguette = this.add.image(w / 2, groundY - scaleH * 0.4, 'baguette-logo')
        .setScale((scaleH * 0.32) / 96)
        .setAlpha(0)
      this.tweens.add({ targets: baguette, alpha: 1, duration: 700, delay: 1500 })

      // Pluie de baguettes : avatar → boss (toutes les 420 ms)
      this.time.addEvent({
        delay: 420,
        loop: true,
        callback: () => {
          const b = this.add.text(avatar.x + 40, avatar.y - 30, '🥖', { fontSize: '34px' })
            .setRotation(-0.6)
          this.tweens.add({
            targets: b,
            x: boss.x - 30,
            y: boss.y - 40,
            rotation: 2.2,
            duration: 520,
            onComplete: () => {
              b.destroy()
              // Feu d'artifice à l'impact (or + ambre, additif)
              const fw = this.add.particles(boss.x - 20, boss.y - 50, 'spark', {
                speed: { min: 90, max: 260 },
                angle: { min: 0, max: 360 },
                lifespan: 480,
                scale: { start: 1.6, end: 0 },
                quantity: 14,
                emitting: false,
                tint: [0xffd166, 0xf4a261, 0xfff3d6],
                blendMode: 'ADD',
              })
              fw.explode(14)
              this.time.delayedCall(600, () => fw.destroy())
            },
          })
        },
      })

      // Riposte : pluie de billets boss → avatar (toutes les 640 ms)
      this.time.addEvent({
        delay: 640,
        loop: true,
        callback: () => {
          const m = this.add.text(boss.x - 40, boss.y - 60, '💶', { fontSize: '32px' })
            .setRotation(0.5)
          this.tweens.add({
            targets: m,
            x: avatar.x + 30,
            y: avatar.y - 50,
            rotation: -2.2,
            duration: 580,
            onComplete: () => {
              m.destroy()
              // L'avatar esquive (petit saut)
              this.tweens.add({
                targets: avatar, y: avatar.y - 22, duration: 110, yoyo: true, repeat: 1,
              })
            },
          })
        },
      })

      // Musique (fondu sortant à la fin) — tolère le blocage autoplay
      try {
        const music = this.sound.add('splash-music', { volume: 0.65 })
        music.play()
        this.tweens.add({ targets: music, volume: 0, duration: 900, delay: 4800 })
      } catch (e) { /* pas de son tant pis */ }

      // Finale : les deux camps s'affichent face à face + baguette « horloge »
      // Taille pilotée par la largeur (mobile) + 2 lignes pour ne pas déborder
      const fs = Math.max(18, Math.min(Math.round(h * 0.03), Math.round(w * 0.055)))
      const labelStyle = (color) => ({
        fontFamily: 'Outfit, sans-serif', fontSize: `${fs}px`,
        fontStyle: 'bold', color, stroke: '#1a1330', strokeThickness: 4,
        align: 'center',
      })
      const leftLabel = this.add.text(w * 0.25, -60, `Artisans de\n${CITY_NAME}`, labelStyle('#e8b04b'))
        .setOrigin(0.5).setAlpha(0)
      const rightLabel = this.add.text(w * 0.75, -60, 'Affamer\nde Gaffe', labelStyle('#ff8080'))
        .setOrigin(0.5).setAlpha(0)
      this.tweens.add({ targets: leftLabel, y: h * 0.22, alpha: 1, duration: 900, delay: 3800, ease: 'Back.easeOut' })
      this.tweens.add({ targets: rightLabel, y: h * 0.22, alpha: 1, duration: 900, delay: 3800, ease: 'Back.easeOut' })

      // La baguette centrale tourne comme une horloge
      this.time.delayedCall(3900, () => {
        this.tweens.add({ targets: baguette, rotation: Math.PI * 2, duration: 6000, repeat: -1, ease: 'Linear' })
      })

      this.time.delayedCall(6000, () => finish(this))
      this.input.once('pointerdown', () => finish(this))
    }
  }

  const game = new Phaser.Game({
    type: Phaser.AUTO,
    parent: container,
    width: container.clientWidth,
    height: container.clientHeight,
    transparent: false,
    backgroundColor: '#1a1330',
    scene: SplashScene,
  })

  return {
    destroy() {
      try { game.destroy(true) } catch (e) { /* noop */ }
    },
  }
}

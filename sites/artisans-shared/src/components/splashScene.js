// Splash de démarrage WebiArtisan : l'avatar (gauche) affronte Affamer de
// Gaffe (droite) — pluie de baguettes L→R vs pluie de billets R→L.
// La scène appelle onDone() à la fin (~6 s) ou au premier tap.
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
      this.load.image('boss', '/boss/boss-crop.jpg')
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

      // Avatar (gauche) et boss (droite) qui se rapprochent
      const avatar = this.add.image(-80, groundY, 'avatar')
      avatar.setScale(scaleH / avatar.height)
      const boss = this.add.image(w + 80, groundY, 'boss').setFlipX(true)
      boss.setScale((scaleH * 1.05) / boss.height)
      this.tweens.add({ targets: avatar, x: w * 0.3, duration: 1400, ease: 'Sine.easeOut' })
      this.tweens.add({ targets: boss, x: w * 0.72, duration: 1400, ease: 'Sine.easeOut' })
      this.tweens.add({ targets: avatar, y: groundY - 8, duration: 900, yoyo: true, repeat: -1, ease: 'Sine.easeInOut' })

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
              boss.setTintFill(0xff6b6b)
              this.tweens.add({
                targets: boss, x: boss.x + 12, duration: 60, yoyo: true, repeat: 1,
                onComplete: () => boss.clearTint(),
              })
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

      // Punchline + fin
      const punch = this.add.text(w / 2, h * 0.86, 'Les artisans contre-attaquent !', {
        fontFamily: 'Outfit, sans-serif', fontSize: `${Math.round(h * 0.03)}px`,
        color: '#fff',
      }).setOrigin(0.5).setAlpha(0)
      this.tweens.add({ targets: punch, alpha: 1, duration: 700, delay: 3600 })

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

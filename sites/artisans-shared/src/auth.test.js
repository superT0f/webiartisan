// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest'
import {
  getUserToken, setUserToken, removeUserToken,
  getArtisanToken, setArtisanToken, removeArtisanToken,
  extractLinkToken, consumeTokenFromQuery,
} from './auth.js'

function clearCookies() {
  for (const name of ['user_token', 'artisan_token']) {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
  }
}

beforeEach(() => {
  localStorage.clear()
  clearCookies()
})

describe('stockage token user', () => {
  it('stocke en localStorage quand remember=false', () => {
    setUserToken('tok-session', false)
    expect(localStorage.getItem('spin_user_token')).toBe('tok-session')
    expect(getUserToken()).toBe('tok-session')
  })

  it('stocke en cookie quand remember=true', () => {
    setUserToken('tok-persist', true)
    expect(localStorage.getItem('spin_user_token')).toBeNull()
    expect(document.cookie).toContain('user_token=tok-persist')
    expect(getUserToken()).toBe('tok-persist')
  })

  it('removeUserToken nettoie les deux stockages', () => {
    setUserToken('a', false)
    setUserToken('b', true)
    removeUserToken()
    expect(getUserToken()).toBe('')
    expect(localStorage.getItem('spin_user_token')).toBeNull()
  })
})

describe('stockage token artisan', () => {
  it('lit un token écrit directement en localStorage (contrainte admin-login.html)', () => {
    localStorage.setItem('artisan_token', 'TOKENADMIN')
    expect(getArtisanToken()).toBe('TOKENADMIN')
  })

  it('stocke en cookie quand remember=true', () => {
    setArtisanToken('tok-art', true)
    expect(localStorage.getItem('artisan_token')).toBeNull()
    expect(document.cookie).toContain('artisan_token=tok-art')
    expect(getArtisanToken()).toBe('tok-art')
  })

  it('removeArtisanToken nettoie les deux stockages', () => {
    setArtisanToken('a', false)
    removeArtisanToken()
    expect(getArtisanToken()).toBe('')
  })
})

describe('extractLinkToken', () => {
  it('retourne null sans token dans la query', () => {
    expect(extractLinkToken({ path: '/carte', query: {} })).toBeNull()
  })

  it('route /espace* → artisan, rememberMe faux par défaut', () => {
    expect(extractLinkToken({ path: '/espace', query: { token: 'abc' } }))
      .toEqual({ token: 'abc', rememberMe: false, type: 'artisan' })
  })

  it('route /espace avec rememberMe=1 → artisan rememberMe vrai', () => {
    expect(extractLinkToken({ path: '/espace/admin', query: { token: 'abc', rememberMe: '1' } }))
      .toEqual({ token: 'abc', rememberMe: true, type: 'artisan' })
  })

  it('route autre → user, rememberMe vrai par défaut', () => {
    expect(extractLinkToken({ path: '/carte', query: { token: 'abc' } }))
      .toEqual({ token: 'abc', rememberMe: true, type: 'user' })
  })

  it('route autre avec rememberMe=0 → user rememberMe faux', () => {
    expect(extractLinkToken({ path: '/carte', query: { token: 'abc', rememberMe: '0' } }))
      .toEqual({ token: 'abc', rememberMe: false, type: 'user' })
  })

  it('accepte un token sous forme de tableau (query dupliquée)', () => {
    expect(extractLinkToken({ path: '/carte', query: { token: ['abc', 'def'] } })?.token).toBe('abc')
  })

  it('ignore /reinitialiser (token de reset mot de passe, pas un lien magique)', () => {
    expect(extractLinkToken({ path: '/reinitialiser', query: { token: 'abc' } })).toBeNull()
  })
})

describe('consumeTokenFromQuery', () => {
  it('retourne null sans token', async () => {
    const exchange = vi.fn()
    expect(await consumeTokenFromQuery({ path: '/carte', query: {} }, exchange)).toBeNull()
    expect(exchange).not.toHaveBeenCalled()
  })

  it('artisan : stocke directement sans appeler l\'échange', async () => {
    const exchange = vi.fn()
    const res = await consumeTokenFromQuery({ path: '/espace', query: { token: 'art-1', rememberMe: '1' } }, exchange)
    expect(res).toEqual({ type: 'artisan', success: true })
    expect(exchange).not.toHaveBeenCalled()
    expect(document.cookie).toContain('artisan_token=art-1')
  })

  it('user : stocke le token échangé en cas de succès', async () => {
    const exchange = vi.fn().mockResolvedValue({ success: true, token: 'final-tok' })
    const res = await consumeTokenFromQuery({ path: '/carte', query: { token: 'tmp' } }, exchange)
    expect(res).toEqual({ type: 'user', success: true })
    expect(exchange).toHaveBeenCalledWith('tmp', true)
    expect(document.cookie).toContain('user_token=final-tok')
  })

  it('user : remonte l\'erreur en cas d\'échec d\'échange', async () => {
    const exchange = vi.fn().mockResolvedValue({ success: false, error: 'Lien invalide' })
    const res = await consumeTokenFromQuery({ path: '/carte', query: { token: 'tmp' } }, exchange)
    expect(res).toEqual({ type: 'user', success: false, error: 'Lien invalide' })
    expect(getUserToken()).toBe('')
  })

  it('user : gère une exception réseau', async () => {
    const exchange = vi.fn().mockRejectedValue(new Error('network'))
    const res = await consumeTokenFromQuery({ path: '/carte', query: { token: 'tmp' } }, exchange)
    expect(res).toEqual({ type: 'user', success: false, error: 'Erreur réseau.' })
  })

  it('ignore /reinitialiser sans appeler l\'échange', async () => {
    const exchange = vi.fn()
    expect(await consumeTokenFromQuery({ path: '/reinitialiser', query: { token: 'abc' } }, exchange)).toBeNull()
    expect(exchange).not.toHaveBeenCalled()
  })
})

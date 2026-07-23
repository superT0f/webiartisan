import { describe, it, expect } from 'vitest'
import { parseFen, PIECE_GLYPHS, squareToCoords, coordsToSquare } from '../chessBoard.js'

describe('parseFen', () => {
  it('position de départ standard', () => {
    const b = parseFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1')
    expect(b).toHaveLength(8)
    expect(b[0]).toEqual(['r','n','b','q','k','b','n','r'])
    expect(b[7]).toEqual(['R','N','B','Q','K','B','N','R'])
    expect(b[3]).toEqual([null,null,null,null,null,null,null,null])
  })

  it('mat du couloir', () => {
    const b = parseFen('6k1/5ppp/8/8/8/8/8/R5K1 w - - 0 1')
    expect(b[0][6]).toBe('k')
    expect(b[7][0]).toBe('R')
    expect(b[7][6]).toBe('K')
    expect(b[1][5]).toBe('p')
  })
})

describe('cases', () => {
  it('squareToCoords', () => {
    expect(squareToCoords('a1')).toEqual({ row: 7, col: 0 })
    expect(squareToCoords('e4')).toEqual({ row: 4, col: 4 })
    expect(squareToCoords('h8')).toEqual({ row: 0, col: 7 })
  })

  it('coordsToSquare', () => {
    expect(coordsToSquare(7, 0)).toBe('a1')
    expect(coordsToSquare(4, 4)).toBe('e4')
    expect(coordsToSquare(0, 7)).toBe('h8')
  })

  it('glyphes présents pour les 12 pièces', () => {
    for (const p of 'KQRBNPkqrbnp') expect(PIECE_GLYPHS[p]).toBeTruthy()
  })
})

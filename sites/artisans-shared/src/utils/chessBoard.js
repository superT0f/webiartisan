/** Rendu d'une position FEN (pièces uniquement) vers un plateau 8×8. */

export const PIECE_GLYPHS = {
  K: '♔', Q: '♕', R: '♖', B: '♗', N: '♘', P: '♙',
  k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟',
}

export function parseFen(fen) {
  const rows = fen.split(' ')[0].split('/')
  return rows.map((row) => {
    const cells = []
    for (const ch of row) {
      if (/\d/.test(ch)) {
        for (let i = 0; i < Number(ch); i++) cells.push(null)
      } else {
        cells.push(ch)
      }
    }
    return cells
  })
}

export function squareToCoords(square) {
  return { row: 8 - Number(square[1]), col: square.charCodeAt(0) - 97 }
}

export function coordsToSquare(row, col) {
  return String.fromCharCode(97 + col) + (8 - row)
}

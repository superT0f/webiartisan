const entityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '`': '&#96;',
  '/': '&#x2F;',
}

export function escapeHtml(text) {
  if (text == null) return ''
  return String(text).replace(/[&<>"'`/]/g, (s) => entityMap[s])
}

const element = document.getElementById('tesserae-config')

/** Everything the server told the browser about this page. */
export const config = element ? JSON.parse(element.textContent || '{}') : {}

config.strings ||= {}
config.catalogue ||= []
config.document ||= { blocks: [] }

/** Translated string, falling back to the key's default. */
export function t(key, fallback = '') {
  return config.strings[key] ?? fallback
}

import fr from './locales/fr.json'

/**
 * Lightweight translation layer: messages live in `locales/<locale>.json` (French only for now)
 * and are resolved by dotted key with `{name}` placeholders. An unknown key is returned as-is so
 * a missing translation is visible instead of silently blank.
 */
type Messages = { [key: string]: string | Messages }
export type TranslationParams = Record<string, string | number>

const catalogs: Record<string, Messages> = { fr }
const locale = 'fr'

export function hasTranslation(key: string): boolean {
  return typeof resolve(key) === 'string'
}

export function t(key: string, params: TranslationParams = {}): string {
  const message = resolve(key)
  if (typeof message !== 'string') return key
  return message.replace(/\{(\w+)\}/g, (match, name: string) => (name in params ? String(params[name]) : match))
}

function resolve(key: string): string | Messages | undefined {
  let node: string | Messages | undefined = catalogs[locale]
  for (const part of key.split('.')) {
    if (node === undefined || typeof node === 'string') return undefined
    node = node[part]
  }
  return node
}

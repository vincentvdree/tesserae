import { scopedAll, scopedFirst } from './dom.js'

/**
 * Reads a whole scope into a plain object, mirroring the shape the PHP field
 * definitions expect back.
 */
export function serializeScope(scope) {
  const values = {}
  if (!scope) return values

  for (const wrapper of scopedAll(scope, '[data-tesserae-field]')) {
    const name = wrapper.dataset.tesseraeField
    if (!name) continue

    values[name] = readField(wrapper)
  }

  return values
}

export function readField(wrapper) {
  const type = wrapper.dataset.tesseraeType

  if (type === 'group') {
    return serializeScope(scopedFirst(wrapper, '[data-tesserae-scope]'))
  }

  if (type === 'repeater') {
    return scopedAll(wrapper, '[data-tesserae-row]').map((row) =>
      serializeScope(scopedFirst(row, '[data-tesserae-scope]')),
    )
  }

  return readLeaf(wrapper)
}

function readLeaf(wrapper) {
  const inputs = scopedAll(wrapper, '[data-tesserae-input]')
  if (inputs.length === 0) return null

  const first = inputs[0]

  if (first.dataset.tesseraeValueType === 'json') {
    try {
      return JSON.parse(first.value || 'null')
    } catch {
      return null
    }
  }

  if (first.type === 'checkbox') {
    if (first.dataset.tesseraeMultiple) {
      return inputs.filter((input) => input.checked).map((input) => cast(input, input.value))
    }

    return first.checked
  }

  if (first.type === 'radio') {
    const checked = inputs.find((input) => input.checked)
    return checked ? cast(checked, checked.value) : ''
  }

  if (first.tagName === 'SELECT' && first.multiple) {
    return [...first.selectedOptions].map((option) => cast(first, option.value))
  }

  if (first.type === 'number') {
    return first.value === '' ? null : Number(first.value)
  }

  return cast(first, first.value)
}

function cast(input, value) {
  if (input.dataset.tesseraeCast === 'int') {
    return value === '' ? null : Number.parseInt(value, 10)
  }

  return value
}

/**
 * Shows and hides fields according to their `conditional:` rules. Runs per
 * scope, so a rule inside a repeater row looks at that row's own values.
 */
export function applyConditionals(scope) {
  if (!scope) return

  const values = serializeScope(scope)

  for (const wrapper of scopedAll(scope, '[data-tesserae-field]')) {
    const raw = wrapper.dataset.tesseraeConditional

    if (raw) {
      let visible = true

      try {
        const { relation = 'and', rules = [] } = JSON.parse(raw)
        const results = rules.map((rule) => matches(values[rule.field], rule))
        visible = relation === 'or' ? results.some(Boolean) : results.every(Boolean)
      } catch {
        visible = true
      }

      wrapper.hidden = !visible
      wrapper.classList.toggle('is-hidden-by-condition', !visible)
    }

    const type = wrapper.dataset.tesseraeType

    if (type === 'group') {
      applyConditionals(scopedFirst(wrapper, '[data-tesserae-scope]'))
    } else if (type === 'repeater') {
      for (const row of scopedAll(wrapper, '[data-tesserae-row]')) {
        applyConditionals(scopedFirst(row, '[data-tesserae-scope]'))
      }
    }
  }
}

function matches(value, rule) {
  const expected = rule.value
  const empty = value === null || value === undefined || value === '' || value === false ||
    (Array.isArray(value) && value.length === 0)

  switch (rule.operator) {
    case '!=':
      return String(value) !== String(expected)
    case '>':
      return Number(value) > Number(expected)
    case '<':
      return Number(value) < Number(expected)
    case 'contains':
      return Array.isArray(value)
        ? value.map(String).includes(String(expected))
        : String(value ?? '').includes(String(expected))
    case 'in':
      return [].concat(expected).map(String).includes(String(value))
    case 'empty':
      return empty
    case 'not_empty':
      return !empty
    default:
      if (typeof expected === 'boolean') return Boolean(value) === expected
      return String(value) === String(expected)
  }
}

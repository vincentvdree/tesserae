/**
 * Scope-aware DOM helpers.
 *
 * A "scope" is one level of the field tree: the modal body, a group body or a
 * repeater row. Walking stops at nested field wrappers so a repeater inside a
 * repeater never leaks its values upwards.
 */

export function* scopedElements(root, selector, skip = '[data-tesserae-field]') {
  if (!root) return

  for (const child of root.children) {
    if (child.matches(selector)) {
      yield child
      continue
    }

    if (child.matches(skip)) continue

    yield* scopedElements(child, selector, skip)
  }
}

export function scopedAll(root, selector, skip) {
  return [...scopedElements(root, selector, skip)]
}

export function scopedFirst(root, selector, skip) {
  for (const element of scopedElements(root, selector, skip)) return element
  return null
}

/** The nearest element that owns values: the modal body, a group or a row. */
export function scopeOf(element) {
  return element.closest('[data-tesserae-scope]')
}

export function debounce(fn, wait = 300) {
  let timer = null

  return (...args) => {
    window.clearTimeout(timer)
    timer = window.setTimeout(() => fn(...args), wait)
  }
}

export function el(tag, attributes = {}, children = []) {
  const node = document.createElement(tag)

  for (const [key, value] of Object.entries(attributes)) {
    if (value === null || value === undefined || value === false) continue
    if (key === 'class') node.className = value
    else if (key === 'text') node.textContent = value
    else if (key === 'html') node.innerHTML = value
    else if (key.startsWith('on')) node.addEventListener(key.slice(2).toLowerCase(), value)
    else node.setAttribute(key, value === true ? '' : value)
  }

  for (const child of [].concat(children)) {
    if (child) node.append(child)
  }

  return node
}

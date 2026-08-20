import { Controller } from '@hotwired/stimulus'
import { api } from '../api.js'
import { debounce, el } from '../dom.js'

/** "Pick some posts" — search, select, reorder. */
export default class extends Controller {
  static targets = ['input', 'chosen', 'search', 'results']
  static values = { multiple: Boolean, types: Array, max: Number }

  connect() {
    this.lookup = debounce(() => this.query(), 250)
    this.titles = new Map()

    for (const chip of this.chosenTarget.querySelectorAll('.tsr-chip')) {
      this.titles.set(Number.parseInt(chip.dataset.id, 10), chip.querySelector('.tsr-chip__label')?.textContent || '')
      this.bindChip(chip)
    }
  }

  get ids() {
    try {
      const value = JSON.parse(this.inputTarget.value || 'null')
      if (this.multipleValue) return Array.isArray(value) ? value : []
      return value ? [value] : []
    } catch {
      return []
    }
  }

  set ids(next) {
    const value = this.multipleValue ? next : (next[0] ?? null)
    this.inputTarget.value = JSON.stringify(value)
    this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }))
  }

  search() {
    this.lookup()
  }

  async query() {
    const term = this.searchTarget.value.trim()

    if (term.length < 2) {
      this.resultsTarget.hidden = true
      return
    }

    try {
      const results = await api.search(term, this.typesValue || [])
      this.resultsTarget.innerHTML = ''
      this.resultsTarget.hidden = false

      if (results.length === 0) {
        this.resultsTarget.append(el('li', { class: 'tsr-posts__none', text: 'Nothing found' }))
        return
      }

      for (const result of results) {
        this.resultsTarget.append(el('li', {}, [
          el('button', {
            type: 'button',
            class: 'tsr-posts__result',
            onclick: () => this.pick(result),
          }, [
            el('span', { class: 'tsr-posts__result-title', text: result.title || `#${result.id}` }),
            el('span', { class: 'tsr-posts__result-type', text: result.subtype || '' }),
          ]),
        ]))
      }
    } catch (error) {
      this.resultsTarget.hidden = false
      this.resultsTarget.innerHTML = ''
      this.resultsTarget.append(el('li', { class: 'tsr-posts__none', text: error.message }))
    }
  }

  pick(result) {
    const ids = this.ids

    if (ids.includes(result.id)) return
    if (this.maxValue > 0 && ids.length >= this.maxValue) return

    this.titles.set(result.id, result.title || `#${result.id}`)

    const next = this.multipleValue ? [...ids, result.id] : [result.id]
    this.ids = next

    this.searchTarget.value = ''
    this.resultsTarget.hidden = true
    this.repaint(next, result.subtype)
  }

  remove(event) {
    event.preventDefault()
    const id = Number.parseInt(event.currentTarget.dataset.id, 10)
    const next = this.ids.filter((item) => item !== id)

    this.ids = next
    this.repaint(next)
  }

  repaint(ids, subtype = '') {
    this.chosenTarget.innerHTML = ''

    if (ids.length === 0) {
      this.chosenTarget.append(el('p', { class: 'tsr-posts__empty', text: 'Nothing selected yet' }))
      return
    }

    for (const id of ids) {
      const chip = el('span', { class: 'tsr-chip', 'data-id': String(id), draggable: 'true' }, [
        el('span', { class: 'tsr-chip__label', text: this.titles.get(id) || `#${id}` }),
        el('span', { class: 'tsr-chip__meta', text: subtype }),
        el('button', {
          type: 'button',
          class: 'tsr-chip__remove',
          'data-id': String(id),
          'data-action': 'tesserae-posts#remove',
          text: '×',
        }),
      ])

      this.bindChip(chip)
      this.chosenTarget.append(chip)
    }
  }

  bindChip(chip) {
    chip.addEventListener('dragstart', (event) => {
      this.dragged = chip
      event.dataTransfer.effectAllowed = 'move'
      chip.classList.add('is-tsr-dragging')
    })

    chip.addEventListener('dragend', () => {
      chip.classList.remove('is-tsr-dragging')
      this.dragged = null
      this.commitOrder()
    })

    chip.addEventListener('dragover', (event) => {
      event.preventDefault()
      if (!this.dragged || this.dragged === chip) return

      const box = chip.getBoundingClientRect()

      if (event.clientX > box.left + box.width / 2) chip.after(this.dragged)
      else chip.before(this.dragged)
    })
  }

  commitOrder() {
    const ids = [...this.chosenTarget.querySelectorAll('.tsr-chip')]
      .map((chip) => Number.parseInt(chip.dataset.id, 10))

    this.ids = ids
  }
}

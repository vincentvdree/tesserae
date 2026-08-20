import { Controller } from '@hotwired/stimulus'
import { applyConditionals } from '../serialize.js'
import { scopedAll, scopedFirst } from '../dom.js'

/** Add, duplicate, remove and reorder repeater rows. */
export default class extends Controller {
  static targets = ['rows', 'template', 'addButton']
  static values = { min: Number, max: Number, label: String }

  connect() {
    this.renumber()
    this.updateButtons()
  }

  get rows() {
    return scopedAll(this.rowsTarget, '[data-tesserae-row]')
  }

  rowOf(event) {
    return event.target.closest('[data-tesserae-row]')
  }

  add() {
    if (this.isFull()) return

    const fragment = this.templateTarget.content.cloneNode(true)
    const row = fragment.firstElementChild
    this.rowsTarget.append(fragment)

    this.renumber()
    this.updateButtons()
    applyConditionals(scopedFirst(row, '[data-tesserae-scope]'))
    this.changed()

    row.querySelector('[data-tesserae-input]:not([type=hidden])')?.focus({ preventScroll: true })
  }

  duplicate(event) {
    const row = this.rowOf(event)
    if (!row || this.isFull()) return

    // Typed values live in properties, not attributes: persist them first.
    this.persistValues(row)

    const copy = row.cloneNode(true)
    row.after(copy)

    this.renumber()
    this.updateButtons()
    this.changed()
  }

  remove(event) {
    const row = this.rowOf(event)
    if (!row || this.rows.length <= this.minValue) return

    row.remove()
    this.renumber()
    this.updateButtons()
    this.changed()
  }

  moveUp(event) {
    this.move(this.rowOf(event), -1)
  }

  moveDown(event) {
    this.move(this.rowOf(event), 1)
  }

  move(row, delta) {
    if (!row) return

    const rows = this.rows
    const index = rows.indexOf(row)
    const target = index + delta

    if (index === -1 || target < 0 || target >= rows.length) return

    if (delta < 0) rows[target].before(row)
    else rows[target].after(row)

    this.renumber()
    this.changed()
    row.querySelector('.tsr-row__handle')?.focus({ preventScroll: true })
  }

  /** Arrow keys on the drag handle reorder without a mouse. */
  handleKey(event) {
    if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return

    event.preventDefault()
    this.move(this.rowOf(event), event.key === 'ArrowUp' ? -1 : 1)
  }

  isFull() {
    return this.maxValue > 0 && this.rows.length >= this.maxValue
  }

  persistValues(root) {
    for (const input of root.querySelectorAll('input, textarea, select')) {
      if (input.type === 'checkbox' || input.type === 'radio') {
        input.toggleAttribute('checked', input.checked)
      } else if (input.tagName === 'TEXTAREA') {
        input.textContent = input.value
      } else if (input.tagName === 'SELECT') {
        for (const option of input.options) option.toggleAttribute('selected', option.selected)
      } else {
        input.setAttribute('value', input.value)
      }
    }

    for (const editable of root.querySelectorAll('[contenteditable]')) {
      editable.setAttribute('data-tesserae-html', editable.innerHTML)
    }
  }

  // — Drag reordering ———————————————————————————————————————

  startDrag(event) {
    const row = this.rowOf(event)
    if (!row) return

    this.dragged = row
    row.classList.add('is-tsr-dragging')
    this.persistValues(row)

    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', 'tesserae-row')
    event.dataTransfer.setDragImage(row, 16, 16)
  }

  dragOver(event) {
    if (!this.dragged) return

    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'

    const target = event.target.closest?.('[data-tesserae-row]')
    if (!target || target === this.dragged || !this.rowsTarget.contains(target)) return

    const box = target.getBoundingClientRect()

    if (event.clientY > box.top + box.height / 2) target.after(this.dragged)
    else target.before(this.dragged)
  }

  drop(event) {
    if (!this.dragged) return

    event.preventDefault()
    this.endDrag()
  }

  endDrag() {
    if (!this.dragged) return

    this.dragged.classList.remove('is-tsr-dragging')
    this.dragged = null

    this.renumber()
    this.changed()
  }

  renumber() {
    this.rows.forEach((row, index) => {
      const title = row.querySelector('[data-tesserae-row-title]')

      if (title) title.textContent = `${this.labelValue || 'Item'} ${index + 1}`
    })
  }

  updateButtons() {
    if (!this.hasAddButtonTarget) return

    const full = this.isFull()
    this.addButtonTarget.disabled = full
    this.addButtonTarget.hidden = full
  }

  changed() {
    this.element.dispatchEvent(new Event('input', { bubbles: true }))
  }
}

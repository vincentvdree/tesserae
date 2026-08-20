import { Controller } from '@hotwired/stimulus'
import { el } from '../dom.js'

/** Image, gallery and file fields: everything that stores attachment ids. */
export default class extends Controller {
  static targets = ['input', 'list']
  static values = { multiple: Boolean, accept: String, kind: String }

  get value() {
    try {
      return JSON.parse(this.inputTarget.value || 'null')
    } catch {
      return null
    }
  }

  set value(next) {
    this.inputTarget.value = JSON.stringify(next ?? (this.multipleValue ? [] : null))
    this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }))
  }

  get ids() {
    const value = this.value
    return this.multipleValue ? (value || []) : (value ? [value] : [])
  }

  async open() {
    const library = window.Tesserae?.mediaLibrary
    if (!library) return

    const selection = await library.open({
      multiple: this.multipleValue,
      accept: this.acceptValue,
      selected: this.ids,
    })

    if (!selection || selection.length === 0) return

    if (this.multipleValue) {
      const ids = [...this.ids]

      for (const item of selection) {
        if (!ids.includes(item.id)) ids.push(item.id)
      }

      this.value = ids
      this.paint(selection)
    } else {
      this.value = selection[0].id
      this.paint(selection)
    }
  }

  clear() {
    this.value = this.multipleValue ? [] : null
    this.paint([])
  }

  removeOne(event) {
    event.preventDefault()
    const id = Number.parseInt(event.currentTarget.dataset.id, 10)

    this.value = this.ids.filter((item) => item !== id)
    event.currentTarget.closest('.tsr-media__item')?.remove()

    if (this.ids.length === 0) this.paint([])
  }

  /** Repaints from the attachments the library just handed back. */
  paint(items) {
    const known = new Map(items.map((item) => [item.id, item]))
    this.listTarget.innerHTML = ''

    if (this.ids.length === 0) {
      this.listTarget.append(el('p', { class: 'tsr-media__empty', text: 'Nothing selected' }))
      return
    }

    for (const id of this.ids) {
      const item = known.get(id)
      const thumb = item?.media_details?.sizes?.thumbnail?.source_url || item?.source_url || ''

      if (this.kindValue === 'file' || (item && item.media_type !== 'image')) {
        this.listTarget.append(el('p', { class: 'tsr-media__file', text: item?.title?.rendered || `#${id}` }))
        continue
      }

      const figure = el('figure', { class: 'tsr-media__item', 'data-id': String(id) }, [
        el('img', { src: thumb, alt: '' }),
      ])

      if (this.multipleValue) {
        figure.append(el('button', {
          type: 'button',
          class: 'tsr-media__remove',
          'data-id': String(id),
          'data-action': 'tesserae-media#removeOne',
          text: '×',
        }))
      }

      this.listTarget.append(figure)
    }
  }
}

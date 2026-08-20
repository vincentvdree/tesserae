import { Controller } from '@hotwired/stimulus'
import { t } from '../config.js'
import { el } from '../dom.js'

/** The "add block" dialog. */
export default class extends Controller {
  static targets = ['search', 'list']

  get editor() {
    return window.Tesserae?.editor || null
  }

  open() {
    this.blocks = []

    if (this.hasListTarget) {
      this.listTarget.innerHTML = `<p class="tsr-picker__empty">${t('loading', 'Loading…')}</p>`
    }

    if (!this.element.open) this.element.showModal()
    if (this.hasSearchTarget) {
      this.searchTarget.value = ''
      window.setTimeout(() => this.searchTarget.focus(), 30)
    }
  }

  close() {
    if (this.element.open) this.element.close()
  }

  render(blocks) {
    this.blocks = blocks || []
    this.paint(this.blocks)
  }

  filter() {
    const term = (this.searchTarget.value || '').trim().toLowerCase()

    if (term === '') {
      this.paint(this.blocks)
      return
    }

    this.paint(
      this.blocks.filter((block) =>
        [block.label, block.description, block.type, ...(block.keywords || [])]
          .join(' ')
          .toLowerCase()
          .includes(term),
      ),
    )
  }

  paint(blocks) {
    if (!this.hasListTarget) return

    this.listTarget.innerHTML = ''

    if (blocks.length === 0) {
      this.listTarget.append(el('p', { class: 'tsr-picker__empty', text: t('noResults', 'Nothing found') }))
      return
    }

    const categories = new Map()

    for (const block of blocks) {
      const category = block.category || 'content'
      if (!categories.has(category)) categories.set(category, [])
      categories.get(category).push(block)
    }

    for (const [category, entries] of categories) {
      this.listTarget.append(el('h3', { class: 'tsr-picker__category', text: category }))

      const grid = el('div', { class: 'tsr-picker__grid' })

      for (const block of entries) {
        const card = el('button', {
          type: 'button',
          class: `tsr-picker__card${block.allowed ? '' : ' is-disabled'}`,
          disabled: !block.allowed,
          title: block.allowed ? block.description : block.reason,
          onclick: () => {
            this.close()
            this.editor?.insertType(block.type)
          },
        }, [
          el('span', { class: 'tsr-picker__icon', html: block.icon || '◻' }),
          el('span', { class: 'tsr-picker__label', text: block.label }),
          el('span', {
            class: 'tsr-picker__description',
            text: block.allowed ? block.description || '' : block.reason || '',
          }),
        ])

        grid.append(card)
      }

      this.listTarget.append(grid)
    }
  }
}

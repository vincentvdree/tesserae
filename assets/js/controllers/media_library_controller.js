import { Controller } from '@hotwired/stimulus'
import { api } from '../api.js'
import { debounce, el } from '../dom.js'

/**
 * A small media browser built straight on the core REST API — no wp.media, no
 * Backbone, no admin scripts on the front end.
 */
export default class extends Controller {
  static targets = ['grid', 'search', 'hint', 'file']

  connect() {
    window.Tesserae = window.Tesserae || {}
    window.Tesserae.mediaLibrary = this

    this.items = []
    this.selected = new Set()
    this.page = 1
    this.exhausted = false
    this.debouncedSearch = debounce(() => this.reload(), 300)
  }

  open({ multiple = false, accept = '', selected = [] } = {}) {
    this.multiple = multiple
    this.accept = accept
    this.selected = new Set()
    this.previouslySelected = selected

    if (!this.element.open) this.element.showModal()
    this.reload()

    return new Promise((resolve) => {
      this.resolve = resolve
    })
  }

  close() {
    if (this.element.open) this.element.close()
    this.resolve?.(null)
    this.resolve = null
  }

  confirm() {
    const chosen = this.items.filter((item) => this.selected.has(item.id))

    if (this.element.open) this.element.close()
    this.resolve?.(chosen)
    this.resolve = null
  }

  search() {
    this.debouncedSearch()
  }

  async reload() {
    this.page = 1
    this.exhausted = false
    this.items = []
    this.gridTarget.innerHTML = ''
    await this.loadMore()
  }

  async loadMore() {
    if (this.loading || this.exhausted) return

    this.loading = true
    this.setHint('Loading…')

    try {
      const items = await api.media({
        search: this.hasSearchTarget ? this.searchTarget.value : '',
        page: this.page,
        mime: this.accept === 'image' ? 'image' : '',
      })

      if (!Array.isArray(items) || items.length === 0) {
        this.exhausted = true
      } else {
        this.items.push(...items)
        this.paint(items)
        this.page += 1
      }

      this.setHint(this.items.length === 0 ? 'No media found' : '')
    } catch (error) {
      this.setHint(error.message)
    } finally {
      this.loading = false
    }
  }

  maybeLoadMore() {
    const { scrollTop, scrollHeight, clientHeight } = this.gridTarget

    if (scrollTop + clientHeight >= scrollHeight - 200) this.loadMore()
  }

  paint(items, position = 'append') {
    const fragment = document.createDocumentFragment()

    for (const item of items) {
      const thumb = item.media_details?.sizes?.thumbnail?.source_url || item.source_url
      const isImage = item.media_type === 'image'

      const tile = el('button', {
        type: 'button',
        class: 'tsr-media-tile',
        'data-id': String(item.id),
        title: item.title?.rendered || '',
        onclick: () => this.toggle(item, tile),
      }, [
        isImage
          ? el('img', { src: thumb, alt: item.alt_text || '', loading: 'lazy' })
          : el('span', { class: 'tsr-media-tile__file', text: (item.mime_type || '').split('/').pop() }),
        el('span', { class: 'tsr-media-tile__label', text: item.title?.rendered || `#${item.id}` }),
      ])

      if (this.previouslySelected?.includes(item.id)) tile.classList.add('is-known')
      if (this.selected.has(item.id)) tile.classList.add('is-selected')

      fragment.append(tile)
    }

    if (position === 'prepend') this.gridTarget.prepend(fragment)
    else this.gridTarget.append(fragment)
  }

  toggle(item, tile) {
    if (!this.multiple) {
      this.selected.clear()

      for (const node of this.gridTarget.querySelectorAll('.is-selected')) {
        node.classList.remove('is-selected')
      }
    }

    if (this.selected.has(item.id)) {
      this.selected.delete(item.id)
      tile.classList.remove('is-selected')
    } else {
      this.selected.add(item.id)
      tile.classList.add('is-selected')
    }

    this.setHint(`${this.selected.size} selected`)

    if (!this.multiple && this.selected.size === 1) this.confirm()
  }

  async upload(event) {
    const files = [...(event.target.files || [])]
    if (files.length === 0) return

    this.setHint(`Uploading ${files.length} file(s)…`)

    try {
      const uploaded = []

      for (const file of files) {
        uploaded.push(await api.upload(file))
      }

      this.items.unshift(...uploaded)

      for (const item of uploaded) this.selected.add(item.id)

      this.paint(uploaded, 'prepend')

      this.setHint(`${uploaded.length} uploaded`)
      if (!this.multiple) this.confirm()
    } catch (error) {
      this.setHint(error.message)
    } finally {
      event.target.value = ''
    }
  }

  setHint(message) {
    if (this.hasHintTarget) this.hintTarget.textContent = message
  }
}

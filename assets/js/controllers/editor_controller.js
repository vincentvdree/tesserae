import { Controller } from '@hotwired/stimulus'
import { api } from '../api.js'
import { config, t } from '../config.js'
import { Store } from '../store.js'
import { debounce } from '../dom.js'

/**
 * The editor itself: owns the document, talks to the REST API and keeps the
 * page in sync with what the user is doing.
 */
export default class extends Controller {
  static targets = ['status', 'save', 'modal', 'picker', 'mediaModal', 'undo', 'redo']

  connect() {
    this.store = new Store(config.document)
    this.postId = config.postId
    this.activeId = null
    this.pendingIndex = -1
    this.committedForEdit = false
    this.canvas = document.querySelector('[data-tesserae-canvas]')

    window.Tesserae = window.Tesserae || {}
    window.Tesserae.editor = this

    this.preview = debounce((id) => this.refreshBlock(id), 350)
    this.onKeydown = this.handleKeydown.bind(this)
    this.onBeforeUnload = this.handleBeforeUnload.bind(this)

    document.addEventListener('keydown', this.onKeydown)
    window.addEventListener('beforeunload', this.onBeforeUnload)
    document.body.classList.add('tsr-editing')

    if (Array.isArray(config.errors) && config.errors.length > 0) {
      this.setStatus(config.errors.join(' · '), 'error')
    }

    this.updateChrome()
  }

  disconnect() {
    document.removeEventListener('keydown', this.onKeydown)
    window.removeEventListener('beforeunload', this.onBeforeUnload)
    document.body.classList.remove('tsr-editing')
  }

  // — Chrome ————————————————————————————————————————————————

  setStatus(message, tone = '') {
    if (!this.hasStatusTarget) return

    this.statusTarget.textContent = message
    this.statusTarget.dataset.tone = tone
  }

  updateChrome() {
    if (this.hasUndoTarget) this.undoTarget.disabled = !this.store.canUndo
    if (this.hasRedoTarget) this.redoTarget.disabled = !this.store.canRedo

    if (this.hasSaveTarget) {
      this.saveTarget.classList.toggle('is-dirty', this.store.dirty)
    }

    if (this.store.dirty) this.setStatus(t('unsaved', 'Unsaved changes'), 'dirty')
  }

  handleBeforeUnload(event) {
    if (!this.store.dirty) return

    event.preventDefault()
    event.returnValue = t('leaveWarning', 'You have unsaved changes.')
  }

  handleKeydown(event) {
    const meta = event.metaKey || event.ctrlKey
    if (!meta) {
      if (event.key === 'Escape' && this.activeId) this.closeModal()
      return
    }

    const key = event.key.toLowerCase()

    if (key === 's') {
      event.preventDefault()
      this.save()
      return
    }

    const typing = event.target instanceof HTMLElement &&
      (event.target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName))

    if (typing) return

    if (key === 'z' && !event.shiftKey) {
      event.preventDefault()
      this.undo()
    } else if ((key === 'z' && event.shiftKey) || key === 'y') {
      event.preventDefault()
      this.redo()
    }
  }

  // — Blocks ————————————————————————————————————————————————

  elementFor(id) {
    return this.canvas?.querySelector(`[data-tesserae-id="${CSS.escape(id)}"]`) || null
  }

  blockIds() {
    return [...(this.canvas?.querySelectorAll(':scope > [data-tesserae-id]') || [])]
      .map((element) => element.dataset.tesseraeId)
  }

  async editBlock(id, field = null) {
    const block = this.store.find(id)
    if (!block) return

    this.activeId = id
    this.committedForEdit = false
    this.highlight(id)

    const modal = this.modalController()
    modal?.showLoading(t('loading', 'Loading…'))

    try {
      const { html, label } = await api.form(this.postId, block)
      modal?.open({ html, label, field })
    } catch (error) {
      this.setStatus(error.message, 'error')
      modal?.close()
    }
  }

  highlight(id) {
    for (const element of this.canvas?.querySelectorAll('.is-tsr-active') || []) {
      element.classList.remove('is-tsr-active')
    }

    const element = this.elementFor(id)

    if (element) {
      element.classList.add('is-tsr-active')
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }

  /** Called by the modal on every change of a field. */
  valuesChanged(values, settings) {
    if (!this.activeId) return

    if (!this.committedForEdit) {
      this.store.commit()
      this.committedForEdit = true
    }

    this.store.update(this.activeId, { values, settings })
    this.updateChrome()
    this.preview(this.activeId)
  }

  async refreshBlock(id) {
    const block = this.store.find(id)
    const element = this.elementFor(id)
    if (!block || !element) return

    try {
      const { html } = await api.renderBlock(this.postId, block, this.store.indexOf(id), this.store.blocks.length)
      this.replaceElement(element, html, id)
    } catch (error) {
      this.setStatus(error.message, 'error')
    }
  }

  replaceElement(element, html, id) {
    const template = document.createElement('template')
    template.innerHTML = html.trim()
    const next = template.content.firstElementChild
    if (!next) return null

    element.replaceWith(next)

    if (id === this.activeId) next.classList.add('is-tsr-active')

    return next
  }

  async refreshDocument() {
    if (!this.canvas) return

    try {
      const { html } = await api.renderDocument(this.postId, this.store.blocks)
      const template = document.createElement('template')
      template.innerHTML = html.trim()
      const next = template.content.firstElementChild

      if (next) {
        this.canvas.replaceWith(next)
        this.canvas = next
      }
    } catch (error) {
      this.setStatus(error.message, 'error')
    }
  }

  // — Adding ————————————————————————————————————————————————

  openPickerAtEnd() {
    this.openPicker(this.store.blocks.length)
  }

  async openPicker(index) {
    this.pendingIndex = index
    const picker = this.pickerController()
    picker?.open()

    try {
      const { blocks } = await api.catalogue(this.postId, index, this.store.blocks)
      picker?.render(blocks)
    } catch (error) {
      this.setStatus(error.message, 'error')
      picker?.close()
    }
  }

  async insertType(type) {
    const index = this.pendingIndex < 0 ? this.store.blocks.length : this.pendingIndex

    try {
      const { html, values, id } = await api.renderBlock(
        this.postId,
        { type, values: {}, settings: {} },
        index,
        this.store.blocks.length + 1,
      )

      const block = { id, type, values, settings: { anchor: '', class: '', hidden: false } }
      this.store.insert(block, index)

      const template = document.createElement('template')
      template.innerHTML = html.trim()
      const element = template.content.firstElementChild
      if (!element || !this.canvas) return

      const siblings = this.canvas.querySelectorAll(':scope > [data-tesserae-id]')
      const reference = siblings[index]

      if (reference) reference.before(element)
      else this.canvas.append(element)

      this.canvas.querySelector('[data-tesserae-empty]')?.remove()
      this.updateChrome()
      this.editBlock(id)
    } catch (error) {
      this.setStatus(error.message, 'error')
    }
  }

  async duplicateBlock(id) {
    const block = this.store.find(id)
    if (!block) return

    const index = this.store.indexOf(id) + 1

    try {
      const { html, id: newId } = await api.renderBlock(
        this.postId,
        { type: block.type, values: block.values, settings: block.settings },
        index,
        this.store.blocks.length + 1,
      )

      this.store.insert({ ...structuredClone(block), id: newId }, index)

      const template = document.createElement('template')
      template.innerHTML = html.trim()
      const element = template.content.firstElementChild
      if (element) this.elementFor(id)?.after(element)

      this.updateChrome()
      this.setStatus(t('saved', ''), '')
    } catch (error) {
      this.setStatus(error.message, 'error')
    }
  }

  removeBlock(id) {
    if (!window.confirm(t('confirmRemove', 'Remove this block?'))) return

    if (this.activeId === id) this.closeModal()

    this.store.remove(id)
    this.elementFor(id)?.remove()
    this.updateChrome()

    // An empty canvas has nothing left to click, so hand back the empty state.
    if (this.store.blocks.length === 0) this.refreshDocument()
  }

  moveBlock(id, delta) {
    const target = this.store.move(id, delta)
    const element = this.elementFor(id)
    if (!element || !this.canvas) return

    const siblings = [...this.canvas.querySelectorAll(':scope > [data-tesserae-id]')].filter((node) => node !== element)
    const reference = siblings[target]

    if (reference) reference.before(element)
    else this.canvas.append(element)

    element.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    this.updateChrome()
  }

  // — Persisting ————————————————————————————————————————————

  async save() {
    if (this.hasSaveTarget) this.saveTarget.disabled = true
    this.setStatus(t('saving', 'Saving…'))

    try {
      const { document: saved } = await api.save(this.postId, this.store.blocks)
      this.store.blocks = saved.blocks
      this.store.markSaved()
      this.setStatus(t('saved', 'Saved'), 'ok')
      window.setTimeout(() => {
        if (!this.store.dirty) this.setStatus('')
      }, 2500)
    } catch (error) {
      this.setStatus(`${t('saveFailed', 'Could not save')}: ${error.message}`, 'error')
    } finally {
      if (this.hasSaveTarget) this.saveTarget.disabled = false
      this.updateChrome()
    }
  }

  async undo() {
    if (!this.store.undo()) return

    this.closeModal()
    await this.refreshDocument()
    this.updateChrome()
  }

  async redo() {
    if (!this.store.redo()) return

    this.closeModal()
    await this.refreshDocument()
    this.updateChrome()
  }

  // — Dialog plumbing ———————————————————————————————————————

  modalController() {
    return this.hasModalTarget
      ? this.application.getControllerForElementAndIdentifier(this.modalTarget, 'tesserae-modal')
      : null
  }

  pickerController() {
    return this.hasPickerTarget
      ? this.application.getControllerForElementAndIdentifier(this.pickerTarget, 'tesserae-picker')
      : null
  }

  closeModal() {
    this.modalController()?.close()
  }

  modalClosed() {
    this.activeId = null
    document.body.classList.remove('tsr-panel-open', 'tsr-modal-open')

    for (const element of this.canvas?.querySelectorAll('.is-tsr-active') || []) {
      element.classList.remove('is-tsr-active')
    }
  }
}

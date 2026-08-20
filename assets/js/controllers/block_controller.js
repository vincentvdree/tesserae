import { Controller } from '@hotwired/stimulus'
import { config, t } from '../config.js'
import { el } from '../dom.js'

/**
 * Attached to every block on the page while editing: adds the hover chrome,
 * turns a click into "edit this block" and handles drag reordering.
 */
export default class extends Controller {
  connect() {
    this.element.classList.add('tsr-block--editable')
    this.injectChrome()

    this.onClick = this.handleClick.bind(this)
    this.element.addEventListener('click', this.onClick)
  }

  disconnect() {
    this.element.removeEventListener('click', this.onClick)
    this.element.querySelector('.tsr-block__chrome')?.remove()
    this.element.querySelectorAll('.tsr-block__insert').forEach((node) => node.remove())
  }

  get editor() {
    return window.Tesserae?.editor || null
  }

  get id() {
    return this.element.dataset.tesseraeId
  }

  get index() {
    const siblings = [...(this.element.parentElement?.children || [])].filter((node) => node.dataset.tesseraeId)

    return siblings.indexOf(this.element)
  }

  injectChrome() {
    const label = this.element.dataset.tesseraeLabel || this.element.dataset.tesseraeType || 'Block'

    const button = (text, title, handler, className = '') =>
      el('button', {
        type: 'button',
        class: `tsr-block__btn ${className}`.trim(),
        title,
        'aria-label': title,
        text,
        onclick: (event) => {
          event.preventDefault()
          event.stopPropagation()
          handler()
        },
      })

    const handle = el('span', {
      class: 'tsr-block__handle',
      title: 'Drag to reorder',
      text: '⠿',
      draggable: 'true',
    })

    handle.addEventListener('dragstart', (event) => this.dragStart(event))
    handle.addEventListener('dragend', () => this.dragEnd())

    const chrome = el('div', { class: 'tsr-block__chrome' }, [
      handle,
      el('span', { class: 'tsr-block__label', text: label }),
      button('✎', t('editBlock', 'Edit'), () => this.editor?.editBlock(this.id)),
      button('↑', t('moveUp', 'Move up'), () => this.editor?.moveBlock(this.id, -1)),
      button('↓', t('moveDown', 'Move down'), () => this.editor?.moveBlock(this.id, 1)),
      button('⧉', t('duplicate', 'Duplicate'), () => this.editor?.duplicateBlock(this.id)),
      button('✕', t('remove', 'Remove'), () => this.editor?.removeBlock(this.id), 'tsr-block__btn--danger'),
    ])

    const insert = (position) =>
      el('button', {
        type: 'button',
        class: `tsr-block__insert tsr-block__insert--${position}`,
        title: t('addBlock', 'Add block'),
        text: '+',
        onclick: (event) => {
          event.preventDefault()
          event.stopPropagation()
          this.editor?.openPicker(position === 'before' ? this.index : this.index + 1)
        },
      })

    this.element.prepend(chrome)
    this.element.prepend(insert('before'))
    this.element.append(insert('after'))

    if (this.element.classList.contains('tsr-block--hidden')) {
      chrome.append(el('span', { class: 'tsr-block__flag', text: t('hiddenBlock', 'Hidden') }))
    }
  }

  handleClick(event) {
    if (event.target.closest('.tsr-block__chrome, .tsr-block__insert')) return

    // Links and buttons inside a block should not navigate while editing.
    const interactive = event.target.closest('a, button, input, textarea, select, [contenteditable]')
    if (interactive) event.preventDefault()

    const field = event.target.closest('[data-tesserae-edit-field]')?.dataset.tesseraeEditField || null

    this.editor?.editBlock(this.id, field)
  }

  // — Drag reordering ———————————————————————————————————————

  dragStart(event) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', this.id)
    event.dataTransfer.setDragImage(this.element, 20, 20)

    this.element.classList.add('is-tsr-dragging')
    document.body.classList.add('tsr-dragging')

    this.onDragOver = (moveEvent) => this.dragOver(moveEvent)
    this.onDrop = (dropEvent) => {
      dropEvent.preventDefault()
      this.dragEnd()
    }

    this.canvas = this.element.parentElement
    this.canvas?.addEventListener('dragover', this.onDragOver)
    this.canvas?.addEventListener('drop', this.onDrop)
  }

  dragOver(event) {
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'

    const target = event.target.closest?.('[data-tesserae-id]')
    if (!target || target === this.element || target.parentElement !== this.canvas) return

    const box = target.getBoundingClientRect()
    const after = event.clientY > box.top + box.height / 2

    if (after) target.after(this.element)
    else target.before(this.element)
  }

  dragEnd() {
    this.element.classList.remove('is-tsr-dragging')
    document.body.classList.remove('tsr-dragging')
    this.canvas?.removeEventListener('dragover', this.onDragOver)
    this.canvas?.removeEventListener('drop', this.onDrop)

    const ids = [...(this.canvas?.querySelectorAll(':scope > [data-tesserae-id]') || [])]
      .map((node) => node.dataset.tesseraeId)

    if (this.editor?.store.reorder(ids)) this.editor.updateChrome()
  }
}

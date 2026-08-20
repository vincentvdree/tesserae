import { Controller } from '@hotwired/stimulus'
import { t } from '../config.js'
import { applyConditionals, serializeScope } from '../serialize.js'
import { debounce } from '../dom.js'

/**
 * The editing panel. It renders whatever HTML the server sent for the block's
 * fields and reports changes back to the editor, which re-renders the preview.
 */
export default class extends Controller {
  static targets = ['title', 'body', 'hint']

  connect() {
    this.notify = debounce(() => this.push(), 120)
    this.element.addEventListener('close', () => {
      this.element.classList.remove('is-visible')
      document.body.classList.remove('tsr-modal-open', 'tsr-panel-open')
    })
  }

  get editor() {
    return window.Tesserae?.editor || null
  }

  showLoading(message) {
    if (this.hasBodyTarget) this.bodyTarget.innerHTML = `<p class="tsr-modal__loading">${message}</p>`
    this.show()
  }

  open({ html, label, field }) {
    if (this.hasTitleTarget) this.titleTarget.textContent = label || ''
    if (this.hasBodyTarget) this.bodyTarget.innerHTML = html

    this.show()
    this.selectFirstTab()
    applyConditionals(this.valuesScope())

    if (field) this.focusField(field)
    else this.firstInput()?.focus({ preventScroll: true })

    this.setHint('')
  }

  /**
   * The panel is a non-modal dialog so the page stays clickable behind it. It
   * slides in, and the page makes room for it rather than being covered.
   */
  show() {
    window.clearTimeout(this.closeTimer)

    if (!this.element.open) this.element.show()

    document.body.classList.add('tsr-modal-open', 'tsr-panel-open')
    window.requestAnimationFrame(() => this.element.classList.add('is-visible'))
  }

  close() {
    if (!this.element.open) return

    this.element.classList.remove('is-visible')
    document.body.classList.remove('tsr-modal-open', 'tsr-panel-open')

    // Let the slide-out finish before the dialog leaves the layout.
    window.clearTimeout(this.closeTimer)
    this.closeTimer = window.setTimeout(() => {
      if (!this.element.classList.contains('is-visible')) this.element.close()
    }, 260)
  }

  setHint(message) {
    if (this.hasHintTarget) this.hintTarget.textContent = message
  }

  valuesScope() {
    return this.element.querySelector('[data-tesserae-values-scope]')
  }

  firstInput() {
    return this.element.querySelector('[data-tesserae-input]:not([type=hidden])')
  }

  focusField(name) {
    const wrapper = this.element.querySelector(`[data-tesserae-field="${CSS.escape(name)}"]`)
    if (!wrapper) return

    const panel = wrapper.closest('[data-tesserae-tab-panel]')
    if (panel) this.selectTabBySlug(panel.dataset.tesseraeTabPanel)

    wrapper.scrollIntoView({ block: 'nearest' })
    wrapper.querySelector('[data-tesserae-input]:not([type=hidden]), [contenteditable]')?.focus({ preventScroll: true })
    wrapper.classList.add('is-tsr-focused')
    window.setTimeout(() => wrapper.classList.remove('is-tsr-focused'), 1200)
  }

  /** Fired by any input inside the panel. */
  changed(event) {
    if (event?.target?.closest('[data-tesserae-ignore]')) return

    applyConditionals(this.valuesScope())
    this.notify()
  }

  push() {
    const values = serializeScope(this.valuesScope())
    const settings = {}

    for (const input of this.element.querySelectorAll('[data-tesserae-setting]')) {
      settings[input.dataset.tesseraeSetting] = input.type === 'checkbox' ? input.checked : input.value
    }

    this.editor?.valuesChanged(values, settings)
    this.setHint(t('unsaved', ''))
  }

  selectFirstTab() {
    const first = this.element.querySelector('.tsr-tab')

    if (first) this.selectTabBySlug(first.dataset.tab)
    else this.showAllPanels()
  }

  showAllPanels() {
    for (const panel of this.element.querySelectorAll('[data-tesserae-tab-panel]')) {
      panel.hidden = false
    }
  }

  selectTab(event) {
    this.selectTabBySlug(event.currentTarget.dataset.tab)
  }

  selectTabBySlug(slug) {
    for (const tab of this.element.querySelectorAll('.tsr-tab')) {
      tab.classList.toggle('is-active', tab.dataset.tab === slug)
    }

    for (const panel of this.element.querySelectorAll('[data-tesserae-tab-panel]')) {
      panel.hidden = panel.dataset.tesseraeTabPanel !== slug
    }
  }
}

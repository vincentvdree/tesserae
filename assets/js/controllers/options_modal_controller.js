import { Controller } from '@hotwired/stimulus'
import { api } from '../api.js'
import { t } from '../config.js'
import { applyConditionals, serializeScope } from '../serialize.js'

/**
 * The "Site Options" dialog opened from the admin bar while editing. Every
 * options page the current user can reach is pre-rendered into its own
 * hidden panel by the server; opening the dialog just swaps which panel
 * shows, so there is nothing to fetch on open — only on save.
 */
export default class extends Controller {
  static targets = ['title', 'body', 'hint', 'save']

  connect() {
    window.Tesserae = window.Tesserae || {}
    window.Tesserae.optionsModal = this
    this.slug = null

    for (const link of document.querySelectorAll('[id^="wp-admin-bar-tesserae-options-page-"] > a')) {
      const slug = link.closest('li').id.replace('wp-admin-bar-tesserae-options-page-', '')

      link.addEventListener('click', (event) => {
        event.preventDefault()
        this.open(slug, link.textContent.trim())
      })
    }

    this.element.addEventListener('close', () => this.element.classList.remove('is-visible'))
  }

  panel(slug = this.slug) {
    return this.hasBodyTarget ? this.bodyTarget.querySelector(`[data-tesserae-options-page="${CSS.escape(slug)}"]`) : null
  }

  open(slug, label = '') {
    const panel = this.panel(slug)
    if (!panel) return

    if (this.slug && this.slug !== slug) this.panel(this.slug)?.setAttribute('hidden', '')

    this.slug = slug
    panel.hidden = false

    if (this.hasTitleTarget) this.titleTarget.textContent = label
    this.setHint('')

    if (!this.element.open) this.element.show()

    document.body.classList.add('tsr-modal-open')
    window.requestAnimationFrame(() => this.element.classList.add('is-visible'))

    this.selectFirstTab(panel)
    applyConditionals(this.scopeOf(panel))
    panel.querySelector('[data-tesserae-input]:not([type=hidden]), [contenteditable]')?.focus({ preventScroll: true })
  }

  close() {
    if (!this.element.open) return

    this.element.classList.remove('is-visible')
    document.body.classList.remove('tsr-modal-open')

    window.clearTimeout(this.closeTimer)
    this.closeTimer = window.setTimeout(() => {
      if (!this.element.classList.contains('is-visible')) this.element.close()
    }, 260)
  }

  closed() {
    document.body.classList.remove('tsr-modal-open')
  }

  scopeOf(panel) {
    return panel?.querySelector('[data-tesserae-values-scope]') || null
  }

  /** Fired by any input inside whichever panel is currently open. */
  changed(event) {
    if (!this.slug || event?.target?.closest('[data-tesserae-ignore]')) return

    applyConditionals(this.scopeOf(this.panel()))
  }

  selectFirstTab(panel) {
    const first = panel.querySelector('.tsr-tab')
    if (first) this.selectTabBySlug(panel, first.dataset.tab)
  }

  selectTab(event) {
    const panel = event.currentTarget.closest('[data-tesserae-options-page]')
    if (panel) this.selectTabBySlug(panel, event.currentTarget.dataset.tab)
  }

  selectTabBySlug(panel, tab) {
    for (const button of panel.querySelectorAll('.tsr-tab')) {
      button.classList.toggle('is-active', button.dataset.tab === tab)
    }

    for (const section of panel.querySelectorAll('[data-tesserae-tab-panel]')) {
      section.hidden = section.dataset.tesseraeTabPanel !== tab
    }
  }

  async save() {
    const panel = this.panel()
    if (!panel || !this.slug) return

    if (this.hasSaveTarget) this.saveTarget.disabled = true
    this.setHint(t('saving', 'Saving…'))

    try {
      await api.saveOptions(this.slug, serializeScope(this.scopeOf(panel)))
      this.setHint(t('saved', 'Saved'))
    } catch (error) {
      this.setHint(`${t('saveFailed', 'Could not save')}: ${error.message}`)
    } finally {
      if (this.hasSaveTarget) this.saveTarget.disabled = false
    }
  }

  setHint(message) {
    if (this.hasHintTarget) this.hintTarget.textContent = message
  }
}

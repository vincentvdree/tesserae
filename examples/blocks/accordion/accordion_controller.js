import { Controller } from '@hotwired/stimulus'

/** Opens and closes the answers. */
export default class extends Controller {
  static targets = ['item', 'panel']
  static values = { single: Boolean }

  toggle(event) {
    const button = event.currentTarget
    const panel = document.getElementById(button.getAttribute('aria-controls'))
    if (!panel) return

    const open = button.getAttribute('aria-expanded') === 'true'

    if (this.singleValue && !open) {
      for (const other of this.element.querySelectorAll('.accordion__toggle[aria-expanded="true"]')) {
        other.setAttribute('aria-expanded', 'false')
        document.getElementById(other.getAttribute('aria-controls'))?.setAttribute('hidden', '')
      }
    }

    button.setAttribute('aria-expanded', open ? 'false' : 'true')
    panel.toggleAttribute('hidden', open)
  }
}

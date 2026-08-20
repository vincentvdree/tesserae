import { Controller } from '@hotwired/stimulus'

/** A palette of predefined colours. */
export default class extends Controller {
  static targets = ['input']

  pick(event) {
    const value = event.currentTarget.dataset.value
    const same = this.inputTarget.value === value

    this.inputTarget.value = same ? '' : value

    for (const swatch of this.element.querySelectorAll('.tsr-swatch')) {
      swatch.classList.toggle('is-active', !same && swatch.dataset.value === value)
    }

    this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }))
  }
}

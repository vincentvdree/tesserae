import { Controller } from '@hotwired/stimulus'

/**
 * Nudges the hero image as the page scrolls. Registered automatically as
 * data-controller="hero" because the file is called hero_controller.js.
 */
export default class extends Controller {
  static targets = ['media']

  connect() {
    if (!this.hasMediaTarget || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    this.onScroll = () => this.update()
    window.addEventListener('scroll', this.onScroll, { passive: true })
    this.update()
  }

  disconnect() {
    if (this.onScroll) window.removeEventListener('scroll', this.onScroll)
  }

  update() {
    const box = this.element.getBoundingClientRect()
    if (box.bottom < 0 || box.top > window.innerHeight) return

    const progress = Math.min(1, Math.max(-1, -box.top / window.innerHeight))
    this.mediaTarget.style.setProperty('--hero-shift', `${(progress * 28).toFixed(2)}px`)
  }
}

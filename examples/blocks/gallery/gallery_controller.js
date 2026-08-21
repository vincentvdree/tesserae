import { Controller } from '@hotwired/stimulus'

/** Click an image, see it big. Arrow keys move through the set. */
export default class extends Controller {
  static targets = ['lightbox', 'lightboxImage']

  open(event) {
    const button = event.currentTarget
    this.index = Number.parseInt(button.dataset.index, 10) || 0

    this.show(button.dataset.full)
  }

  show(source) {
    if (!this.hasLightboxTarget) return

    this.lightboxImageTarget.src = source
    if (!this.lightboxTarget.open) this.lightboxTarget.showModal()

    this.onKey ||= (event) => this.navigate(event)
    document.addEventListener('keydown', this.onKey)
  }

  navigate(event) {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return

    const buttons = [...this.element.querySelectorAll('.gallery__item')]
    if (buttons.length === 0) return

    const step = event.key === 'ArrowRight' ? 1 : -1
    this.index = (this.index + step + buttons.length) % buttons.length
    this.lightboxImageTarget.src = buttons[this.index].dataset.full
  }

  close() {
    if (this.hasLightboxTarget && this.lightboxTarget.open) this.lightboxTarget.close()
    if (this.onKey) document.removeEventListener('keydown', this.onKey)
  }
}

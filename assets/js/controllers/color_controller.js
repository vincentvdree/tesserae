import { Controller } from '@hotwired/stimulus'

/** Keeps the native colour picker and the hex field in step. */
export default class extends Controller {
  static targets = ['picker', 'text']

  fromPicker() {
    this.textTarget.value = this.pickerTarget.value
    this.textTarget.dispatchEvent(new Event('input', { bubbles: true }))
  }

  fromText() {
    const value = this.textTarget.value.trim()

    if (/^#[0-9a-f]{6}$/i.test(value)) this.pickerTarget.value = value
  }
}

import { Controller } from '@hotwired/stimulus'
import { el } from '../dom.js'

const COMMANDS = {
  bold: { label: 'B', command: 'bold', title: 'Bold' },
  italic: { label: 'I', command: 'italic', title: 'Italic' },
  underline: { label: 'U', command: 'underline', title: 'Underline' },
  unordered_list: { label: '•', command: 'insertUnorderedList', title: 'Bullet list' },
  ordered_list: { label: '1.', command: 'insertOrderedList', title: 'Numbered list' },
  h2: { label: 'H2', command: 'formatBlock', argument: 'h2', title: 'Heading' },
  h3: { label: 'H3', command: 'formatBlock', argument: 'h3', title: 'Subheading' },
  quote: { label: '❞', command: 'formatBlock', argument: 'blockquote', title: 'Quote' },
  clear: { label: '⌫', command: 'removeFormat', title: 'Clear formatting' },
}

/** A deliberately small rich text field: a few commands, plain HTML out. */
export default class extends Controller {
  static targets = ['bar', 'body', 'input']
  static values = { toolbar: Array }

  connect() {
    // Rows cloned by the repeater carry their HTML in an attribute.
    const stored = this.bodyTarget.getAttribute('data-tesserae-html')
    if (stored !== null) this.bodyTarget.innerHTML = stored

    for (const name of this.toolbarValue.length > 0 ? this.toolbarValue : ['bold', 'italic', 'link']) {
      if (name === 'link') {
        this.barTarget.append(this.button({ label: '🔗', title: 'Link' }, () => this.link()))
        continue
      }

      const spec = COMMANDS[name]
      if (!spec) continue

      this.barTarget.append(this.button(spec, () => this.exec(spec)))
    }

    this.onInput = () => this.sync()
    this.bodyTarget.addEventListener('input', this.onInput)
    this.bodyTarget.addEventListener('blur', this.onInput)
  }

  disconnect() {
    this.bodyTarget.removeEventListener('input', this.onInput)
    this.bodyTarget.removeEventListener('blur', this.onInput)
  }

  button(spec, handler) {
    return el('button', {
      type: 'button',
      class: 'tsr-richtext__btn',
      title: spec.title,
      text: spec.label,
      onmousedown: (event) => event.preventDefault(),
      onclick: (event) => {
        event.preventDefault()
        handler()
      },
    })
  }

  exec(spec) {
    this.bodyTarget.focus()
    document.execCommand(spec.command, false, spec.argument ? `<${spec.argument}>` : undefined)
    this.sync()
  }

  link() {
    const url = window.prompt('Link URL', 'https://')
    if (!url) return

    this.bodyTarget.focus()
    document.execCommand('createLink', false, url)
    this.sync()
  }

  sync() {
    this.inputTarget.value = this.bodyTarget.innerHTML.trim()
    this.bodyTarget.setAttribute('data-tesserae-html', this.inputTarget.value)
    this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }))
  }
}

import { Controller } from '@hotwired/stimulus'
import { api } from '../api.js'
import { debounce, el } from '../dom.js'

/** URL + label + target, with a search box for internal content. */
export default class extends Controller {
  static targets = ['input', 'url', 'title', 'blank', 'results']

  connect() {
    this.lookup = debounce(() => this.query(), 250)
  }

  sync() {
    const value = {
      url: this.urlTarget.value.trim(),
      title: this.titleTarget.value.trim(),
      target: this.blankTarget.checked ? '_blank' : '',
    }

    this.inputTarget.value = JSON.stringify(value)
    this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }))
  }

  toggleSearch() {
    this.resultsTarget.hidden = !this.resultsTarget.hidden

    if (!this.resultsTarget.hidden) this.query()
  }

  search() {
    if (this.urlTarget.value.startsWith('http') || this.urlTarget.value.startsWith('/')) {
      this.resultsTarget.hidden = true
      return
    }

    this.lookup()
  }

  async query() {
    const term = this.urlTarget.value.trim()

    if (term.length < 2) {
      this.resultsTarget.hidden = true
      return
    }

    try {
      const results = await api.search(term)
      this.resultsTarget.innerHTML = ''
      this.resultsTarget.hidden = results.length === 0

      for (const result of results) {
        this.resultsTarget.append(el('button', {
          type: 'button',
          class: 'tsr-link__result',
          onclick: () => {
            this.urlTarget.value = result.url
            if (this.titleTarget.value === '') this.titleTarget.value = result.title
            this.resultsTarget.hidden = true
            this.sync()
          },
        }, [
          el('span', { class: 'tsr-link__result-title', text: result.title || result.url }),
          el('span', { class: 'tsr-link__result-type', text: result.subtype || '' }),
        ]))
      }
    } catch {
      this.resultsTarget.hidden = true
    }
  }
}

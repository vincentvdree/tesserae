import { config } from './config.js'

/** Thin wrapper around the two REST namespaces the editor talks to. */
export class Api {
  constructor() {
    this.base = config.rest || ''
    this.root = config.restRoot || ''
    this.nonce = config.nonce || ''
  }

  async request(url, { method = 'GET', body, headers = {} } = {}) {
    const options = {
      method,
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': this.nonce, ...headers },
    }

    if (body !== undefined) {
      if (body instanceof File || body instanceof Blob) {
        options.body = body
      } else {
        options.headers['Content-Type'] = 'application/json'
        options.body = JSON.stringify(body)
      }
    }

    const response = await fetch(url, options)
    const payload = await response.json().catch(() => null)

    if (!response.ok) {
      const message = payload?.message || `${response.status} ${response.statusText}`
      throw new Error(message)
    }

    return payload
  }

  catalogue(postId, index, blocks) {
    return this.request(`${this.base}blocks`, {
      method: 'POST',
      body: { post_id: postId, index: index ?? -1, blocks },
    })
  }

  form(postId, block) {
    return this.request(`${this.base}form`, { method: 'POST', body: { post_id: postId, block } })
  }

  renderBlock(postId, block, index, total) {
    return this.request(`${this.base}render`, {
      method: 'POST',
      body: { post_id: postId, block, index, total },
    })
  }

  renderDocument(postId, blocks) {
    return this.request(`${this.base}render`, { method: 'POST', body: { post_id: postId, blocks } })
  }

  save(postId, blocks) {
    return this.request(`${this.base}save`, { method: 'POST', body: { post_id: postId, blocks } })
  }

  saveOptions(page, values) {
    return this.request(`${this.base}options/save`, { method: 'POST', body: { page, values } })
  }

  /** Core's search endpoint powers the link and post pickers. */
  async search(term, subtypes = [], perPage = 10) {
    const query = new URLSearchParams({
      search: term,
      per_page: String(perPage),
      type: 'post',
      _fields: 'id,title,url,subtype,type',
    })

    if (subtypes.length > 0) query.set('subtype', subtypes.join(','))

    return this.request(`${this.root}wp/v2/search?${query}`)
  }

  media({ search = '', page = 1, perPage = 40, mime = '' } = {}) {
    const query = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
      orderby: 'date',
      order: 'desc',
      _fields: 'id,title,alt_text,mime_type,source_url,media_type,media_details,date',
    })

    if (search) query.set('search', search)
    if (mime) query.set('media_type', mime === 'image' ? 'image' : mime)

    return this.request(`${this.root}wp/v2/media?${query}`)
  }

  upload(file) {
    return this.request(`${this.root}wp/v2/media`, {
      method: 'POST',
      body: file,
      headers: {
        'Content-Disposition': `attachment; filename="${encodeURIComponent(file.name)}"`,
        'Content-Type': file.type || 'application/octet-stream',
      },
    })
  }
}

export const api = new Api()

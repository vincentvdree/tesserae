/**
 * The editor's copy of the document, plus undo history.
 *
 * The DOM is a projection of this: every mutation happens here first and the
 * server re-renders whatever changed.
 */
const clone = (value) => JSON.parse(JSON.stringify(value))

export class Store {
  constructor(document = { blocks: [] }) {
    this.blocks = clone(document.blocks || [])
    this.past = []
    this.future = []
    this.saved = this.serialize()
  }

  serialize() {
    return JSON.stringify(this.blocks)
  }

  get dirty() {
    return this.serialize() !== this.saved
  }

  markSaved() {
    this.saved = this.serialize()
  }

  commit() {
    this.past.push(clone(this.blocks))
    if (this.past.length > 50) this.past.shift()
    this.future = []
  }

  find(id) {
    return this.blocks.find((block) => block.id === id) || null
  }

  indexOf(id) {
    return this.blocks.findIndex((block) => block.id === id)
  }

  update(id, { values, settings } = {}) {
    const block = this.find(id)
    if (!block) return null

    if (values !== undefined) block.values = clone(values)
    if (settings !== undefined) block.settings = clone(settings)

    return block
  }

  insert(block, index = -1) {
    this.commit()
    const position = index < 0 || index > this.blocks.length ? this.blocks.length : index
    this.blocks.splice(position, 0, clone(block))

    return position
  }

  remove(id) {
    const index = this.indexOf(id)
    if (index === -1) return false

    this.commit()
    this.blocks.splice(index, 1)

    return true
  }

  move(id, delta) {
    const index = this.indexOf(id)
    if (index === -1) return index

    const target = Math.max(0, Math.min(this.blocks.length - 1, index + delta))
    if (target === index) return index

    this.commit()
    const [block] = this.blocks.splice(index, 1)
    this.blocks.splice(target, 0, block)

    return target
  }

  reorder(ids) {
    const map = new Map(this.blocks.map((block) => [block.id, block]))
    const next = ids.map((id) => map.get(id)).filter(Boolean)
    if (next.length !== this.blocks.length) return false

    this.commit()
    this.blocks = next

    return true
  }

  undo() {
    if (this.past.length === 0) return false

    this.future.push(clone(this.blocks))
    this.blocks = this.past.pop()

    return true
  }

  redo() {
    if (this.future.length === 0) return false

    this.past.push(clone(this.blocks))
    this.blocks = this.future.pop()

    return true
  }

  get canUndo() {
    return this.past.length > 0
  }

  get canRedo() {
    return this.future.length > 0
  }
}

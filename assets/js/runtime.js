import { Application } from '@hotwired/stimulus'
import { config } from './config.js'

/**
 * The front end runtime: one shared Stimulus application. Loaded on any page
 * that has blocks — editing or not. Blocks load their own `<name>.js` file
 * (if they have one) alongside this and register against
 * `window.Tesserae.application` themselves; nothing here wires them up.
 */
const application = Application.start()
application.debug = false

window.Tesserae = window.Tesserae || {}
window.Tesserae.application = application

if (config.editing) {
  const { registerEditor } = await import('./editor.js')
  registerEditor(application)
}

export default application

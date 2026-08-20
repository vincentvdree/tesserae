import { Application } from '@hotwired/stimulus'
import { config } from './config.js'

/**
 * The front end runtime: one Stimulus application, plus every block controller
 * the page needs. Loaded on any page that has blocks — editing or not.
 */
const application = Application.start()
application.debug = false

window.Tesserae = window.Tesserae || {}
window.Tesserae.application = application

const loading = Object.entries(config.controllers).map(async ([identifier, url]) => {
  try {
    const module = await import(url)
    const controller = module.default

    if (controller) application.register(identifier, controller)
  } catch (error) {
    console.error(`[Tesserae] Could not load the "${identifier}" controller.`, error)
  }
})

window.Tesserae.ready = Promise.all(loading)

if (config.editing) {
  const { registerEditor } = await import('./editor.js')
  registerEditor(application)
}

export default application

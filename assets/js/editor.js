import BlockController from './controllers/block_controller.js'
import ColorController from './controllers/color_controller.js'
import EditorController from './controllers/editor_controller.js'
import LinkController from './controllers/link_controller.js'
import MediaController from './controllers/media_controller.js'
import MediaLibraryController from './controllers/media_library_controller.js'
import ModalController from './controllers/modal_controller.js'
import PickerController from './controllers/picker_controller.js'
import PostsController from './controllers/posts_controller.js'
import RepeaterController from './controllers/repeater_controller.js'
import RichTextController from './controllers/richtext_controller.js'
import SwatchesController from './controllers/swatches_controller.js'

/** Loaded on demand by the runtime, only when the page is in edit mode. */
export function registerEditor(application) {
  application.register('tesserae-editor', EditorController)
  application.register('tesserae-block', BlockController)
  application.register('tesserae-modal', ModalController)
  application.register('tesserae-picker', PickerController)
  application.register('tesserae-media-library', MediaLibraryController)
  application.register('tesserae-media', MediaController)
  application.register('tesserae-posts', PostsController)
  application.register('tesserae-link', LinkController)
  application.register('tesserae-richtext', RichTextController)
  application.register('tesserae-repeater', RepeaterController)
  application.register('tesserae-color', ColorController)
  application.register('tesserae-swatches', SwatchesController)
}

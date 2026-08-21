---
title: Blocks
nav_order: 2
---

# Blocks

A block is a folder in a theme's `blocks/` directory, named after its type:

```
blocks/hero/
├── hero.yaml            # label, fields, placement rules — required
├── hero.php             # the template — required
├── hero.js              # a plain JS module, loaded when the block is on the page (optional)
├── hero.css             # styles, loaded only when the block is on the page (optional)
├── hero_edit.php        # rendered while the page is being edited (optional)
└── hero_robot.php       # rendered for crawlers and other machine readers (optional)
```

Drop the folder in, reload the page in edit mode, and the block is in the picker — nothing to register in
PHP. Tesserae discovers blocks by scanning the directories in the `tesserae/block_sources` filter, which
defaults to the active theme's (and parent theme's) `blocks/`.

## The config file

Every key besides `fields` is optional.

```yaml
label: Hero
description: Full width opening statement with an optional background image.
icon: 🏔                 # emoji, or any HTML
category: header
keywords: [banner, intro]
tag: section             # wrapper element, defaults to <section>

supports:
  anchor: true           # per-instance #anchor
  className: true        # per-instance extra CSS classes
  wrapper: true          # set false to render the template without a wrapper element
  preview: true

rules:                   # where this block may be placed
  post_types: [page]
  templates: [default]   # page template slugs
  capability: edit_pages
  max: 1
  position: first        # first | last | any
  requires: [hero]       # another block must already be on the page
  not_with: [banner]     # cannot be combined with these
  hidden: false          # true hides it from the picker entirely

fields:
  - name: title
    type: text
    label: Title
    required: true
    width: 60            # percentage of the panel row
```

`rules/block_available` has the final say on placement beyond what YAML can express — see
[Hooks & REST](hooks#editing) for the `tesserae/block_available` filter. Every field type and its options
are in [Fields](fields).

## The template

Templates are plain PHP. They receive `$block` (a `BlockContext`), `$fields` (the prepared values), `$post`
and `$editing`.

```php
<?php /** @var Tesserae\Blocks\BlockContext $block */ ?>

<div class="hero hero--<?php echo esc_attr($block->field('align', 'center')); ?>">
    <h1 <?php tesserae_editable('title'); ?>><?php tesserae_the_field('title'); ?></h1>

    <?php if ($block->has('image')) { ?>
        <?php tesserae_the_image($block->field('image'), ['class' => 'hero__image']); ?>
    <?php } ?>
</div>
```

Values arrive prepared, not raw: an image field is an array with `url`, `alt`, `srcset` and friends, a posts
field is a list of `WP_Post` objects, a link field is `['url', 'title', 'target', 'rel']`, a repeater is a
list of rows. `tesserae_editable('field')` marks the element that opens the panel focused on that field.

### Template API

| Function | Description |
| --- | --- |
| `tesserae_render(?int $post_id)` | Renders all blocks of a post. |
| `tesserae_get_render(?int $post_id)` | Same, returned as a string. |
| `tesserae_has_blocks(?int $post_id)` | Whether the post has any blocks. |
| `tesserae_blocks(?int $post_id)` | The raw `BlockInstance` list (ids, types, values). |
| `tesserae_block()` | The block being rendered, or `null`. |
| `tesserae_field($path, $default)` | A prepared value; dot paths work (`cta.link.url`). |
| `tesserae_has_field($path)` | Value is present and not empty. |
| `tesserae_the_field($path)` | Echoes a scalar value, escaped. |
| `tesserae_editable($field)` | Marks an element as the entry point for one field. |
| `tesserae_image($value, $attrs)` / `tesserae_the_image()` | `<img>` from an image value. |
| `tesserae_link_attrs($value)` / `tesserae_the_link_attrs()` | `href`/`target`/`rel` from a link value. |
| `tesserae_is_editing()` / `tesserae_edit_url()` | Edit mode helpers. |

## Variants

| File | When it is used |
| --- | --- |
| `name.php` | Normally. |
| `name_edit.php` | While the page is in edit mode — useful for blocks whose output is a live query, so an editor sees something stable to click on instead of live data shifting under them. |
| `name_robot.php` | For crawlers and AI agents (user-agent based; `tesserae/is_robot` decides). Use it for content a script-driven UI would otherwise hide — an accordion's collapsed panels, for example. |

## Scripts & controllers

Drop a `hero.js` next to `hero.php` and it loads as a native ES module whenever the `hero` block is on the
page — nothing to register in YAML, nothing enqueued by hand.

```js
export default function () {
  // …
}
```

It runs on its own; Tesserae does not assume anything about its contents or wire it into `data-controller`.
For a Stimulus controller, register it yourself against the shared application instance:

```js
import { Controller } from '@hotwired/stimulus'

window.Tesserae.application.register('hero', class extends Controller {
  static targets = ['media']

  connect() {
    // …
  }
})
```

Stimulus ships with the plugin and is mapped through the WordPress import map — nothing to install, nothing
to bundle.

## WP-CLI

```
wp tesserae blocks                       # every block found, and which files it resolved
wp tesserae document <post_id>           # the stored JSON document
wp tesserae scaffold my_block [--label=<label>] [--script]
```

`scaffold` writes a config file and template into the active theme's `blocks/`; `--script` also writes a
`.js` file with a commented-out Stimulus registration to fill in.

## Extending

Field types are PHP classes. Extend `Tesserae\Fields\Field`, implement `type()` and `renderControl()`, and
register it via the `tesserae/field_types` filter. Blocks can come from anywhere, not just the active
theme — add a directory via `tesserae/block_sources`. Both filters, and everything else Tesserae exposes,
are listed in [Hooks & REST](hooks).

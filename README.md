# Tesserae

[![License: GPL-3.0-only](https://img.shields.io/badge/license-GPL--3.0--only-blue.svg)](LICENCE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg)](composer.json)
[![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.5-21759b.svg)](#requirements)

A code-first block editor for WordPress. Blocks are folders in your theme, editing happens on the live
page, and none of it goes through Gutenberg or ACF.

```
blocks/hero/
├── hero.yaml            # label, fields, placement rules
├── hero.php             # the template
├── hero.js              # a plain JS module, loaded when the block is on the page (optional)
├── hero.css             # styles, loaded only when the block is on the page (optional)
├── hero_edit.php        # rendered while the page is being edited (optional)
└── hero_robot.php       # rendered for crawlers and other machine readers (optional)
```

Drop that folder in, reload the page, and the block is in the picker.

Full documentation — a five-minute quick start plus references for blocks, fields, hooks and REST — lives in
[`docs/`](docs/index.md).

## Why

Page builders keep the structure of a site in the database, where it cannot be reviewed, diffed or
deployed. Tesserae keeps it in the repository: a developer defines what a block is, which fields it has and
where it may be used; an editor arranges those blocks on the page itself and sees the real thing while
typing.

## Requirements

- PHP 8.2+
- WordPress 6.5+ (the script module API)
- No build step, no npm install — Tesserae itself has no Composer dependencies of its own

## Installation

Via Composer (recommended):

```
composer require vincentvdree/tesserae
```

Or download the [latest release](https://github.com/vincentvdree/tesserae/releases) and drop the `tesserae`
directory into your `wp-content/plugins/` (or, on a [Bedrock](https://roots.io/bedrock/) install,
`web/app/plugins/`). Then activate it like any other plugin. It ships no admin screens and no options table
entries — the only thing it needs from a theme is a `blocks/` directory.

## Getting started

1. Activate the Tesserae plugin.
2. Create `blocks/` in your theme and add a block folder (or run `wp tesserae scaffold my_block`).
3. Call `tesserae_render()` in `page.php`:

```php
<?php get_header(); ?>

<?php
while (have_posts()) {
    the_post();
    tesserae_render();
}
?>

<?php get_footer(); ?>
```

4. Open a page and click **Edit with Tesserae** in the admin bar (or add `?tesserae=edit` to the URL).

The [config file](#the-config-file) and [template](#the-template) sections below walk through a complete
`hero` block, field by field.

## Editing

Edit mode is a query parameter (`?tesserae=edit`) available to anyone with `edit_post` on that page.

- **Click a block** to open its panel. It slides in from the right and the page makes room for it, so the
  block you are editing stays fully visible instead of hiding under an overlay. Closing it gives the space
  back.
- **Type** and the block re-renders from the server, so what you see is the actual template output — not a
  second implementation of it in JavaScript.
- **Hover** a block for its toolbar: move up or down, duplicate, remove, or drag the handle to reorder. The
  `+` buttons at the top and bottom edge insert a block right there.
- **Repeater rows** move the same way — drag the handle, use the ↑ / ↓ buttons, or focus the handle and
  press the arrow keys.
- **⌘S** saves, **⌘Z / ⇧⌘Z** undo and redo, **Esc** closes the panel.
- `tesserae_editable('title')` in a template makes an element open the panel focused on that one field.

Nothing is written until you save. The whole page is one JSON document in the `_tesserae_blocks` post meta,
plus a flattened text copy in `_tesserae_text` so WordPress search still finds block content.

## The config file

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

  - type: tab            # everything after this lands on a "Design" tab
    label: Design

  - name: image
    type: image
    size: full

  - name: overlay
    type: toggle
    label: Darken image
    conditional:         # shown only when the image field is filled
      - field: image
        operator: not_empty
```

Conditional operators: `==`, `!=`, `>`, `<`, `contains`, `in`, `empty`, `not_empty`. Multiple rules are
ANDed; use `conditional: {relation: or, rules: [...]}` for the other case.

See [docs/fields.md](docs/fields.md) for every field type and its options.

## The template

Templates are plain PHP. They receive `$block` (a `BlockContext`), `$fields` (the prepared values),
`$post` and `$editing`.

```php
<?php /** @var Tesserae\Blocks\BlockContext $block */ ?>

<div class="hero hero--<?php echo esc_attr($block->field('align', 'center')); ?>">
    <h1 <?php tesserae_editable('title'); ?>><?php tesserae_the_field('title'); ?></h1>

    <?php if ($block->has('image')) { ?>
        <?php tesserae_the_image($block->field('image'), ['class' => 'hero__image']); ?>
    <?php } ?>

    <?php foreach ($block->field('buttons', []) as $button) { ?>
        <a class="ts-button" <?php tesserae_the_link_attrs($button['link']); ?>>
            <?php echo esc_html($button['link']['title']); ?>
        </a>
    <?php } ?>
</div>
```

Values arrive prepared: an image field is an array with `url`, `alt`, `srcset` and friends, a posts field
is a list of `WP_Post` objects, a link field is `['url', 'title', 'target', 'rel']`, a repeater is a list
of rows.

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

## Block scripts

Drop a `hero.js` next to `hero.php` and it is loaded as a native ES module whenever the `hero` block is on
the page — nothing to register in YAML, nothing enqueued by hand.

```js
export default function () {
  // …
}
```

It runs on its own; Tesserae does not assume anything about its contents or wire it into `data-controller`.
If you want a Stimulus controller, register it yourself against the shared application instance:

```js
import { Controller } from '@hotwired/stimulus'

window.Tesserae.application.register('hero', class extends Controller {
  static targets = ['media']

  connect() {
    // …
  }
})
```

Stimulus itself ships with the plugin and is mapped through the WordPress import map, so there is nothing
to install and nothing to bundle.

## Variants

| File | When it is used |
| --- | --- |
| `name.php` | Normally. |
| `name_edit.php` | While the page is in edit mode — useful for blocks whose output is a live query. |
| `name_robot.php` | For crawlers and AI agents (user-agent based, `tesserae/is_robot` decides). |

## WP-CLI

```
wp tesserae blocks                       # every block found, and which files it resolved
wp tesserae document <post_id>           # the stored JSON document
wp tesserae scaffold my_block --script
```

## Extending

Field types are PHP classes. Extend `Tesserae\Fields\Field`, implement `type()` and `renderControl()`, and
register it:

```php
add_filter('tesserae/field_types', function (array $types): array {
    $types['icon'] = MyIconField::class;

    return $types;
});
```

Blocks can come from anywhere, not just the theme:

```php
add_filter('tesserae/block_sources', function (array $sources): array {
    $sources[__DIR__.'/blocks'] = plugins_url('blocks', __FILE__);

    return $sources;
});
```

Every hook is listed in [docs/hooks.md](docs/hooks.md).

## What it deliberately does not do

- No admin screens, no options table entries, no settings UI.
- No Gutenberg. For the post types it manages, the block editor is switched off and the content field is
  removed — the page is edited on the page.
- No ACF, no field group UI, no export/import step: the config file *is* the field group.
- No nested blocks. Groups and repeaters cover the layouts that matter; block-in-block does not.

## Uninstalling

Deactivating (or deleting) the plugin leaves your content alone: the block documents stay in post meta, so
reactivating it picks up where you left off. Removing them is a deliberate act (`wp post meta delete <id>
_tesserae_blocks`), never a side effect.

## Contributing

Issues and pull requests are welcome at
[github.com/vincentvdree/tesserae](https://github.com/vincentvdree/tesserae). If you're proposing a
non-trivial change, open an issue first so we can talk through the approach before you put the work in.

## Licence

GPL-3.0-only, see [LICENCE](LICENCE).

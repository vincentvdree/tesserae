# Hooks

## Discovery and configuration

| Hook | Type | Description |
| --- | --- | --- |
| `tesserae/block_sources` | filter | `array<path, url>` of directories scanned for blocks. Defaults to the theme's (and parent theme's) `blocks/`. |
| `tesserae/blocks` | filter | The resolved `array<string, BlockDefinition>` map, after scanning. |
| `tesserae/field_types` | filter | `array<string, class-string<Field>>` of available field types. |
| `tesserae/post_types` | filter | Post types Tesserae manages. Default `['page', 'post']`. |
| `tesserae/disable_block_editor` | filter | Whether to switch Gutenberg off for a managed post type. Default `true`. |
| `tesserae/remove_editor_support` | filter | Whether to remove `editor` support from managed post types. Default `true`. |
| `tesserae/auto_append_content` | filter | Append blocks to `the_content()` instead of calling `tesserae_render()`. Default `false`. |
| `tesserae/admin_link` | filter | The single meta box Tesserae adds to wp-admin. Return `false` to remove it. |
| `tesserae/main_tab_label` | filter | Label of the implicit first tab. Default "Content". |

## Rendering

| Hook | Type | Description |
| --- | --- | --- |
| `tesserae/render_block` | filter | `(string $html, BlockContext $context)` — a single block's inner HTML. |
| `tesserae/block_attributes` | filter | `(array $attributes, BlockContext $context)` — the wrapper's attributes. |
| `tesserae/richtext_allowed_html` | filter | `(array $allowed, array $fieldConfig)` — `wp_kses` rules for rich text values. |

## Editing

| Hook | Type | Description |
| --- | --- | --- |
| `tesserae/is_editing` | filter | Force edit mode on or off. |
| `tesserae/is_robot` | filter | `(bool $robot, string $userAgent)` — whether to use `_robot` templates. |
| `tesserae/robot_agents` | filter | The user-agent needles that mark a request as a machine reader. |
| `tesserae/block_available` | filter | `(array{allowed, reason} $result, BlockDefinition $block, int $postId, Document $document, int $index)` — the final say on placement. |

## Storage

| Hook | Type | Description |
| --- | --- | --- |
| `tesserae/load_document` | filter | `(Document $document, int $postId)` — the document read from meta. |
| `tesserae/before_save` | action | `(int $postId, Document $document)`. |
| `tesserae/after_save` | action | `(int $postId, Document $document)`. |
| `tesserae/search_blocks` | filter | `(bool $enabled, WP_Query $query)` — include block text in site search. Default `true`. |

## REST

All routes live under `tesserae/v1` and require `edit_post` on the post in question.

| Route | Method | Purpose |
| --- | --- | --- |
| `/blocks` | GET, POST | The block catalogue for a post, with availability and reasons. |
| `/form` | POST | The panel HTML for one block instance. |
| `/render` | POST | Re-render one block (`block`) or the whole document (`blocks`). |
| `/save` | POST | Persist a document. Every value is sanitised through its field definition. |

## Storage format

`_tesserae_blocks` (post meta, JSON):

```json
{
  "version": 1,
  "blocks": [
    {
      "id": "b3f1c9a02e4d1",
      "type": "hero",
      "values": { "title": "…", "buttons": [{ "link": { "url": "/about/" } }] },
      "settings": { "anchor": "top", "class": "", "hidden": false }
    }
  ]
}
```

`_tesserae_text` holds the same content flattened to plain text, which is what site search matches against.

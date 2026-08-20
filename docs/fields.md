# Field reference

Every field accepts these keys:

| Key | Description |
| --- | --- |
| `name` | Required. The key the value is stored under and the name templates use. |
| `type` | Field type, see below. Defaults to `text`. |
| `label` | Shown in the panel. Falls back to a prettified `name`. |
| `instructions` | Help text under the control (`description` also works). |
| `default` | Value used for a new block, and whenever the stored value is missing. |
| `required` | Marks the control as required in the panel. |
| `width` | Percentage of the panel row, 10–100. Two `width: 50` fields sit side by side. |
| `class` | Extra class on the field wrapper. |
| `placeholder` | Placeholder for text-like controls. |
| `conditional` | Show/hide rules, see the README. |

Field types have aliases so the YAML reads the way you think: `wysiwyg` → `richtext`, `true_false` →
`toggle`, `post_object`/`relationship` → `posts`, `taxonomy` → `terms`, `images` → `gallery`,
`repeater`/`flexible`/`list` → `repeater`, and so on.

---

## text

```yaml
- name: title
  type: text          # or url, email, tel, date, time, password — they set the input type
  maxlength: 80
```

Stored and prepared as a string.

## textarea

```yaml
- name: intro
  type: textarea
  rows: 4
  auto_paragraphs: true   # default: wraps the value in <p> when prepared
```

Stored as plain text. Set `auto_paragraphs: false` when the template does its own escaping and wrapping.

## richtext

A small contenteditable editor — bold, italic, links, lists, headings, quotes. Not TinyMCE, not Gutenberg.

```yaml
- name: content
  type: richtext
  toolbar: [bold, italic, link, unordered_list, ordered_list, h2, h3, quote, clear]
```

Stored as HTML, filtered through `wp_kses` on save. Filter the allowed tags with
`tesserae/richtext_allowed_html`.

## number

```yaml
- name: columns
  type: number
  min: 1
  max: 6
  step: 1
```

Stored as `int` or `float`, or `null` when empty. Values outside `min`/`max` are clamped on save.

## toggle

```yaml
- name: overlay
  type: toggle
  text: Adds a dark overlay      # label next to the switch
  default: true
```

Stored as a boolean.

## select / radio / checkbox

```yaml
- name: size
  type: select
  choices:
    small: Small
    large: Large
  allow_null: true     # select only
  null_label: "—"
  multiple: false      # select only; `checkbox` is always multiple
  inline: true         # radio and checkbox: lay the options out in a row
```

`choices` also accepts a plain list (`[small, large]`). Values not in `choices` are rejected on save.
`select` stores a string (or a list when `multiple: true`), `radio` a string, `checkbox` a list.

## color

```yaml
- name: tone
  type: color
  choices:             # optional palette; without it you get a colour picker
    ink: Ink
    "#f97316": Orange
```

With a palette the field stores the palette key (handy for `cta--ink` style class names); without one it
stores a validated hex colour.

## link

```yaml
- name: cta
  type: link
```

Stored as `{url, title, target}`, prepared as `{url, title, target, rel}` — or `null` when no URL is set.
The control searches published content so editors do not have to paste URLs. Use
`tesserae_the_link_attrs($value)` in templates.

## image

```yaml
- name: image
  type: image
  size: large          # the size `url`, `width`, `height` and `srcset` describe
```

Stored as an attachment id. Prepared as an array: `id`, `url`, `width`, `height`, `full`, `alt`, `title`,
`caption`, `description`, `mime`, `srcset`, `sizes`. `tesserae_the_image($value, $attrs)` renders it.

## gallery

```yaml
- name: images
  type: gallery
  size: large
  max: 12
```

Stored as a list of attachment ids, prepared as a list of image arrays. Selected images can be dragged
into order.

## file

```yaml
- name: brochure
  type: file
  accept: application/pdf
```

Stored as an attachment id. Prepared as `{id, url, title, filename, filesize, mime}`.

## posts

"Select x amount of WP_Posts", with search over the core REST endpoints.

```yaml
- name: picked
  type: posts
  post_type: [post, page]
  multiple: true
  max: 6
```

Stored as a list of post ids (or a single id when `multiple: false`), prepared as `WP_Post` objects, in
the order the editor arranged them. Ids whose post type is not allowed are dropped on save.

## terms

```yaml
- name: topics
  type: terms
  taxonomy: category
  multiple: true
  hide_empty: false
```

Stored as term ids, prepared as `WP_Term` objects.

## group

A named set of fields, stored as a nested object.

```yaml
- name: cta
  type: group
  seamless: false      # true drops the box around it
  fields:
    - name: title
      type: text
    - name: link
      type: link
```

Templates read it with dot paths: `tesserae_field('cta.link.url')`.

## repeater

A sortable list of rows. Rows can be added, duplicated, dragged and removed.

```yaml
- name: items
  type: repeater
  row_label: Feature       # used for the row headings
  button_label: Add feature
  min: 1
  max: 8
  default:
    - title: First one
  fields:
    - name: title
      type: text
    - name: text
      type: textarea
```

Stored and prepared as a list of row objects. Repeaters may contain groups and other repeaters.

## tab

Splits the panel. Everything after a `tab` field belongs to it; the fields before the first one become an
implicit first tab.

```yaml
- type: tab
  label: Design
```

## message

A note for whoever is editing. Stores nothing.

```yaml
- type: message
  tone: warning        # info (default), warning, danger
  message: "Keep this to one sentence."
```

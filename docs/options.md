---
title: Options pages
nav_order: 4
---

# Options pages

An options page is one flat set of fields for values that do not belong to any single page — a phone
number, social links, footer text. It uses the same field types as a block, but there is no folder, no
template and no placement on the page: the values live in one `wp_options` row, and they are edited from a
dialog rather than a panel on a page.

There is deliberately still no wp-admin screen for this — see [What it deliberately does not
do](../README.md#what-it-deliberately-does-not-do). Options pages are edited on the front end, the same way
blocks are.

## The config file

Drop a YAML file into `option-pages/` in your theme — one file per page, named after its slug:

```
option-pages/
└── site.yaml
```

```yaml
label: Site Settings
description: Sitewide contact details and social links.
capability: manage_options    # who may view and save this page; defaults to manage_options

fields:
  - type: tab
    label: Contact

  - name: phone
    type: text
    label: Phone number

  - name: email
    type: text
    label: Email
    input_type: email

  - type: tab
    label: Social

  - name: socials
    type: repeater
    row_label: Link
    fields:
      - name: platform
        type: select
        choices: [instagram, x, linkedin, youtube]
      - name: link
        type: link
```

`fields` accepts every field type in the [field reference](fields), including tabs, groups and repeaters.
There is no `rules`, `supports` or `icon` — those describe where and how a *block* is placed, and an options
page has neither.

## Editing

Tesserae's edit mode already knows to look for options pages: while a page is open with `?tesserae=edit` (or
via **Edit with Tesserae** in the admin bar), a second admin bar item appears — **Site Options** if more than
one page is registered, expanding to one item per page, or the page's own label directly if there is only
one. Clicking it opens a dialog with that page's fields, tabbed the same way a block's panel is. **Save**
persists it immediately through a small REST route; there is nothing to save alongside the rest of the page.

The dialog only shows up once editing is already underway, and only lists pages the current user's
capability allows — the same admin bar item is simply absent for anyone who cannot edit at least one options
page.

## The template

```php
<?php if (tesserae_has_option('site', 'phone')) { ?>
    <a href="tel:<?php tesserae_the_option('site', 'phone'); ?>">
        <?php tesserae_the_option('site', 'phone'); ?>
    </a>
<?php } ?>

<?php foreach (tesserae_option('site', 'socials', []) as $social) { ?>
    <a <?php tesserae_the_link_attrs($social['link']); ?>><?php echo esc_html($social['platform']); ?></a>
<?php } ?>
```

| Function | Description |
| --- | --- |
| `tesserae_option($page, $path = '', $default = null)` | A prepared value; dot paths work. Omit `$path` for the whole page as an array. |
| `tesserae_has_option($page, $path)` | Value is present and not empty. |
| `tesserae_the_option($page, $path, $default = '')` | Echoes a scalar value, escaped. |

Values arrive prepared exactly like a block's `$fields` — an image field is an array with `url`, `alt` and
friends, a posts field is a list of `WP_Post` objects, and so on.

## WP-CLI

```
wp tesserae option_pages                 # every options page found
wp tesserae options site                 # the stored values of the "site" page, as JSON
wp tesserae scaffold_options site        # writes option-pages/site.yaml in the active theme
```

## Storage

Each page is one autoloaded row, `tesserae_options_<slug>`:

```json
{ "version": 1, "values": { "phone": "+1 555 0100", "socials": [] } }
```

Autoloaded rather than post meta, because — unlike a page's blocks — this is global data a template may read
on every request.

---
title: Home
layout: default
nav_order: 1
permalink: /
---

# Tesserae

A code-first block editor for WordPress. Blocks are folders in your theme, editing happens on the live page,
and none of it goes through Gutenberg or ACF. See the [project README]({{ site.aux_links["Plugin source"][0]
}}) for the full pitch and the reasoning behind it — this site is the reference manual.
{: .fs-6 .fw-300 }

## Get started in five minutes

**1. Install.**

```
composer require vincentvdree/tesserae
```

Or drop the `tesserae` directory into `wp-content/plugins/` (on a [Bedrock](https://roots.io/bedrock/)
install, `web/app/plugins/`) and activate it. It needs PHP 8.2+, WordPress 6.5+, and nothing else — no build
step, no npm install.

**2. Add a block.** Create `blocks/` in your active theme, then either scaffold one:

```
wp tesserae scaffold hero --script
```

or write the two files by hand — `blocks/hero/hero.yaml`:

```yaml
label: Hero
fields:
  - name: title
    type: text
    label: Title
    required: true
```

and `blocks/hero/hero.php`:

```php
<?php /** @var Tesserae\Blocks\BlockContext $block */ ?>
<h1 <?php tesserae_editable('title'); ?>><?php tesserae_the_field('title'); ?></h1>
```

**3. Render it.** In `page.php`:

```php
<?php
while (have_posts()) {
    the_post();
    tesserae_render();
}
?>
```

**4. Edit it.** Open the page and click **Edit with Tesserae** in the admin bar (or append `?tesserae=edit`
to the URL). Click the block, type in the panel that slides in, press **⌘S** to save.

That's the whole loop. For a working example of every field type and pattern below, read the blocks in
[`web/app/themes/tesserae-starter/blocks/`](https://github.com/vincentvdree/tesserae) inside this repository
— it is a small theme built specifically to demonstrate this plugin.

## Where to go next

| Page | Read it when you need to |
| --- | --- |
| [Blocks](blocks) | Understand a block folder's files, the config keys (`rules`, `supports`), the `_edit`/`_robot` variants, and how block scripts and Stimulus controllers load. |
| [Fields](fields) | Look up a field type — its YAML options, what it's stored as, and what a template receives. |
| [Options pages](options) | Add a global settings page — a phone number, socials, footer text — edited from the front end without a wp-admin screen. |
| [Hooks & REST](hooks) | Find a specific filter or action, the REST routes the editor UI calls, or the post-meta storage format. |
| [Development](development) | Orient in the plugin's own source layout, and run the checks that gate a change (linting, static analysis, tests). |

## In this repository

This docs site lives at `web/app/plugins/tesserae/docs/` inside a [Bedrock](https://roots.io/bedrock/)
project template. Two paths in that project are where hands-on work happens:

- `web/app/plugins/tesserae/` — this plugin, the primary focus of most changes.
- `web/app/themes/tesserae-starter/` — the reference theme used above; keep it current whenever a plugin
  change affects how blocks are authored or rendered.

See the repository root `CLAUDE.md` and `README.md` for the surrounding Docker/Bedrock setup — installing
WordPress, running `make check`, environment variables, and so on. None of that is specific to Tesserae.

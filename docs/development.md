---
title: Development
nav_order: 5
---

# Development

This page is for changing Tesserae itself, not for building blocks with it.

## Source layout

```
src/
├── Plugin.php        # composition root — wires everything below together on plugin load
├── Blocks/            # discovery, placement rules (Availability), rendering a block instance
├── Editor/            # edit-mode assets, the edit session, rendering the side panel form
├── Fields/            # the field type system — Field, FieldRegistry, FieldCollection, Types/*
├── Rest/              # the tesserae/v1 REST routes the editor UI calls
├── Storage/           # the post-meta document model (BlockInstance, Document, DocumentStore) + search
├── Cli/               # `wp tesserae …` commands
└── Support/           # small internal utilities — including Tesserae's own tiny YAML parser (no Composer
                         # dependencies of its own; see composer.json)
```

Start in `Plugin.php` to see how a request flows: block discovery (`Blocks/BlockRegistry`) → placement
checks (`Blocks/Availability`) → either the REST-driven editor (`Editor/`, `Rest/`) or a plain render
(`Blocks/Renderer`) → values read from and written to `Storage/DocumentStore`.

## Where this fits in the repository

This plugin (`web/app/plugins/tesserae/`) and the reference theme (`web/app/themes/tesserae-starter/`) are
the two paths meant for hands-on changes in this repository — see the root `CLAUDE.md`. Most work happens
here, in the plugin; keep the starter theme's blocks and README in sync whenever a change here affects how
blocks are authored or rendered, since that theme is what demonstrates real usage.

## Running the checks

Both this plugin and the starter theme are already covered by the repository's root-level tooling — there
is nothing separate to install or configure inside `web/app/plugins/tesserae/` itself. From the repository
root (inside the `php` container):

```
make check          # php-cs-fixer + phpstan (level 10) + phpunit — run before considering a change done
make php-cs-fixer    # fix code style
make phpstan         # static analysis
```

`.php-cs-fixer.dist.php` and `phpstan.dist.neon` both explicitly include `web/app/plugins/tesserae` and
`web/app/themes/tesserae-starter` alongside the root `config/`/`tests/`, and exclude this plugin's own
`vendor/` (its dev-only PHPUnit install, kept separate via its own `composer.json`).

## Contributing to the plugin itself

Tesserae is developed at [github.com/vincentvdree/tesserae](https://github.com/vincentvdree/tesserae) and
pulled into this project via Composer. Issues and pull requests for the plugin's own behavior belong there —
open an issue first for anything non-trivial so the approach can be discussed before the work goes in.

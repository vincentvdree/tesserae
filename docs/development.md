---
title: Development
nav_order: 6
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
├── Options/            # options pages — OptionsPage, OptionsRegistry, OptionsStore, its own FormRenderer
├── Rest/              # the tesserae/v1 REST routes the editor UI calls
├── Storage/           # the post-meta document model (BlockInstance, Document, DocumentStore) + search
├── Cli/               # `wp tesserae …` commands
├── Development/       # registers this plugin's own examples/ as a block source — see below
└── Support/           # small internal utilities — including Tesserae's own tiny YAML parser (no Composer
                         # dependencies of its own; see composer.json)

examples/
├── blocks/            # sample blocks exercising every field type — see Blocks and Fields
└── option-pages/      # a sample "site" options page — see Options pages
```

`examples/` ships with the plugin but is only wired in as a block/option source when `TESSERAE_ENABLE_SAMPLE_BLOCKS`
is defined — `Development/DevelopmentLoader` checks for it in `Plugin::boot()`. The companion Bedrock
project's `development` environment config defines it, which is how the reference theme (see below) gets its
demo content without carrying any blocks of its own.

Start in `Plugin.php` to see how a request flows: block discovery (`Blocks/BlockRegistry`) → placement
checks (`Blocks/Availability`) → either the REST-driven editor (`Editor/`, `Rest/`) or a plain render
(`Blocks/Renderer`) → values read from and written to `Storage/DocumentStore`. Options pages run the same
field system through a much shorter path: `Options/OptionsRegistry` discovers them, `Options/OptionsStore`
reads and writes one `wp_options` row per page, and the "Site Options" dialog that `Editor/Assets` prints is
just `Options/FormRenderer` output re-using the block panel's own CSS and field controllers.

## Where this fits in the repository

This plugin (`web/app/plugins/tesserae/`) and the reference theme (`web/app/themes/tesserae-starter/`) are
the two paths meant for hands-on changes in this repository — see the root `CLAUDE.md`. Most work happens
here, in the plugin. The starter theme carries no `blocks/` or `option-pages/` of its own — the sample
content that demonstrates real usage lives in this plugin's `examples/blocks/` and `examples/option-pages/`
instead (see [Source layout](#source-layout) above), so update those, plus the theme's templates and README,
whenever a change here affects how blocks are authored or rendered.

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

## The plugin's own PHPUnit suite

Most of the plugin is exercised live — install it, build a block, load the page. The `Fixtures/` service
(`wp tesserae fixtures`, see [Fixtures](fixtures.md)) is the exception: it's tested with its own PHPUnit
suite, run from inside `web/app/plugins/tesserae/` itself rather than through `make phpunit` at the repo
root:

```
cd web/app/plugins/tesserae
composer install   # first time only — installs this plugin's own dev-only phpunit
composer test       # or: vendor/bin/phpunit
```

This suite runs against hand-rolled WordPress stubs (`tests/bootstrap.php`, `tests/stubs/`) backed by an
in-memory fake (`tests/Support/FakeWordPress.php`) — not a real WordPress install, and not this project's
own `php-stubs/wordpress-stubs` (the two collide under static analysis, which is why `phpstan.dist.neon`
excludes this plugin's `tests/` from the root PHPStan run entirely). The stubs cover exactly the WordPress
API surface `Fixtures/` and the real Field/Block/Storage classes it drives touch — extending them for a new
test is usually a matter of adding one function to `tests/stubs/functions.php`, backed by
`FakeWordPress`'s static state.

Test blocks (`tests/Support/TestBlocks.php`) are deliberately not this plugin's own `examples/blocks/` — they
use a small field-type subset (`text`, `number`, `toggle`, `select`, `terms`, `posts`, `group`, `repeater`)
chosen to exercise nesting, cross-post references and taxonomy references without needing GD, real file I/O
or `wp_kses` in the stub layer. `image`/`gallery`/`file`/`richtext`/`link` field behavior is proven by
actually running `wp tesserae fixtures generate` against those example blocks instead (see the root
`CLAUDE.md` for how to reach a running stack, and set `TESSERAE_ENABLE_SAMPLE_BLOCKS` to load them).

## Contributing to the plugin itself

Tesserae is developed at [github.com/vincentvdree/tesserae](https://github.com/vincentvdree/tesserae) and
pulled into this project via Composer. Issues and pull requests for the plugin's own behavior belong there —
open an issue first for anything non-trivial so the approach can be discussed before the work goes in.

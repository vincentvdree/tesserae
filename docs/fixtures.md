---
title: Fixtures
nav_order: 7
---

# Fixtures

`wp tesserae fixtures` generates deterministic content that exercises every block registered in the active
theme — one thing this is *for* is giving an end-to-end test suite a known, reproducible site to run against,
but it's just as useful for trying out a new block against realistic-looking content without hand-writing a
page for it.

It only registers when WP-CLI is loaded (`defined('WP_CLI') && WP_CLI`), and the logic itself lives in a
plain service class (`Tesserae\Fixtures\FixturesService`) with no WP-CLI dependency — the CLI command is a
thin adapter over it.

## Commands

Both a subcommand and a flag form work — `wp tesserae fixtures generate` and `wp tesserae fixtures --generate`
do the same thing. The examples below use the subcommand form.

```
wp tesserae fixtures generate [--seed=<int>] [--skip-backup] [--force] [--dry-run] [--yes]
wp tesserae fixtures restore  [--from=<id|latest>] [--force] [--dry-run] [--yes]
wp tesserae fixtures backup   [--label=<string>]
wp tesserae fixtures list
```

### `generate`

1. Backs up first, unless `--skip-backup` is passed. If the backup fails or doesn't verify, nothing is
   purged — `generate` throws before it touches a row.
2. Purges existing Tesserae-owned content — see [What counts as "Tesserae-owned"](#what-counts-as-tesserae-owned)
   below.
3. Builds a believable little site deterministically from `--seed` (default `1`) instead of one page per
   block type: a **Home** page, a handful of ordinary text pages (**About**, **Services**, **FAQ**, ...), a
   **Gallery** page (only if the theme has a block whose `category:` is `media`), and a **Contact** page —
   each carrying a subset of the registered blocks with deliberately non-default field values. Which page a
   block leans toward is read off its own `category:` (`header`/`footer`/`media` bias it toward Home/Contact/
   Gallery; anything else, including the default `content`, is general-purpose), so this keeps working for
   whatever blocks a theme defines rather than assuming the starter theme's block names. A block's
   `rules: requires:` chain is always spliced in ahead of it on these pages, so a dependency is never left
   off the page that needs it. A **Style guide** page places every block together in one seed-shuffled order
   on top of that, so `rules:` interplay (`max`, `position`, `not_with`) gets exercised for real, through the
   same `Availability::check()` the editor's picker uses — not a re-implementation of it. If a theme's own
   rules keep a block off every one of those pages (two blocks that `not_with` each other, say), it still gets
   one dedicated fallback page to itself — `generate()`'s guarantee that every registered block is placed at
   least once always holds. Finally, a sample page on a second Tesserae-enabled post type, if the theme
   enables one (titled "Hello, world!" for `post`). The very first generated page (Home) is left as a draft
   (everything else is published), so a suite testing save-draft-then-reload has a fixture for it without a
   dedicated page.
4. Images, galleries and files reference a small pool of attachments generated with GD (deterministic pixels,
   no network fetch). `posts`-type fields (a block that hand-picks other pages/posts) get resolved once every
   fixture post exists, so a "hand-picked" configuration ends up pointing at other real fixture posts, not at
   nothing.
5. Prints a summary table (post type / block type → count) and the backup id it can be rolled back to.

Same seed, same output: nothing here uses `rand()`/`mt_rand()`/`time()` — see
`Tesserae\Fixtures\Support\SeededRandom`. The one thing that legitimately differs between two runs of the
same seed is real ids WordPress assigns (`wp_insert_post`'s auto-increment, attachment ids) — those depend on
the database's history, not the seed, the same way they would for any two `wp post create` calls.

### `restore`

Restores from `--from=<id>`, or the most recent backup with `--from=latest` (the default). The backup is
parsed and its checksum verified *before* anything is purged — a corrupt, truncated, or unknown-schema-version
backup aborts cleanly with nothing mutated.

Posts try to come back under their original id (only falling back to a fresh one if that id is taken by the
time of restore) — see [Restoring posts and ids](#restoring-posts-and-ids). The command reports what was
restored under its original id, what got remapped to a new one, and what was skipped and why.

### `backup`

Captures the current state without generating or restoring anything — `wp tesserae fixtures backup
--label="before the theme rewrite"`. Unlike `generate`/`restore`, `backup` doesn't require `--force` on
production/staging: it's non-destructive, and "take a backup before I touch something on staging" is exactly
the kind of thing this command should allow on the environments the other two refuse to touch.

### `list`

Lists every backup found under the backup directory (see [Backup format](#backup-format)), newest first.

## Safety rails

- `generate` and `restore` abort on a `production`/`staging` `wp_get_environment_type()` unless `--force` is
  passed, and even then prompt for confirmation unless `--yes` is also passed (via `WP_CLI::confirm()`, the
  same mechanism every other confirmable WP-CLI command uses).
- `--dry-run` on `generate` and `restore` prints what would happen and changes nothing — no purge, no writes,
  no backup.
- The command is registered only when WP-CLI is loaded.

## What counts as "Tesserae-owned"

Tesserae doesn't register its own post type — it attaches to whatever post types a theme enables via the
`tesserae/post_types` filter (`page`/`post` by default) and stores everything in one `_tesserae_blocks`
postmeta value per post. That means "every page and post" is *not* what `generate` purges: a site can have
ordinary pages Tesserae has never touched, and purging those would be exactly the kind of accidental data
loss this command exists to avoid. "Tesserae-owned" here means *has a non-empty `_tesserae_blocks` document* —
nothing more. Fixture-generated attachments are found the same way, via a `_tesserae_fixture` postmeta marker
set on every attachment `generate` creates — a real site's own media library is never touched.

## Backup format

Each backup is a directory named `<timestamp>-<label-slug>` under the backup directory (default
`web/app/tesserae-fixtures-backups/`, filterable with `tesserae/fixtures_backup_dir` — deliberately outside
`web/app/uploads/`, so nothing that syncs "just the media library" mistakes a content backup for media; see
`.gitignore`, which excludes the whole directory):

```
<backup-id>/
├── manifest.json   # schema version, plugin version, WP version, site URL, timestamp, label, counts, checksum
├── content.json    # posts (with meta and term slugs), attachments, options page values
└── media/
    └── <attachment id>/<original filename>
```

`content.json`'s checksum lives in `manifest.json`; `restore` (and `list`, more leniently — a corrupt payload
still shows up in the list) checks it before doing anything else. A backup whose `schema_version` is newer
than the running code understands is refused outright rather than partially read.

### Restoring posts and ids

`restore` asks `wp_insert_post` for each post's original id back (`import_id`). If that id is free, the post
comes back with the exact id it had when backed up; if something else has taken it since, the post gets a
fresh id instead, and everything that referenced the old id gets rewritten: `post_parent`, `_thumbnail_id`,
and any `image`/`file`/`gallery`/`posts`-type field value stored inside a block. `link`-type field values are
plain URLs, not ids, and are restored verbatim — a link pointing at a fixture post's permalink only stays
correct if that post's slug (not necessarily its id) is unchanged, which id remapping doesn't affect either
way.

### What isn't backed up

Non-Tesserae posts, users, and unrelated options are never touched by any of these commands — `generate` and
`restore` only ever purge/restore the content described above. Terms themselves aren't purged (only term
*relationships* on Tesserae-owned posts are captured, by slug); if a term no longer exists on the target site
at restore time, that one relationship is skipped rather than failing the whole restore.

## Example

```
$ wp tesserae fixtures generate --seed=1234
type              count
page              10
post              1
block: hero       1
block: cta        4
...
attachments       8
Success: Generated fixture content. Backup: 20260821-120000-pre-generate (restore with `wp tesserae fixtures restore --from=20260821-120000-pre-generate`).

$ wp tesserae fixtures restore --from=latest
Restored 11 post(s) under their original id.
Success: Restored from backup "20260821-120000-pre-generate".
```

# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-09

### Added

- `IconRegistry`, a catalogue of the icons the configured providers can draw, with scored search
  over name, label, alias, use case and purpose, and typo tolerance as a fallback. Bound scoped, so it holds
  the icon set for one request or job.
- Providers for Flux (its own icons, and loose files and vendor directories in
  `resources/views/flux/icon`) and for every Blade Icons set. Both are optional; a provider whose
  package is not installed is skipped. Further providers implement `Contracts\IconProvider`.
- Each icon has a key (`<vendor>.<name>`, the value to store) and a reference (what its provider's
  component expects), so Flux's own icons are listed as `flux.home` and drawn as `home`.
- `<x-registry-icon icon="...">` and `IconRegistry::render()` draw a stored key with the provider
  that listed it, and throw `UnknownIcon` for a key none lists. The component draws nothing for a
  null or empty key. The tag is configurable with `component`.
- Drawn icons are decorative (`aria-hidden="true"`) unless given an accessible name. `label` renders
  as `role="img"` with an `aria-label`; an explicit `aria-label`, `aria-labelledby`, `title`, `role`
  or `aria-hidden` is respected. Flux's hardcoded `aria-hidden` is replaced by the resolved value,
  and an attribute array passed to `render()` is escaped as Blade escapes component attributes.
- The `IconExists` validation rule, with `vendor()` and `provider()` constraints and a suggestion for
  the closest icon. When the cache predates an icon, it checks the providers before failing.
  It rejects non-strings and keys with surrounding whitespace, which could not be drawn.
  Messages in English and Dutch.
- A metadata file for labels, aliases and use cases. Rewriting it keeps the order of the
  entries, the header, section banners and the comment above each entry.
- Purposes: `resources/icons/purposes.php` maps a meaning such as `action.delete` to one icon and an
  accessible label. Draw one with `<x-registry-icon purpose="...">` or `renderPurpose()`, decorative
  unless `labelled`. `purposes()` lists them, `purpose()` returns the icon, an undefined purpose throws
  `UnknownPurpose`, and `icon-registry:doctor` fails on a malformed purpose or one whose icon no longer exists.
- `icon-registry:cache`, `icon-registry:clear`, `icon-registry:doctor`, `icon-registry:search` and
  `icon-registry:export`.
  Named apart from Blade Icons' own `icons:cache` and `icons:clear`. `php artisan optimize` builds
  the cache and `optimize:clear` removes it.
- `flux.<name>` stays listed when an application file overrides that icon, so stored keys keep
  drawing. The icon is then listed as both `flux.<name>` and `app.<name>`.
- The cache and metadata file are replaced atomically, so a request cannot read a partial cache.
  The cache is versioned plain data read lazily, and a cache of another format is rebuilt.
- A VS Code extension in `editors/vscode` that completes icon keys in Blade and PHP from the
  manifest `icon-registry:export` writes.
- `icon-registry:scan` lists the icons an application's Blade and PHP source refers to, with
  keywords from their labels, slot text, actions and file paths. Matching is by pattern, and
  references chosen at runtime are listed but not resolved. `--write` appends the keywords to the
  metadata file as use cases after a preview.

### Notes for applications moving from an in-app registry

This package grew out of the registry in an application of ours. Compared with that one:

- The commands were `icons:*`; they are `icon-registry:*`.
- The config key was `icons`, with a list of source paths; it is `icon-registry`, with providers.
- Rewriting a metadata file whose `return [` is not on a line of its own (such as `var_export()`
  output) used to keep the old `return` statement in the header, so the file went on returning the
  old entries. It now keeps only what precedes `return`.
- A cache or metadata file rewritten while opcache still held the previous version could be read
  back stale within the same process. Both are invalidated after a write.

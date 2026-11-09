# Artisan commands

The commands are named `icon-registry:*`. Blade Icons already registers `icons:cache` and
`icons:clear`, for its own manifest.

## `icon-registry:cache`

Writes the cache to the `cache` path. `php artisan optimize` runs it.

## `icon-registry:clear`

Deletes the cache. `php artisan optimize:clear` runs it.

## `icon-registry:doctor`

Reports the available providers, the number of icons, metadata entries and purposes, whether the
cache is fresh (every icon in it, including labels and aliases, matches a new scan), metadata and
purposes for icons that no longer exist, cache entries for icons that no longer exist, and keys that
more than one provider lists. It exits with a failure on stale metadata, a purpose whose icon is
gone, a malformed purpose or stale cache entries, so it can run in CI. A purpose is never pruned; point it at another
icon yourself.

| Option | Effect |
| --- | --- |
| `--fix` | All three repairs below. |
| `--prune-meta` | Removes metadata entries for icons that no longer exist and rewrites the file, keeping its order and comments. |
| `--clear-cache` | Deletes the cache. |
| `--rebuild-cache` | Rebuilds the cache. |

## `icon-registry:export`

Writes the JSON manifest that editor extensions complete from, always from a fresh scan. See
[Editor support](../usage/editor-support.md).

| Option | Effect |
| --- | --- |
| `--path=` | Write here instead of the `editor_manifest` path. |

## `icon-registry:scan`

Finds the icons the application's source refers to and suggests keywords for what each is used
for. It writes nothing unless you pass `--write`.

```bash
php artisan icon-registry:scan
php artisan icon-registry:scan --path=modules/billing/resources/views --json
```

| Option | Effect |
| --- | --- |
| `--path=` | A directory to scan instead of `resources/views`, `app` and `config`. Repeatable. Relative paths start at the application root. |
| `--json` | Print `icons` (by key, with `keywords` and `usages`), `unknown` and `dynamic` as JSON. Cannot be combined with `--write`. |
| `--write` | After a preview and a confirmation, add each icon's keywords to the metadata file as `use_cases`, and rebuild the cache if one exists. |
| `--force` | With `--write`, skip the confirmation. |

It reads `.php` and `.blade.php` files by pattern, and resolves names against the providers, not
the cache. See [Finding the icons you use](../usage/searching-and-metadata.md#finding-the-icons-you-use)
for what it recognises. It exits successfully even when it reports unknown icons.

## `icon-registry:search`

Searches the registry. Without a query it opens an interactive search; with one it prints a table.

```bash
php artisan icon-registry:search
php artisan icon-registry:search "remove document" --vendor=tabler
php artisan icon-registry:search home --json
```

| Option | Effect |
| --- | --- |
| `query` | The search query. |
| `--vendor=` | Only icons of this vendor. |
| `--provider=` | Only icons of this provider. |
| `--limit=50` | The maximum number of results. |
| `--fresh` | Ask the providers directly instead of reading the cache. |
| `--json` | Print the results as JSON, without the search index. |
| `--key-only` | In the interactive search, print only the selected key. |

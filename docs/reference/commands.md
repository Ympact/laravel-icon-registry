# Artisan commands

The commands are named `icon-registry:*`. Blade Icons already registers `icons:cache` and
`icons:clear`, for its own manifest.

## `icon-registry:cache`

Writes the cache to the `cache` path. `php artisan optimize` runs it.

## `icon-registry:clear`

Deletes the cache. `php artisan optimize:clear` runs it.

## `icon-registry:doctor`

Reports the available providers, the number of icons and metadata entries, whether the cache is
fresh, metadata for icons that no longer exist, cache entries for icons that no longer exist, and
keys that more than one provider lists. It exits with a failure on stale metadata or stale cache
entries, so it can run in CI.

| Option | Effect |
| --- | --- |
| `--fix` | All three repairs below. |
| `--prune-meta` | Removes metadata entries for icons that no longer exist and rewrites the file, keeping its comments. |
| `--clear-cache` | Deletes the cache. |
| `--rebuild-cache` | Rebuilds the cache. |

## `icon-registry:search`

Searches the registry. Without a query it opens an interactive search; with one it prints a table.

```bash
php artisan icon-registry:search
php artisan icon-registry:search "bestand verwijderen" --vendor=tabler
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

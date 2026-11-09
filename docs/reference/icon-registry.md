# IconRegistry API

`Ympact\IconRegistry\IconRegistry` is bound scoped: one instance per request or queued job, which
holds the icon set in memory for that request. Resolve it from the container, or use the
`Ympact\IconRegistry\Facades\Icons` facade.

## Reading

| Method | Returns |
| --- | --- |
| `all(): Collection<int, Icon>` | Every icon, from the cache when there is one and from the providers otherwise. |
| `icons(bool $fresh = false): Collection<int, Icon>` | `all()`, or a fresh scan of the providers. |
| `find(string $key, bool $fresh = false): ?Icon` | The icon with this key, or null. |
| `vendors(): Collection<int, string>` | The vendors, sorted. |
| `search(?string $query = null, ?string $vendor = null, int $limit = 50, bool $fresh = false, ?string $provider = null): Collection<int, Icon>` | See [Searching and metadata](../usage/searching-and-metadata.md). |
| `render(string $key, ComponentAttributeBag\|array $attributes = []): Htmlable` | The icon drawn by its provider, decorative unless `label` or another accessible name is given ([Accessibility](../usage/storing-and-drawing-icons.md#accessibility)). Throws `UnknownIcon` for a key no provider lists. |
| `purposes(): Collection<string, Purpose>` | The application's well-formed purposes, by name. |
| `purpose(string $name): Icon` | The icon of a purpose. Throws `UnknownPurpose` for an undefined or malformed purpose and `UnknownIcon` for a missing icon. |
| `renderPurpose(string $name, ComponentAttributeBag\|array $attributes = []): Htmlable` | The icon of a purpose, decorative unless `labelled` or `label` is given ([Draw by purpose](../usage/storing-and-drawing-icons.md#draw-by-purpose)). |
| `providers(): array<string, IconProvider>` | The configured providers whose packages are installed. |
| `provider(string $name): IconProvider` | One of those; throws `InvalidArgumentException` otherwise. |

## Maintenance

| Method | Effect |
| --- | --- |
| `build(): Collection<int, Icon>` | Scans the providers and merges the metadata, without touching the cache. |
| `rebuildCache(): int` | Writes the cache and returns the number of icons. |
| `clearCache(): bool` | Deletes the cache; false when there was none. |
| `validate(): array` | The report `icon-registry:doctor` prints: `icon_count`, `metadata_count`, `cache_exists`, `cache_is_fresh`, `orphaned_metadata`, `icons_without_metadata`, `stale_cache_entries`, `uncached_icons`, `shadowed_icons`, `purpose_count`, `orphaned_purposes`, `malformed_purposes`. |
| `cleanMetadata(bool $write = false): array` | Drops metadata for missing icons and normalises the rest; writes the file when `$write` is true. Returns `removed`, `removed_count`, `metadata` and `written`. |
| `editorManifestPath(): string` | The configured `editor_manifest` path. |
| `writeEditorManifest(?string $path = null): int` | Writes the manifest for editor extensions from a fresh scan and returns the number of icons. |
| `metadata(): MetadataFile` | The metadata file. |
| `cachePath(): string` | The configured cache path. |

## `Icon`

A readonly value object.

| Property | Description |
| --- | --- |
| `key` | `<vendor>.<name>`; the value to store. |
| `vendor`, `name` | The two halves of the key. |
| `label` | From the metadata, or the name as a headline. |
| `provider` | The name of the provider that lists it. |
| `reference` | What the provider's component expects. |
| `path` | The file behind the icon, or null. |
| `aliases`, `useCases` | From the metadata. |
| `purposes` | The names of the purposes this icon stands for. |
| `terms`, `searchText` | The search index. Internal: left out of `jsonSerialize()`. |

## `Purpose`

A readonly value object.

| Property | Description |
| --- | --- |
| `name` | The purpose, such as `action.delete`. |
| `icon` | The key of the icon drawn for it. |
| `label` | The accessible name, or the translation key for it. |

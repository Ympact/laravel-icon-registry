# Roadmap

What is planned but not built yet. Remove an item once it is released.

- **Publish the VS Code extension** to the Marketplace, with an icon preview in the completion list.
- **Rebuild the cache when ympact/flux-icons builds icons.** flux-icons dispatches `IconsBuilt` on
  its unreleased v2 branch ([flux-icons#33](https://github.com/Ympact/flux-icons/pull/33)). Once that
  is released, the registry can refresh a stale cache in development and, where a vendor publishes
  tags, add them as aliases to new metadata entries.

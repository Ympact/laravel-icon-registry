<?php

namespace Ympact\IconRegistry;

/**
 * An icon as a provider finds it, before the registry adds its key, metadata and search terms.
 * The reference is what the provider's own component expects, which need not equal the key.
 */
final readonly class DiscoveredIcon
{
    /**
     * @param  string|null  $path  The file behind the icon, or null when it does not live on the local disk.
     */
    public function __construct(
        public string $vendor,
        public string $name,
        public string $reference,
        public ?string $path = null,
    ) {}
}

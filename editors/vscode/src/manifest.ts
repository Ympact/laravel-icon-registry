/** Bump with EDITOR_MANIFEST_VERSION in src/IconRegistry.php; a Pest test keeps the two equal. */
export const SUPPORTED_MANIFEST_VERSION = 1;

export interface ManifestIcon {
    key: string;
    name: string;
    label: string;
    provider: string;
    path: string | null;
    aliases: string[];
    use_cases: string[];
}

export interface Manifest {
    component: string;
    icons: IndexedIcon[];
}

export interface IndexedIcon extends ManifestIcon {
    lowerKey: string;
    lowerName: string;
    lowerLabel: string;
    haystack: string;
}

function isIcon(value: unknown): value is ManifestIcon {
    const icon = value as Partial<ManifestIcon> | null;

    return typeof icon?.key === 'string'
        && typeof icon.name === 'string'
        && typeof icon.label === 'string'
        && typeof icon.provider === 'string'
        && Array.isArray(icon.aliases)
        && Array.isArray(icon.use_cases);
}

/**
 * Parses a manifest, returning null for anything this version cannot read. The file is
 * generated from a workspace, so its shape is checked rather than assumed.
 */
export function parseManifest(json: string): Manifest | null {
    let data: { version?: unknown; component?: unknown; icons?: unknown };

    try {
        data = JSON.parse(json);
    } catch {
        return null;
    }

    if (data?.version !== SUPPORTED_MANIFEST_VERSION || typeof data.component !== 'string' || !Array.isArray(data.icons)) {
        return null;
    }

    return { component: data.component, icons: data.icons.filter(isIcon).map(index) };
}

function index(icon: ManifestIcon): IndexedIcon {
    return {
        ...icon,
        lowerKey: icon.key.toLowerCase(),
        lowerName: icon.name.toLowerCase(),
        lowerLabel: icon.label.toLowerCase(),
        haystack: [icon.key, icon.label, ...icon.aliases, ...icon.use_cases].join('\n').toLowerCase(),
    };
}

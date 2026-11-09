import type { IndexedIcon } from './manifest.js';

/** How far back to look for the opening tag; attributes never span more than this. */
export const CONTEXT_LENGTH = 2000;

const DEFAULT_LIMIT = 100;

function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\-]/g, '\\$&');
}

/**
 * The part of an icon key already typed, or null when the cursor is not at an icon key. `before`
 * is the text leading up to the cursor.
 */
export function iconKeyPrefix(before: string, component: string): string | null {
    const attributes = '(?:[^<>]|->)*?';
    const tag = `<x-${escapeRegExp(component)}\\s${attributes}`;
    const patterns = [
        new RegExp(`${tag}(?<![:\\w-])icon=["']([^"'\\n]*)$`),
        new RegExp(`${tag}:icon=(?:"'|'")([^"'\\n]*)$`),
        /\bIcons::(?:find|render)\(\s*["']([^"'\n]*)$/,
    ];

    for (const pattern of patterns) {
        const match = pattern.exec(before);

        if (match) {
            return match[1];
        }
    }

    return null;
}

function rank(icon: IndexedIcon, query: string): number {
    if (icon.lowerKey === query) {
        return 0;
    }

    if (icon.lowerKey.startsWith(query)) {
        return 1;
    }

    if (icon.lowerName.startsWith(query)) {
        return 2;
    }

    return icon.lowerLabel.startsWith(query) ? 3 : 4;
}

/**
 * Icons mentioning every word of the query, closest first. Substring matching only: typo
 * tolerance stays in the PHP search.
 */
export function searchIcons(icons: IndexedIcon[], query: string, limit = DEFAULT_LIMIT): IndexedIcon[] {
    const normalized = query.trim().toLowerCase();

    if (normalized === '') {
        return icons.slice(0, limit);
    }

    const tokens = normalized.split(/[\s._-]+/).filter(Boolean);

    return icons
        .filter((icon) => tokens.every((token) => icon.haystack.includes(token)))
        .map((icon) => ({ icon, order: rank(icon, normalized) }))
        .sort((first, second) => first.order - second.order || first.icon.key.localeCompare(second.icon.key))
        .slice(0, limit)
        .map(({ icon }) => icon);
}

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { parseManifest, SUPPORTED_MANIFEST_VERSION } from '../manifest.js';

const icon = { key: 'tabler.trash', name: 'trash', label: 'Remove', provider: 'flux', path: null, aliases: ['Dustbin'], use_cases: [] };

test('reads a manifest and lower-cases what search compares', () => {
    const manifest = parseManifest(JSON.stringify({ version: SUPPORTED_MANIFEST_VERSION, component: 'registry-icon', icons: [icon] }));

    assert.equal(manifest?.component, 'registry-icon');
    assert.match(manifest?.icons[0].haystack ?? '', /dustbin/);
});

test('refuses another version, malformed JSON and a missing icon list', () => {
    assert.equal(parseManifest(JSON.stringify({ version: 999, component: 'x', icons: [] })), null);
    assert.equal(parseManifest('{not json'), null);
    assert.equal(parseManifest('null'), null);
    assert.equal(parseManifest(JSON.stringify({ version: SUPPORTED_MANIFEST_VERSION, component: 'x' })), null);
});

test('drops an entry of the wrong shape and keeps the rest', () => {
    const manifest = parseManifest(JSON.stringify({ version: SUPPORTED_MANIFEST_VERSION, component: 'x', icons: [icon, { key: 1 }, null] }));

    assert.equal(manifest?.icons.length, 1);
});

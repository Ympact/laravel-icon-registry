import assert from 'node:assert/strict';
import { test } from 'node:test';
import { parseManifest, SUPPORTED_MANIFEST_VERSION } from '../manifest.js';
import { iconKeyPrefix, searchIcons } from '../matching.js';

const TAG = 'registry-icon';

test('finds the typed key in a static attribute', () => {
    assert.equal(iconKeyPrefix('<x-registry-icon icon="tabler.ho', TAG), 'tabler.ho');
    assert.equal(iconKeyPrefix("<x-registry-icon class=\"size-5\" icon='", TAG), '');
});

test('finds the typed key across lines and after an arrow in an attribute', () => {
    assert.equal(iconKeyPrefix('<x-registry-icon\n    class="a"\n    icon="ta', TAG), 'ta');
    assert.equal(iconKeyPrefix('<x-registry-icon :class="$a->b" icon="ta', TAG), 'ta');
});

test('finds a quoted string in a bound attribute, but not an expression', () => {
    assert.equal(iconKeyPrefix(`<x-registry-icon :icon="'tabler.`, TAG), 'tabler.');
    assert.equal(iconKeyPrefix('<x-registry-icon :icon="$item->ic', TAG), null);
});

test('finds the key in facade calls', () => {
    assert.equal(iconKeyPrefix("Icons::find('tab", TAG), 'tab');
    assert.equal(iconKeyPrefix('Icons::render( "', TAG), '');
});

test('stays out of other tags, attributes and finished values', () => {
    assert.equal(iconKeyPrefix('<flux:icon icon="ho', TAG), null);
    assert.equal(iconKeyPrefix('<x-registry-iconic icon="ho', TAG), null);
    assert.equal(iconKeyPrefix('<x-registry-icon data-icon="ho', TAG), null);
    assert.equal(iconKeyPrefix('<x-registry-icon class="ho', TAG), null);
    assert.equal(iconKeyPrefix('<x-registry-icon icon="a" /> <p class="ho', TAG), null);
    assert.equal(iconKeyPrefix('$other->find("ho', TAG), null);
});

test('honours a configured tag and treats it literally', () => {
    assert.equal(iconKeyPrefix('<x-ri icon="ho', 'ri'), 'ho');
    assert.equal(iconKeyPrefix('<x-rXi icon="ho', 'r.i'), null);
});

const raw = (key: string, label: string, aliases: string[] = []) => ({
    key, name: key.split('.')[1], label, provider: 'flux', path: null, aliases, use_cases: [],
});

const icons = parseManifest(JSON.stringify({
    version: SUPPORTED_MANIFEST_VERSION,
    component: TAG,
    icons: [raw('tabler.trash', 'Remove', ['dustbin']), raw('tabler.home', 'House'), raw('app.tablet', 'Tablet'), raw('heroicons.home', 'Start')],
}))!.icons;

const keys = (query: string, limit?: number) => searchIcons(icons, query, limit).map((icon) => icon.key);

test('finds an icon by alias and by label', () => {
    assert.deepEqual(keys('dustbin'), ['tabler.trash']);
    assert.deepEqual(keys('remove'), ['tabler.trash']);
});

test('requires every word, in any order', () => {
    assert.deepEqual(keys('home tabler'), ['tabler.home']);
    assert.deepEqual(keys('home dustbin'), []);
});

test('ranks a key prefix above a name prefix above a mention', () => {
    assert.deepEqual(keys('tabl'), ['tabler.home', 'tabler.trash', 'app.tablet']);
});

test('lists icons for an empty query, within the limit', () => {
    assert.equal(keys('').length, 4);
    assert.equal(keys('', 2).length, 2);
});

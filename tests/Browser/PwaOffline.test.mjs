import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const workerSource = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
const offlineSource = readFileSync(new URL('../../public/offline.js', import.meta.url), 'utf8');

function worker(fetch) {
    const listeners = {};
    const deleted = [];
    const cache = {addAll: async () => {}, put: () => {}, match: async () => undefined};
    const context = {
        URL, fetch,
        self: {location: {origin: 'https://deepsky.test'}, addEventListener: (name, callback) => { listeners[name] = callback; }, skipWaiting: async () => {}, clients: {claim: async () => {}}},
        caches: {open: async () => cache, match: async () => 'offline-shell', keys: async () => ['another-app', 'deepsky-static-v1', 'deepsky-static-v2', 'deepsky-offline-v1'], delete: async (key) => { deleted.push(key); }},
    };
    vm.runInNewContext(workerSource, context);
    return {listeners, deleted};
}

test('offline navigation returns the static shell and POST requests remain untouched', async () => {
    const {listeners} = worker(async () => { throw new Error('offline'); });
    let response;
    listeners.fetch({request: {method: 'GET', mode: 'navigate'}, respondWith: (promise) => { response = promise; }});
    assert.equal(await response, 'offline-shell');
    listeners.fetch({request: {method: 'POST'}, respondWith: () => assert.fail('POST intercepted')});
});

test('online private pages are fetched without storing their HTML', async () => {
    const {listeners} = worker(async () => 'private-html');
    let response;
    listeners.fetch({request: {method: 'GET', mode: 'navigate'}, respondWith: (promise) => { response = promise; }});
    assert.equal(await response, 'private-html');
});

test('activation removes only obsolete Deepsky caches', async () => {
    const {listeners, deleted} = worker(async () => {});
    let completion;
    listeners.activate({waitUntil: (promise) => { completion = promise; }});
    await completion;
    assert.deepEqual(deleted, ['deepsky-static-v1']);
});

class Element {
    children = [];
    listeners = {};
    attributes = {};
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    setAttribute(name, value) { this.attributes[name] = value; }
}

test('offline plan text stays literal and saved copies can be deleted', () => {
    const nodes = Object.fromEntries(['plans', 'status', 'night-mode', 'clear-plans'].map((id) => [id, new Element()]));
    const saved = {owner: '1', plans: [{id: 7, date: '04/10/2026', status: 'Planifié', notes: '<img src=x onerror=alert(1)>', savedAt: '2026-10-04T20:00:00Z', targets: [{name: 'M31', start: '04/10 22:00', end: '05/10 02:00', score: 90}]}]};
    const storage = new Map([['deepsky-offline-plans-v1', JSON.stringify(saved)]]);
    const document = {getElementById: (id) => nodes[id], createElement: () => new Element(), documentElement: {dataset: {}}};
    vm.runInNewContext(offlineSource, {document, navigator: {}, localStorage: {getItem: (key) => storage.get(key), setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key)}, confirm: () => true, Date});
    const article = nodes.plans.children[0];
    assert.equal(article.children.find((node) => node.textContent === saved.plans[0].notes).textContent, saved.plans[0].notes);
    article.children.at(-1).listeners.click();
    assert.equal(JSON.parse(storage.get('deepsky-offline-plans-v1')).plans.length, 0);
    nodes['night-mode'].listeners.click();
    assert.equal(document.documentElement.dataset.nightMode, 'red');
    assert.equal(storage.get('deepsky-night-mode'), 'red');
});

import test from 'node:test';
import assert from 'node:assert/strict';
import {objectDescription} from '../../resources/js/object-description.js';

function editor() {
    const instance = objectDescription();
    const fields = {'[name="celestial_object_id"]': {value: '42'}, '[name="_token"]': {value: 'test-token'}};
    instance.$root = {
        dataset: {initialDescription: 'Texte existant', generateUrl: '/generate'},
        closest: () => ({querySelector: selector => fields[selector]}),
    };
    instance.init();
    return {instance, fields};
}

test('generation keeps existing text until the suggestion is explicitly accepted', async () => {
    const {instance} = editor();
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => ({ok: true, json: async () => ({description: 'Proposition', object_id: 42})});
    try {
        await instance.generate();
        assert.equal(instance.text, 'Texte existant');
        assert.equal(instance.suggestion, 'Proposition');
        instance.accept();
        assert.equal(instance.text, 'Proposition');
        assert.equal(instance.suggestion, '');
    } finally { globalThis.fetch = previousFetch; }
});

test('a suggestion cannot be inserted after changing the selected celestial object', async () => {
    const {instance, fields} = editor();
    instance.suggestion = 'Ancienne cible';
    instance.suggestionObjectId = '42';
    fields['[name="celestial_object_id"]'].value = '43';
    instance.accept();
    assert.equal(instance.text, 'Texte existant');
    assert.match(instance.error, /changé/);

    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => {
        fields['[name="celestial_object_id"]'].value = '44';
        return {ok: true, json: async () => ({description: 'Cible obsolète', object_id: 43})};
    };
    try {
        await instance.generate();
        assert.equal(instance.suggestion, '');
        assert.match(instance.error, /changé/);
        assert.equal(instance.loading, false);
    } finally { globalThis.fetch = previousFetch; }
});

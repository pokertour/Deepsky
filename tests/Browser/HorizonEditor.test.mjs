import test from 'node:test';
import assert from 'node:assert/strict';
import {registerHorizonTools} from '../../resources/js/horizon-editor.js';

function editor(mask) {
    const components = {};
    registerHorizonTools({data: (name, factory) => { components[name] = factory; }});
    let submit;
    const form = {addEventListener: (_, handler) => { submit = handler; }, removeEventListener: () => {}};
    const instance = components.horizonEditor();
    instance.$root = {dataset: {mask}, closest: () => form};
    instance.$refs = {error: {focus: () => {}}};
    instance.$nextTick = callback => callback();
    instance.init();
    return {instance, submit: () => { let prevented = false; submit({preventDefault: () => { prevented = true; }}); return prevented; }};
}

test('duplicate directions block saving instead of silently collapsing into one point', () => {
    const {instance, submit} = editor('{"0":20}');
    instance.points.push({id: 10, azimuth: 0, altitude: 50});
    instance.sync();
    assert.match(instance.error, /unique/);
    assert.equal(submit(), true);
    assert.equal(instance.points.length, 2);
});

test('invalid imported masks preserve existing points and cannot be submitted', () => {
    const {instance, submit} = editor('{"90.5":30}');
    for (const invalid of ['[]', '{"10":null}', '{"10":"30"}', '{"360":20}', '{"10":91}', '{"10":20,"010":30}']) {
        instance.json = invalid;
        instance.applyJson();
        assert.ok(instance.error, invalid);
        assert.equal(instance.points[0].azimuth, 90.5);
        assert.equal(submit(), true);
    }
    instance.json = '{"90.5":25.5}';
    instance.applyJson();
    assert.equal(instance.error, '');
    assert.equal(submit(), false);
    assert.equal(JSON.parse(instance.json)['90.5'], 25.5);
});

test('a free horizon submits an empty mask and preset directions stay numeric', () => {
    const {instance, submit} = editor('{}');
    instance.preset();
    assert.equal(instance.points.length, 8);
    assert.ok(Object.values(JSON.parse(instance.json)).every(value => typeof value === 'number'));
    instance.clear();
    assert.equal(instance.json, '');
    assert.equal(submit(), false);
});

test('photo tracing requires calibration and preserves points when a new reference invalidates it', () => {
    const {instance, submit} = editor('{}');
    instance.$refs.photoPreview = {
        getBoundingClientRect: () => ({left: 0, top: 0, width: 200, height: 100}),
        scrollIntoView: () => {},
    };
    instance.photoKind = 'photo';
    instance.photoAspect = 2;
    instance.photoHorizon = 50;
    instance.referenceBearing = 90;
    instance.markPhoto({clientX: 100, clientY: 50});
    assert.equal(instance.photoCalibrated, false);
    assert.equal(instance.points.length, 0);
    instance.photoHorizontal = 90;
    instance.markPhoto({clientX: 100, clientY: 50});
    assert.equal(instance.photoBearing, 90);
    assert.equal(instance.photoCalibrated, true);
    instance.markPhoto({clientX: 100, clientY: 0});
    assert.deepEqual(JSON.parse(instance.json), {'90': 26.6});
    assert.equal(submit(), false);
    instance.receiveReference(null);
    assert.equal(instance.photoCalibrated, false);
    instance.photoAction = 'trace';
    instance.markPhoto({clientX: 0, clientY: 0});
    assert.deepEqual(JSON.parse(instance.json), {'90': 26.6});
});

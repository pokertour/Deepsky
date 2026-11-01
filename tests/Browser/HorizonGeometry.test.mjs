import test from 'node:test';
import assert from 'node:assert/strict';
import {horizonAltitude, horizonLine, trajectorySegments} from '../../resources/js/horizon-geometry.js';

test('decimal directions interpolate across north without truncation', () => {
    const mask = [{azimuth: 350.5, altitude: 10}, {azimuth: 10.5, altitude: 30}];
    assert.equal(horizonAltitude(mask, 0.5), 20);
    assert.equal(horizonAltitude(mask, 360.5), 20);
    assert.equal(horizonAltitude(mask, -359.5), 20);
    assert.equal(horizonAltitude([], 110), 0);
    assert.equal(horizonAltitude([{azimuth: 33.3, altitude: 25}], 300), 25);
    const vertices = horizonLine(mask).split(' ');
    assert.equal(vertices[0].split(',')[1], vertices.at(-1).split(',')[1]);
});

test('a north crossing produces two short segments rather than a line across the sky', () => {
    const segments = trajectorySegments([
        {azimuth: 350, altitude: 30, horizon: 20, visible: true},
        {azimuth: 10, altitude: 50, horizon: 20, visible: true},
    ]);
    assert.deepEqual(segments, [
        {points: '740,144 760,124', status: 'visible'},
        {points: '40,124 60,104', status: 'visible'},
    ]);
});

test('a reverse north crossing is also split and obstacles differ from a low target', () => {
    const segments = trajectorySegments([
        {azimuth: 10, altitude: 10, horizon: 15, visible: false},
        {azimuth: 350, altitude: 10, horizon: 0, visible: false},
        {azimuth: 330, altitude: 15, horizon: 0, visible: false},
    ]);
    assert.equal(segments.length, 3);
    assert.deepEqual(segments.map(segment => segment.status), ['blocked', 'blocked', 'low']);
    assert.equal(segments[0].points, '60,184 40,184');
    assert.equal(segments[1].points, '760,184 740,184');
});

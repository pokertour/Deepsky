import test from 'node:test';
import assert from 'node:assert/strict';
import {cameraBasis, horizontalToVector, pointingOf, projectGnomonic} from '../../resources/js/sky-ar.js';

const close = (actual, expected, tolerance = 0.5) => assert.ok(Math.abs(actual - expected) < tolerance, `${actual} ≉ ${expected}`);

test('a phone held upright and facing north looks at the northern horizon', () => {
    const pointing = pointingOf(cameraBasis(0, 90, 0));
    close(pointing.altitude, 0);
    close(pointing.azimuth, 0);
});

test('turning the phone counter-clockwise points it west, tilting it back raises it', () => {
    close(pointingOf(cameraBasis(90, 90, 0)).azimuth, 270);
    const raised = pointingOf(cameraBasis(180, 135, 0));
    close(raised.azimuth, 180);
    close(raised.altitude, 45);
    close(pointingOf(cameraBasis(0, 0, 0)).altitude, -90);
});

test('the screen keeps east on the right when facing north', () => {
    const basis = cameraBasis(0, 90, 0);
    const east = projectGnomonic(horizontalToVector(0, 10), basis);
    const above = projectGnomonic(horizontalToVector(10, 0), basis);
    assert.ok(east.x > 0);
    assert.ok(above.y > 0);
    assert.equal(projectGnomonic(horizontalToVector(0, 180), basis), null);
});

test('landscape screens use the device side as the top of the view', () => {
    const basis = cameraBasis(0, 90, 0, 90);
    const above = projectGnomonic(horizontalToVector(10, 0), basis);
    assert.ok(Math.abs(above.x) > Math.abs(above.y));
});

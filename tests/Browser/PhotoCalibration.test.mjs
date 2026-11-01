import test from 'node:test';
import assert from 'node:assert/strict';
import {compassBearing, photoDirection, alignPhotoToReference} from '../../resources/js/photo-calibration.js';

const close = (actual, expected) => assert.ok(Math.abs(actual - expected) < 1e-8, `${actual} != ${expected}`);

test('map references use geographic directions, including across the date line', () => {
    close(compassBearing([0, 0], [1, 0]), 0);
    close(compassBearing([0, 0], [0, 1]), 90);
    close(compassBearing([0, 0], [-1, 0]), 180);
    close(compassBearing([0, 0], [0, -1]), 270);
    close(compassBearing([0, 179], [0, -179]), 90);
    assert.equal(compassBearing([47, -1], [47, -1]), null);
});

test('an ordinary rectilinear photo covers its field instead of the whole horizon', () => {
    const calibration = {kind: 'photo', horizontal: 90, horizon: 50, aspect: 1, bearing: 100};
    close(photoDirection(0, 0.5, calibration).azimuth, 55);
    close(photoDirection(1, 0.5, calibration).azimuth, 145);
    close(photoDirection(0.5, 0, calibration).altitude, 45);
    close(photoDirection(1, 0, calibration).altitude, 35.264389682754654);
    close(photoDirection(0.5, 0.5, calibration).altitude, 0);
});

test('a tilted photo is aligned from its true horizontal line and a known landmark', () => {
    const calibration = {kind: 'photo', horizontal: 90, horizon: 75, aspect: 1, bearing: 0};
    close(photoDirection(0.8, 0.75, calibration).altitude, 0);
    const bearing = alignPhotoToReference(0.8, 0.3, calibration, 350);
    close(photoDirection(0.8, 0.3, {...calibration, bearing}).azimuth, 350);
});

test('panorama reference alignment wraps at north and keeps angular heights', () => {
    const calibration = {kind: 'panorama', vertical: 90, horizon: 75, bearing: 0};
    const bearing = alignPhotoToReference(0.9, 0.5, calibration, 10);
    close(bearing, 46);
    close(photoDirection(0.9, 0.5, {...calibration, bearing}).azimuth, 10);
    close(photoDirection(0.9, 0.5, {...calibration, bearing}).altitude, 22.5);
});

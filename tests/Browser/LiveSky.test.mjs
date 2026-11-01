import test from 'node:test';
import assert from 'node:assert/strict';
import {equatorialToHorizontal, localSiderealDegrees, moonPosition, sunPosition, twilightPhase, zenithConstellation} from '../../resources/js/live-sky.js';

const NANTES = {latitude: 47.218, longitude: -1.554};

test('sidereal time matches the J2000 reference', () => {
    assert.ok(Math.abs(localSiderealDegrees(new Date('2000-01-01T12:00:00Z'), 0) - 280.46) < 0.01);
});

test('polaris stays at the observer latitude and the summer sun reaches its maximum height', () => {
    for (const hour of ['00', '06', '12', '18']) {
        const lst = localSiderealDegrees(new Date(`2026-10-30T${hour}:00:00Z`), NANTES.longitude);
        const polaris = equatorialToHorizontal(2.53 * 15, 89.26, NANTES.latitude, lst);
        assert.ok(Math.abs(polaris.altitude - NANTES.latitude) < 1);
    }

    const solstice = new Date('2026-06-21T12:06:00Z');
    const sun = sunPosition(solstice);
    assert.ok(Math.abs(sun.dec - 23.44) < 0.1);
    const horizontal = equatorialToHorizontal(sun.ra, sun.dec, NANTES.latitude, localSiderealDegrees(solstice, NANTES.longitude));
    assert.ok(Math.abs(horizontal.altitude - (90 - NANTES.latitude + 23.44)) < 1);
    assert.ok(Math.abs(horizontal.azimuth - 180) < 5);
});

test('moon illumination follows known full and new moons', () => {
    assert.ok(moonPosition(new Date('2024-01-25T17:54:00Z')).illumination > 0.97);
    assert.ok(moonPosition(new Date('2024-01-11T11:57:00Z')).illumination < 0.03);
    assert.equal(moonPosition(new Date('2024-01-18T12:00:00Z')).waxing, true);
});

test('labels the twilight phase and the constellation overhead', () => {
    assert.equal(twilightPhase(5), 'Jour');
    assert.equal(twilightPhase(-14), 'Crépuscule astronomique');
    assert.equal(twilightPhase(-30), 'Nuit noire');
    assert.equal(zenithConstellation(new Date('2026-09-01T21:30:00Z'), NANTES.latitude, NANTES.longitude), 'Cygne');
});

test('planets follow known conjunctions and oppositions', async () => {
    const {planetPosition, sunPosition} = await import('../../resources/js/live-sky.js');
    const separation = (a, b) => Math.acos(Math.sin(a.dec * Math.PI / 180) * Math.sin(b.dec * Math.PI / 180) + Math.cos(a.dec * Math.PI / 180) * Math.cos(b.dec * Math.PI / 180) * Math.cos((a.ra - b.ra) * Math.PI / 180)) * 180 / Math.PI;

    const greatConjunction = new Date('2020-12-21T18:00:00Z');
    assert.ok(separation(planetPosition('jupiter', greatConjunction), planetPosition('saturn', greatConjunction)) < 0.5);

    const marsOpposition = new Date('2020-10-13T23:00:00Z');
    assert.ok(separation(planetPosition('mars', marsOpposition), sunPosition(marsOpposition)) > 170);

    const venusElongation = new Date('2020-03-24T12:00:00Z');
    const elongation = separation(planetPosition('venus', venusElongation), sunPosition(venusElongation));
    assert.ok(Math.abs(elongation - 46.1) < 1.5);
});

test('the galactic centre lies in Sagittarius', async () => {
    const {galacticToEquatorial} = await import('../../resources/js/live-sky.js');
    const centre = galacticToEquatorial(0, 0);
    assert.ok(Math.abs(centre.ra - 266.4) < 0.2);
    assert.ok(Math.abs(centre.dec + 28.94) < 0.2);
});

test('night summary finds sunset, astronomical darkness and observable targets in Nantes', async () => {
    const {nightSummary} = await import('../../resources/js/live-sky.js');
    const winter = nightSummary(new Date('2026-12-21T12:00:00Z'), NANTES.latitude, NANTES.longitude, [
        {label: 'M 42', ra: 83.822, dec: -5.391},
        {label: 'Sud lointain', ra: 266.4, dec: -60},
    ]);

    const minutesFrom = (date, iso) => Math.abs(date.getTime() - new Date(iso).getTime()) / 60000;
    assert.ok(minutesFrom(winter.sunset, '2026-12-21T16:12:00Z') < 10);
    assert.ok(minutesFrom(winter.darkStart, '2026-12-21T18:00:00Z') < 15);
    assert.ok(minutesFrom(winter.sunrise, '2026-12-22T07:48:00Z') < 10);
    assert.deepEqual(winter.targets.map((target) => target.label), ['M 42']);
    assert.ok(Math.abs(winter.targets[0].altitude - (90 - NANTES.latitude - 5.391)) < 2);

    const lateEvening = nightSummary(new Date('2026-12-21T21:30:00Z'), NANTES.latitude, NANTES.longitude);
    assert.ok(minutesFrom(lateEvening.sunset, '2026-12-21T16:12:00Z') < 10, 'keeps the sunset that opened the current night');
    assert.ok(minutesFrom(lateEvening.sunrise, '2026-12-22T07:48:00Z') < 10);
    assert.equal(lateEvening.alreadyDark, true);
});

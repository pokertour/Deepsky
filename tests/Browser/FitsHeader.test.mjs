import test from 'node:test';
import assert from 'node:assert/strict';
import {parseFitsHeader, parseXisfHeader, summarizeHeaders} from '../../resources/js/fits-header.js';

const card = (text) => text.padEnd(80, ' ');
const fits = (cards) => [...cards.map(card), card('END')].join('').padEnd(2880, ' ');

test('parses FITS cards with strings, numbers, booleans and comments', () => {
    const header = parseFitsHeader(fits([
        'SIMPLE  =                    T / file does conform to FITS standard',
        "IMAGETYP= 'LIGHT   '           / Type of exposure",
        'EXPOSURE=                300.0 / [s] Exposure duration',
        "OBJECT  = 'M 31 l''Andromède'  / Name of the object of interest",
        'GAIN    =                  100',
        'CCD-TEMP=                -10.2',
    ]));

    assert.equal(header.SIMPLE, true);
    assert.equal(header.IMAGETYP, 'LIGHT');
    assert.equal(header.EXPOSURE, 300);
    assert.equal(header.OBJECT, "M 31 l'Andromède");
    assert.equal(header['CCD-TEMP'], -10.2);
});

test('reads FITS keywords embedded in an XISF header', () => {
    const header = parseXisfHeader('<xisf><Image><FITSKeyword name="EXPTIME" value="180." comment=""/><FITSKeyword name="FILTER" value="\'Ha\'" comment=""/></Image></xisf>');

    assert.equal(header.EXPTIME, 180);
    assert.equal(header.FILTER, 'Ha');
});

test('groups subs into nights and lots and derives the form fields', () => {
    const light = (date, filter, extra = {}) => ({IMAGETYP: 'LIGHT', 'DATE-OBS': date, FILTER: filter, EXPOSURE: 300, GAIN: 100, OFFSET: 50, 'CCD-TEMP': -10, FOCALLEN: 530, OBJECT: 'NGC 7000', ...extra});
    const summary = summarizeHeaders([
        light('2026-09-12T21:30:00', 'Ha'),
        light('2026-09-13T01:30:00', 'Ha'),
        light('2026-09-13T02:00:00', 'OIII', {'CCD-TEMP': -9}),
        light('2026-09-14T22:00:00', 'Ha'),
        {IMAGETYP: 'Flat Frame', 'DATE-OBS': '2026-09-13T05:00:00', FILTER: 'Ha', EXPOSURE: 1.5},
        {IMAGETYP: 'DARK', 'DATE-OBS': '2026-09-13T05:30:00', EXPOSURE: 300},
    ]);

    assert.equal(summary.lightCount, 4);
    assert.equal(summary.calibrationCount, 2);
    assert.equal(summary.sessions.length, 2);
    assert.equal(summary.sessions[0].camera_mode, 'mono');
    assert.deepEqual(summary.sessions[0].frames.map(({type, filter, count, seconds}) => [type, filter, count, seconds]), [
        ['light', 'Ha', 2, 300],
        ['light', 'OIII', 1, 300],
        ['flat', 'Ha', 1, 1.5],
        ['dark', '', 1, 300],
    ]);
    assert.deepEqual(summary.sessions[1].frames.map(({count}) => count), [1]);
    assert.equal(summary.target, 'NGC 7000');
    assert.equal(summary.gain, 100);
    assert.equal(summary.offset, 50);
    assert.equal(summary.sensorTemp, '-10°C');
    assert.equal(summary.focalLength, 530);
    assert.equal(summary.filters, 'Ha / OIII');
});

test('one-shot colour cameras are detected from the Bayer pattern', () => {
    const summary = summarizeHeaders([{IMAGETYP: 'Light', 'DATE-OBS': '2026-02-10T20:00:00', EXPTIME: 120, BAYERPAT: 'RGGB'}]);

    assert.equal(summary.sessions[0].camera_mode, 'color');
    assert.deepEqual(summary.sessions[0].frames[0], {type: 'light', filter: '', count: 1, seconds: 120});
});

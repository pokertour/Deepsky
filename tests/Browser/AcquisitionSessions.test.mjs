import test from 'node:test';
import assert from 'node:assert/strict';
import {acquisitionSessions, integrationSeconds, formatDuration} from '../../resources/js/acquisition-sessions.js';

test('only lights contribute to integration across nights and filters', () => {
    assert.equal(integrationSeconds([
        {frames: [{type: 'light', count: 30, seconds: 120}, {type: 'dark', count: 50, seconds: 120}]},
        {frames: [{type: 'light', count: 20, seconds: 180}, {type: 'flat', count: 30, seconds: 0.5}]},
    ]), 7200);
    assert.equal(formatDuration(7200), '2h');
    assert.equal(formatDuration(3 * 0.125), '0.375s');
});

test('duplicating a session creates independent frame rows and clears its date', () => {
    const editor = acquisitionSessions();
    editor.$root = {dataset: {initialExposure: '3600', sessions: JSON.stringify([
        {date: '2026-08-05', camera_mode: 'mono', frames: [{type: 'light', filter: 'R', count: 30, seconds: 120}]},
    ])}};
    editor.init();
    editor.duplicateSession(0);
    editor.sessions[1].frames[0].count = 10;
    assert.equal(editor.sessions[0].frames[0].count, 30);
    assert.equal(editor.sessions[1].date, '');
    assert.equal(new Set(editor.sessions.flatMap(session => [session.key, ...session.frames.map(frame => frame.key)])).size, 4);
    assert.equal(editor.total, 4800);
    assert.deepEqual(editor.summary, [{filter: 'R', count: 40, seconds: 4800}]);
    editor.removeSession(1);
    editor.removeSession(0);
    assert.equal(editor.manualExposure, '3600');
});

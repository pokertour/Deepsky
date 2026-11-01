import test from 'node:test';
import assert from 'node:assert/strict';
import {qrMatrix, qrSvg} from '../../resources/js/qr-code.js';

test('picks the smallest version that fits and keeps the finder patterns', () => {
    assert.equal(qrMatrix('HELLO').length, 21);
    const otpauth = 'otpauth://totp/Deepsky:astro%40example.com?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Deepsky&algorithm=SHA1&digits=6&period=30';
    const matrix = qrMatrix(otpauth);
    assert.equal(matrix.length, 49);
    for (const [x, y] of [[0, 0], [matrix.length - 7, 0], [0, matrix.length - 7]]) {
        assert.deepEqual(matrix[y + 3].slice(x, x + 7), [true, false, true, true, true, false, true]);
    }
});

test('renders a self-contained svg and rejects oversized payloads', () => {
    assert.match(qrSvg('HELLO'), /^<svg[^>]+viewBox="0 0 29 29"/);
    assert.throws(() => qrMatrix('x'.repeat(300)), /trop long/);
});

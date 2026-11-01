import { t } from './i18n.js';
/**
 * Encodeur QR minimal (mode octet, correction M, versions 1 à 10) pour afficher
 * l'URI otpauth:// de la double authentification sans dépendance externe.
 * Structure inspirée de l'implémentation de référence de Project Nayuki.
 */
const ECC_PER_BLOCK = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
const BLOCK_COUNT = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
const FORMAT_ECC_M = 0;

function rawDataModules(version) {
    let result = (16 * version + 128) * version + 64;
    if (version >= 2) {
        const alignCount = Math.floor(version / 7) + 2;
        result -= (25 * alignCount - 10) * alignCount - 55;
        if (version >= 7) result -= 36;
    }
    return result;
}

function dataCodewords(version) {
    return Math.floor(rawDataModules(version) / 8) - ECC_PER_BLOCK[version] * BLOCK_COUNT[version];
}

function gfMultiply(x, y) {
    let z = 0;
    for (let i = 7; i >= 0; i--) {
        z = (z << 1) ^ ((z >>> 7) * 0x11d);
        z ^= ((y >>> i) & 1) * x;
    }
    return z;
}

function rsDivisor(degree) {
    const result = new Array(degree).fill(0);
    result[degree - 1] = 1;
    let root = 1;
    for (let i = 0; i < degree; i++) {
        for (let j = 0; j < result.length; j++) {
            result[j] = gfMultiply(result[j], root);
            if (j + 1 < result.length) result[j] ^= result[j + 1];
        }
        root = gfMultiply(root, 0x02);
    }
    return result;
}

function rsRemainder(data, divisor) {
    const result = new Array(divisor.length).fill(0);
    for (const byte of data) {
        const factor = byte ^ result.shift();
        result.push(0);
        divisor.forEach((coefficient, i) => { result[i] ^= gfMultiply(coefficient, factor); });
    }
    return result;
}

function alignmentPositions(version, size) {
    if (version === 1) return [];
    const count = Math.floor(version / 7) + 2;
    const step = Math.ceil((version * 4 + 4) / (count * 2 - 2)) * 2;
    const result = [6];
    for (let position = size - 7; result.length < count; position -= step) result.splice(1, 0, position);
    return result;
}

function encodeData(bytes, version) {
    const bits = [];
    const push = (value, length) => { for (let i = length - 1; i >= 0; i--) bits.push((value >>> i) & 1); };
    push(0b0100, 4);
    push(bytes.length, version < 10 ? 8 : 16);
    bytes.forEach((byte) => push(byte, 8));
    const capacity = dataCodewords(version) * 8;
    push(0, Math.min(4, capacity - bits.length));
    push(0, (8 - (bits.length % 8)) % 8);
    for (let pad = 0xec; bits.length < capacity; pad ^= 0xec ^ 0x11) push(pad, 8);

    const codewords = [];
    for (let i = 0; i < bits.length; i += 8) codewords.push(parseInt(bits.slice(i, i + 8).join(''), 2));
    return codewords;
}

function interleave(data, version) {
    const blockCount = BLOCK_COUNT[version];
    const eccLength = ECC_PER_BLOCK[version];
    const rawCodewords = Math.floor(rawDataModules(version) / 8);
    const shortBlockCount = blockCount - (rawCodewords % blockCount);
    const shortBlockLength = Math.floor(rawCodewords / blockCount);
    const divisor = rsDivisor(eccLength);
    const blocks = [];
    for (let i = 0, k = 0; i < blockCount; i++) {
        const block = data.slice(k, k + shortBlockLength - eccLength + (i < shortBlockCount ? 0 : 1));
        k += block.length;
        const ecc = rsRemainder(block, divisor);
        if (i < shortBlockCount) block.push(0);
        blocks.push(block.concat(ecc));
    }
    const result = [];
    for (let i = 0; i < blocks[0].length; i++) {
        blocks.forEach((block, j) => {
            if (i !== shortBlockLength - eccLength || j >= shortBlockCount) result.push(block[i]);
        });
    }
    return result;
}

function buildMatrix(version, codewords, mask) {
    const size = version * 4 + 17;
    const modules = Array.from({ length: size }, () => new Array(size).fill(false));
    const reserved = Array.from({ length: size }, () => new Array(size).fill(false));
    const setFunction = (x, y, dark) => { modules[y][x] = dark; reserved[y][x] = true; };

    for (let i = 0; i < size; i++) {
        setFunction(6, i, i % 2 === 0);
        setFunction(i, 6, i % 2 === 0);
    }
    for (const [cx, cy] of [[3, 3], [size - 4, 3], [3, size - 4]]) {
        for (let dy = -4; dy <= 4; dy++) {
            for (let dx = -4; dx <= 4; dx++) {
                const distance = Math.max(Math.abs(dx), Math.abs(dy));
                const x = cx + dx;
                const y = cy + dy;
                if (x >= 0 && x < size && y >= 0 && y < size) setFunction(x, y, distance !== 2 && distance !== 4);
            }
        }
    }
    const positions = alignmentPositions(version, size);
    const last = positions.length - 1;
    positions.forEach((x, i) => positions.forEach((y, j) => {
        if ((i === 0 && j === 0) || (i === 0 && j === last) || (i === last && j === 0)) return;
        for (let dy = -2; dy <= 2; dy++) {
            for (let dx = -2; dx <= 2; dx++) setFunction(x + dx, y + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
    }));

    const formatData = (FORMAT_ECC_M << 3) | mask;
    let remainder = formatData;
    for (let i = 0; i < 10; i++) remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
    const formatBits = ((formatData << 10) | remainder) ^ 0x5412;
    const formatBit = (i) => ((formatBits >>> i) & 1) === 1;
    for (let i = 0; i <= 5; i++) setFunction(8, i, formatBit(i));
    setFunction(8, 7, formatBit(6));
    setFunction(8, 8, formatBit(7));
    setFunction(7, 8, formatBit(8));
    for (let i = 9; i < 15; i++) setFunction(14 - i, 8, formatBit(i));
    for (let i = 0; i < 8; i++) setFunction(size - 1 - i, 8, formatBit(i));
    for (let i = 8; i < 15; i++) setFunction(8, size - 15 + i, formatBit(i));
    setFunction(8, size - 8, true);

    if (version >= 7) {
        let versionRemainder = version;
        for (let i = 0; i < 12; i++) versionRemainder = (versionRemainder << 1) ^ ((versionRemainder >>> 11) * 0x1f25);
        const versionBits = (version << 12) | versionRemainder;
        for (let i = 0; i < 18; i++) {
            const dark = ((versionBits >>> i) & 1) === 1;
            const a = size - 11 + (i % 3);
            const b = Math.floor(i / 3);
            setFunction(a, b, dark);
            setFunction(b, a, dark);
        }
    }

    let bitIndex = 0;
    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) right = 5;
        for (let vertical = 0; vertical < size; vertical++) {
            for (let j = 0; j < 2; j++) {
                const x = right - j;
                const upward = ((right + 1) & 2) === 0;
                const y = upward ? size - 1 - vertical : vertical;
                if (!reserved[y][x] && bitIndex < codewords.length * 8) {
                    modules[y][x] = ((codewords[bitIndex >>> 3] >>> (7 - (bitIndex & 7))) & 1) === 1;
                    bitIndex++;
                }
            }
        }
    }

    const masks = [
        (x, y) => (x + y) % 2 === 0,
        (x, y) => y % 2 === 0,
        (x) => x % 3 === 0,
        (x, y) => (x + y) % 3 === 0,
        (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0,
        (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0,
        (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0,
        (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0,
    ];
    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            if (!reserved[y][x] && masks[mask](x, y)) modules[y][x] = !modules[y][x];
        }
    }
    return modules;
}

function penalty(modules) {
    const size = modules.length;
    let score = 0;
    let dark = 0;
    for (let y = 0; y < size; y++) {
        for (const horizontal of [true, false]) {
            let run = 1;
            for (let i = 1; i <= size; i++) {
                const current = i < size ? (horizontal ? modules[y][i] : modules[i][y]) : null;
                const previous = horizontal ? modules[y][i - 1] : modules[i - 1][y];
                if (current === previous) {
                    run++;
                } else {
                    if (run >= 5) score += run - 2;
                    run = 1;
                }
            }
        }
        for (let x = 0; x < size; x++) {
            if (modules[y][x]) dark++;
            if (x < size - 1 && y < size - 1 && modules[y][x] === modules[y][x + 1]
                && modules[y][x] === modules[y + 1][x] && modules[y][x] === modules[y + 1][x + 1]) score += 3;
        }
    }
    return score + Math.floor(Math.abs(dark * 20 - size * size * 10) / (size * size)) * 10;
}

export function qrMatrix(text) {
    const bytes = [...new TextEncoder().encode(text)];
    const version = ECC_PER_BLOCK.findIndex((_, v) => v > 0 && dataCodewords(v) >= bytes.length + (v < 10 ? 2 : 3));
    if (version < 1) throw new Error(t('Texte trop long pour le QR code.'));
    const codewords = interleave(encodeData(bytes, version), version);

    let best = null;
    for (let mask = 0; mask < 8; mask++) {
        const modules = buildMatrix(version, codewords, mask);
        const score = penalty(modules);
        if (best === null || score < best.score) best = { modules, score };
    }
    return best.modules;
}

export function qrSvg(text, { quietZone = 4 } = {}) {
    const modules = qrMatrix(text);
    const size = modules.length + quietZone * 2;
    let path = '';
    modules.forEach((row, y) => row.forEach((dark, x) => {
        if (dark) path += `M${x + quietZone} ${y + quietZone}h1v1h-1z`;
    }));
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" shape-rendering="crispEdges" role="img" aria-label="QR code de configuration"><rect width="${size}" height="${size}" fill="#fff"/><path d="${path}" fill="#0d1117"/></svg>`;
}

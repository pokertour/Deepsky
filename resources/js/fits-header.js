/**
 * Lecture des en-têtes FITS / XISF directement dans le navigateur : seuls les
 * premiers kilo-octets de chaque fichier sont lus, rien n'est envoyé au serveur.
 * NINA, ASIAIR, SharpCap, Ekos et PixInsight écrivent les mêmes mots-clés FITS.
 */
const FITS_CARD = 80;
const FITS_BLOCK = 2880;
const MAX_FITS_HEADER_BYTES = FITS_BLOCK * 40;

export function parseFitsHeader(text) {
    const keywords = {};
    for (let offset = 0; offset + FITS_CARD <= text.length; offset += FITS_CARD) {
        const card = text.slice(offset, offset + FITS_CARD);
        const key = card.slice(0, 8).trim();
        if (key === 'END') break;
        if (!key || card[8] !== '=') continue;
        keywords[key] = parseFitsValue(card.slice(10));
    }
    return keywords;
}

function parseFitsValue(raw) {
    const trimmed = raw.trim();
    if (trimmed.startsWith("'")) {
        const match = trimmed.match(/^'((?:[^']|'')*)'/);
        return match ? match[1].replace(/''/g, "'").trim() : '';
    }
    const value = trimmed.split('/')[0].trim();
    if (value === 'T') return true;
    if (value === 'F') return false;
    const number = Number(value);
    return value !== '' && Number.isFinite(number) ? number : value;
}

export function parseXisfHeader(xml) {
    const keywords = {};
    for (const match of xml.matchAll(/<FITSKeyword\s+([^>]*?)\/?>/g)) {
        const attributes = Object.fromEntries([...match[1].matchAll(/(\w+)="([^"]*)"/g)].map(([, name, value]) => [name, value]));
        if (!attributes.name || attributes.name in keywords) continue;
        keywords[attributes.name] = parseFitsValue(attributes.value ?? '');
    }
    return keywords;
}

export async function readHeader(file) {
    if (/\.xisf$/i.test(file.name)) {
        const prefix = new DataView(await file.slice(0, 16).arrayBuffer());
        const signature = new TextDecoder().decode(new Uint8Array(prefix.buffer, 0, 8));
        if (signature !== 'XISF0100') throw new Error(`${file.name} : fichier XISF illisible.`);
        const length = prefix.getUint32(8, true);
        return parseXisfHeader(await file.slice(16, 16 + length).text());
    }
    const text = await file.slice(0, MAX_FITS_HEADER_BYTES).text();
    if (!text.startsWith('SIMPLE')) throw new Error(t(':file : ce n’est pas un fichier FITS.', {file: file.name}));
    return parseFitsHeader(text);
}

function frameType(imageType) {
    const value = String(imageType ?? 'light').toLowerCase();
    if (value.includes('flat') && value.includes('dark')) return 'dark';
    if (value.includes('dark')) return 'dark';
    if (value.includes('flat')) return 'flat';
    if (value.includes('bias') || value.includes('offset')) return 'bias';
    return 'light';
}

function captureDate(header) {
    const raw = header['DATE-LOC'] ?? header['DATE-OBS'];
    if (!raw) return null;
    const isLocal = 'DATE-LOC' in header;
    const date = new Date(isLocal ? String(raw).replace(/Z$/, '') : `${String(raw).replace(/Z$/, '')}Z`);
    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Nuit d'observation : une pose prise à 2 h du matin appartient à la soirée précédente.
 */
function nightOf(date) {
    const shifted = new Date(date.getTime() - 12 * 3600 * 1000);
    return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, '0')}-${String(shifted.getDate()).padStart(2, '0')}`;
}

const round = (value, digits = 0) => Math.round(value * 10 ** digits) / 10 ** digits;
const mostFrequent = (values) => {
    const counts = new Map();
    values.filter((value) => value !== undefined && value !== null && value !== '').forEach((value) => counts.set(value, (counts.get(value) ?? 0) + 1));
    return [...counts.entries()].sort((a, b) => b[1] - a[1])[0]?.[0] ?? null;
};

export function summarizeHeaders(headers) {
    const frames = headers.map((header) => ({
        header,
        type: frameType(header.IMAGETYP ?? header.FRAMETYP),
        filter: String(header.FILTER ?? '').trim(),
        seconds: Number(header.EXPTIME ?? header.EXPOSURE) || 0,
        date: captureDate(header),
        color: 'BAYERPAT' in header || (!('FILTER' in header) && !header.INSTRUME?.toString().toLowerCase().includes('mono')),
    }));
    const lights = frames.filter((frame) => frame.type === 'light');
    const datedLights = lights.filter((frame) => frame.date);

    const sessions = new Map();
    for (const frame of frames) {
        const night = frame.date ? nightOf(frame.date) : (datedLights[0] ? nightOf(datedLights[0].date) : '');
        const session = sessions.get(night) ?? {date: night, camera_mode: 'color', lots: new Map(), colorVotes: []};
        session.colorVotes.push(frame.color);
        const lotKey = `${frame.type}|${frame.filter}|${frame.seconds}`;
        const lot = session.lots.get(lotKey) ?? {type: frame.type, filter: frame.filter, count: 0, seconds: frame.seconds || ''};
        lot.count++;
        session.lots.set(lotKey, lot);
        sessions.set(night, session);
    }

    const order = {light: 0, flat: 1, dark: 2, bias: 3};
    const temperatures = lights.map((frame) => Number(frame.header['CCD-TEMP'] ?? frame.header['SET-TEMP'])).filter(Number.isFinite);
    const filters = [...new Set(lights.map((frame) => frame.filter).filter(Boolean))];

    return {
        sessions: [...sessions.values()]
            .sort((a, b) => a.date.localeCompare(b.date))
            .map((session) => ({
                date: session.date,
                camera_mode: session.colorVotes.filter(Boolean).length >= session.colorVotes.length / 2 ? 'color' : 'mono',
                frames: [...session.lots.values()].sort((a, b) => order[a.type] - order[b.type] || a.filter.localeCompare(b.filter)),
            })),
        lightCount: lights.length,
        calibrationCount: frames.length - lights.length,
        firstNight: datedLights.length ? nightOf(new Date(Math.min(...datedLights.map((frame) => frame.date.getTime())))) : null,
        target: mostFrequent(lights.map((frame) => String(frame.header.OBJECT ?? '').trim())),
        gain: mostFrequent(lights.map((frame) => frame.header.GAIN)),
        offset: mostFrequent(lights.map((frame) => frame.header.OFFSET ?? frame.header.BLKLEVEL)),
        sensorTemp: temperatures.length ? `${round(temperatures.reduce((sum, value) => sum + value, 0) / temperatures.length)}°C` : null,
        focalLength: mostFrequent(lights.map((frame) => frame.header.FOCALLEN)),
        filters: filters.join(' / '),
    };
}

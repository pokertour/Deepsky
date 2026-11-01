export const horizonX = (azimuth) => 40 + Number(azimuth) * 2;
export const horizonY = (altitude) => 204 - Number(altitude) * 2;

export function horizonAltitude(points, azimuth) {
    if (!points.length) return 0;
    const sorted = [...points].sort((a, b) => a.azimuth - b.azimuth);
    const first = sorted[0];
    const normalized = ((azimuth % 360) + 360) % 360;
    const bearing = normalized < first.azimuth ? normalized + 360 : normalized;
    sorted.push({...first, azimuth: first.azimuth + 360});
    let previous = first;
    for (const point of sorted) {
        if (point.azimuth >= bearing) {
            const span = point.azimuth - previous.azimuth;
            return span <= 0 ? point.altitude : previous.altitude + (point.altitude - previous.altitude) * (bearing - previous.azimuth) / span;
        }
        previous = point;
    }
    return first.altitude;
}

export function horizonLine(points) {
    const vertices = [0, ...points.map(point => Number(point.azimuth)).filter(azimuth => azimuth > 0 && azimuth < 360), 360].sort((a, b) => a - b);
    return vertices.map(azimuth => `${horizonX(azimuth)},${horizonY(horizonAltitude(points, azimuth))}`).join(' ');
}

export function trajectorySegments(samples) {
    const segments = [];
    samples.slice(1).forEach((end, index) => {
        const start = samples[index];
        const delta = end.azimuth - start.azimuth;
        const status = start.altitude < start.horizon ? 'blocked' : (start.visible ? 'visible' : 'low');
        const append = (a, b) => segments.push({points: `${horizonX(a.azimuth)},${horizonY(a.altitude)} ${horizonX(b.azimuth)},${horizonY(b.altitude)}`, status});
        if (Math.abs(delta) <= 180) { append(start, end); return; }
        const unwrappedEnd = end.azimuth + (delta < 0 ? 360 : -360);
        const edge = delta < 0 ? 360 : 0;
        const ratio = (edge - start.azimuth) / (unwrappedEnd - start.azimuth);
        const altitude = start.altitude + (end.altitude - start.altitude) * ratio;
        append(start, {azimuth: edge, altitude});
        append({azimuth: edge === 360 ? 0 : 360, altitude}, end);
    });
    return segments;
}

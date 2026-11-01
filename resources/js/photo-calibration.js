const radians = degrees => degrees * Math.PI / 180;
const degrees = angle => angle * 180 / Math.PI;
export const normalizeBearing = bearing => ((bearing % 360) + 360) % 360;

export function compassBearing(origin, target) {
    const lat1 = radians(origin[0]);
    const lat2 = radians(target[0]);
    const delta = radians(target[1] - origin[1]);
    const east = Math.sin(delta) * Math.cos(lat2);
    const north = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(delta);
    if (Math.hypot(east, north) < 1e-12) return null;
    return normalizeBearing(degrees(Math.atan2(east, north)));
}

export function photoDirection(x, y, {kind, horizontal, vertical, horizon, aspect, bearing}) {
    if (kind === 'panorama') return {azimuth: normalizeBearing(bearing + x * 360), altitude: (horizon / 100 - y) * vertical};
    const scale = 2 * Math.tan(radians(horizontal / 2));
    const right = (x - 0.5) * scale;
    const up = (0.5 - y) * scale / aspect;
    const pitch = Math.atan((horizon / 100 - 0.5) * scale / aspect);
    const worldUp = up * Math.cos(pitch) + Math.sin(pitch);
    const forward = Math.cos(pitch) - up * Math.sin(pitch);
    return {
        azimuth: normalizeBearing(bearing + degrees(Math.atan2(right, forward))),
        altitude: degrees(Math.atan2(worldUp, Math.hypot(right, forward))),
    };
}

export function alignPhotoToReference(x, y, calibration, referenceBearing) {
    const offset = photoDirection(x, y, {...calibration, bearing: 0}).azimuth;
    return normalizeBearing(referenceBearing - offset);
}

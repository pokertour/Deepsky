import {compassBearing} from './photo-calibration.js';
import { t } from './i18n.js';

export function registerObservingLocationMap(Alpine) {
    Alpine.data('observingLocationMap', () => {
        let map;
        let marker;
        let leaflet;
        let requestId = 0;
        let referenceLayers = [];

        return {
            status: '',
            mapError: '',
            locating: false,
            referenceMode: false,

            async init() {
                try {
                    leaflet = (await import('leaflet')).default;
                    await import('leaflet/dist/leaflet.css');
                    const position = this.coordinates();
                    map = leaflet.map(this.$refs.map, {scrollWheelZoom: false}).setView(position ?? [47.2184, -1.5536], position ? 15 : 6);
                    leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }).on('tileerror', () => { this.mapError = t('Fond de carte indisponible. La saisie des coordonnées reste possible.'); }).addTo(map);
                    if (position) this.moveMarker(position);
                    map.on('click', (event) => this.referenceMode ? this.chooseReference(event.latlng.lat, event.latlng.lng) : this.choose(event.latlng.lat, event.latlng.lng));
                } catch {
                    this.mapError = t('La carte ne peut pas être chargée. Renseigne les coordonnées ci-dessous.');
                }
            },

            coordinates() {
                const lat = this.$root.querySelector('[name="latitude"]').value;
                const lng = this.$root.querySelector('[name="longitude"]').value;
                if (lat === '' || lng === '') return null;
                const position = [Number(lat), Number(lng)];
                return position.every(Number.isFinite) && Math.abs(position[0]) <= 90 && Math.abs(position[1]) <= 180 ? position : null;
            },

            moveMarker(position) {
                if (!map) return;
                if (marker) marker.setLatLng(position);
                else {
                    marker = leaflet.marker(position, {
                        draggable: true,
                        icon: leaflet.divIcon({
                            className: 'observing-map-marker',
                            html: '<span class="block size-5 rounded-full border-4 border-white bg-sky-500 shadow-lg"></span>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10],
                        }),
                    }).addTo(map);
                    marker.on('dragend', () => {
                        const point = marker.getLatLng();
                        this.choose(point.lat, point.lng);
                    });
                }
            },

            choose(lat, lng) {
                const normalizedLng = ((lng + 180) % 360 + 360) % 360 - 180;
                this.$root.querySelector('[name="latitude"]').value = Math.max(-90, Math.min(90, lat)).toFixed(7);
                this.$root.querySelector('[name="longitude"]').value = normalizedLng.toFixed(7);
                this.syncFromFields();
            },

            syncFromFields() {
                this.clearReference();
                const position = this.coordinates();
                if (!position) { requestId++; this.status = t('Vérifie les coordonnées avant de rechercher l’altitude.'); return; }
                this.moveMarker(position);
                map?.panTo(position);
                this.fetchElevation(position);
            },

            clearReference() {
                referenceLayers.forEach(layer => layer.remove()); referenceLayers = [];
                this.referenceMode = false;
                this.$dispatch('horizon-reference-picked', {bearing: null});
            },

            startReference() {
                if (!map || !this.coordinates()) { this.status = t('Renseigne d’abord la position du télescope.'); return; }
                this.referenceMode = true;
                this.status = t('Appuie sur un bâtiment ou un repère visible dans la photo. La position du lieu restera inchangée.');
                this.$refs.map.scrollIntoView({behavior: 'smooth', block: 'center'});
            },

            chooseReference(lat, lng) {
                const origin = this.coordinates();
                if (!origin) return;
                const target = [lat, ((lng + 180) % 360 + 360) % 360 - 180];
                const bearing = compassBearing(origin, target);
                if (bearing === null) { this.status = t('Choisis un repère distinct de la position du télescope.'); return; }
                referenceLayers.forEach(layer => layer.remove());
                referenceLayers = [
                    leaflet.circleMarker(target, {radius: 8, color: '#f0c674', fillOpacity: 0.8}).addTo(map),
                    leaflet.polyline([origin, target], {color: '#f0c674', dashArray: '5 5'}).addTo(map),
                ];
                this.referenceMode = false;
                this.status = t('Repère choisi : :bearing° depuis le nord géographique. Désigne maintenant ce même repère dans l’image.', {bearing: bearing.toFixed(1)});
                this.$dispatch('horizon-reference-picked', {bearing});
            },

            async fetchElevation(position = this.coordinates()) {
                if (!position) { this.status = t('Choisis d’abord un point sur la carte.'); return; }
                const currentRequest = ++requestId;
                const altitude = this.$root.querySelector('[name="altitude"]');
                const initialAltitude = altitude.value;
                this.status = t('Recherche de l’altitude…');
                try {
                    const query = new URLSearchParams({latitude: position[0], longitude: position[1]});
                    const response = await fetch(`${this.$root.dataset.elevationUrl}?${query}`, {headers: {Accept: 'application/json'}, cache: 'no-store'});
                    const data = await response.json();
                    if (currentRequest !== requestId) return;
                    if (!response.ok || !Number.isFinite(data.elevation)) throw new Error();
                    if (altitude.value === initialAltitude) {
                        altitude.value = Math.round(data.elevation * 10) / 10;
                        this.status = t('Altitude du terrain proposée par Open-Meteo. Tu peux la corriger manuellement.');
                    } else this.status = t('Altitude trouvée, mais ta correction manuelle a été conservée.');
                } catch {
                    if (currentRequest === requestId) this.status = t('Altitude indisponible. Tu peux la renseigner manuellement.');
                }
            },

            locate() {
                if (!navigator.geolocation) { this.status = t('La géolocalisation est indisponible sur cet appareil.'); return; }
                this.locating = true;
                this.status = t('Recherche de ta position…');
                navigator.geolocation.getCurrentPosition((position) => {
                    this.locating = false;
                    this.choose(position.coords.latitude, position.coords.longitude);
                    map?.setView(this.coordinates(), 16);
                }, () => {
                    this.locating = false;
                    this.status = t('Position indisponible ou accès refusé. Choisis le lieu sur la carte.');
                }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 60000});
            },

            destroy() { requestId++; map?.remove(); },
        };
    });
}

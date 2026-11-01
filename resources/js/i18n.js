import en from './lang/en.json' with { type: 'json' };

const dictionaries = { en };

/** Langue de la page (attribut lang de <html>), « fr » par défaut. */
export function locale() {
    return (globalThis.document?.documentElement.lang || 'fr').slice(0, 2);
}

/** Locale Intl pour les dates et nombres : fr-FR ou en-GB (format jour/mois, 24 h). */
export function intlLocale() {
    return locale() === 'en' ? 'en-GB' : 'fr-FR';
}

/** Direction cardinale sous forme de complément : « au nord », « à l’est »… */
export function compassPhrase(azimuth) {
    return t(['au nord', 'au nord-est', 'à l’est', 'au sud-est', 'au sud', 'au sud-ouest', 'à l’ouest', 'au nord-ouest'][Math.round(azimuth / 45) % 8]);
}

/**
 * Traduit un texte source français, comme __() côté Laravel :
 * t('Il reste :count codes', { count: 3 }).
 */
export function t(text, replacements = {}) {
    let translated = dictionaries[locale()]?.[text] ?? text;

    for (const [key, value] of Object.entries(replacements)) {
        translated = translated.replaceAll(`:${key}`, String(value));
    }

    return translated;
}

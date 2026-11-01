<?php

return [
    'elevation' => [
        'url' => env('NIGHTSCOPE_ELEVATION_URL', 'https://api.open-meteo.com/v1/elevation'),
        'connect_timeout' => 3,
        'timeout' => 8,
        'cache_days' => 30,
    ],
    'preview' => [
        'endpoint' => 'https://alasky.cds.unistra.fr/hips-image-services/hips2fits',
        'endpoints' => [
            'https://alasky.cds.unistra.fr/hips-image-services/hips2fits',
            'https://alaskybis.cds.unistra.fr/hips-image-services/hips2fits',
        ],
        'survey' => 'CDS/P/DSS2/color',
        // Secours si le CDS ne répond pas : DSS2 via NASA SkyView, recomposé en couleur.
        'skyview_endpoint' => 'https://skyview.gsfc.nasa.gov/current/cgi/runquery.pl',
        'cds_retry_minutes' => 10,
        'minimum_fov_deg' => 0.5,
        'maximum_fov_deg' => 4.0,
        'width' => 640,
        'height' => 360,
        'cache_days' => 14,
        'timeout' => 12,
        'connect_timeout' => 4,
        'user_agent' => env('NIGHTSCOPE_PREVIEW_USER_AGENT', 'DeepskyNightScope/1.0'),
    ],

    'weather' => [
        'driver' => env('NIGHTSCOPE_WEATHER_DRIVER', 'open_meteo'),
        'cache_minutes' => 45,
        'connect_timeout' => 5,
        'timeout' => 15,
        'open_meteo' => [
            'endpoint' => 'https://api.open-meteo.com',
        ],
        'seven_timer' => [
            'enabled' => env('NIGHTSCOPE_SEVEN_TIMER_ENABLED', true),
            'endpoint' => env('NIGHTSCOPE_SEVEN_TIMER_ENDPOINT', 'https://www.7timer.info'),
            'match_minutes' => 120,
        ],
    ],

    'scores' => [
        'night' => [
            'darkness_without_weather' => 0.60,
            'moon_without_weather' => 0.40,
            'darkness' => 0.35,
            'moon' => 0.20,
            'weather' => 0.45,
        ],
        /*
         | Target score weights are renormalized over available components.
         | Threshold notes (starting calibration, adjust after real nights):
         | - useful altitude window uses >= 30°
         | - large nebula focal preference kicks in at >= 60′
         | - history treats a target as “fresh” again after fresh_after_days
         | - integration soft-caps around integration_soft_cap_hours
         | Seeing/transparency are intentionally absent until a specialized weather provider exists.
         */
        'target' => [
            'visibility' => 0.24,
            'altitude' => 0.14,
            'moon' => 0.12,
            'framing' => 0.12,
            'equipment' => 0.14,
            'history' => 0.10,
            'weather' => 0.14,
            'interest' => 0.22,
            // Bonus « jamais photographié », proportionnel à l'intérêt de l'objet.
            'never_photographed_bonus' => 12,
        ],
        /*
         | Intérêt photographique (InterestScoreCalculator), renormalisé quand
         | la magnitude manque (fréquent pour les nébuleuses étendues).
         */
        'altitude_full_score_deg' => 60,
        'interest' => [
            'notoriety' => 0.45,
            'brightness' => 0.30,
            'size' => 0.25,
        ],
        'equipment' => [
            'focal' => 0.30,
            'sampling' => 0.25,
            'filter' => 0.25,
            'field' => 0.20,
        ],
        'history' => [
            'recency' => 0.40,
            'integration' => 0.35,
            'diversity' => 0.25,
            'fresh_after_days' => 180,
            'integration_soft_cap_hours' => 10.0,
        ],
    ],

    'planner' => [
        'minimum_slot_minutes' => 45,
        'maximum_slot_minutes' => 120,
        'transition_minutes' => 10,
        'maximum_targets' => 4,
        'minimum_altitude_deg' => 30,
        'minimum_moon_separation_deg' => 30,
        'visibility_sample_minutes' => 10,
    ],

    'tonight' => [
        'candidate_limit' => 250,
        'recommendation_limit' => 12,
        'recommendation_step' => 12,
        'recommendation_max' => 48,
        'default_types' => ['galaxy', 'nebula', 'cluster'],
    ],

    'distances' => [
        'simbad_tap_url' => env('NIGHTSCOPE_SIMBAD_TAP_URL', 'https://simbad.cds.unistra.fr/simbad/sim-tap/sync'),
        // Miroirs interrogés si le serveur de Strasbourg ne répond pas.
        'simbad_tap_mirrors' => ['https://simbad.harvard.edu/simbad/sim-tap/sync'],
        'cache_days' => 30,
        'connect_timeout' => 5,
        'timeout' => 20,
        'enrich_batch' => 150,
        'recheck_days' => 30,
    ],

    /*
     | Sub-exposure heuristic scales base seconds by (f-ratio / 5)^2, then snaps
     | to a common step list. Gain prefers camera specs, then Rig history median.
     */
    'acquisition' => [
        'default_cooled_gain' => 100,
        'default_uncooled_gain' => 0,
        'base_broadband_seconds' => 120,
        'base_dual_band_seconds' => 180,
        'base_narrowband_seconds' => 300,
        'minimum_sub_exposure_seconds' => 30,
        'maximum_sub_exposure_seconds' => 600,
    ],

    'filters' => [
        'moonlit_illumination_percent' => 40,
        'moonlit_separation_deg' => 50,
        'light_polluted_bortle' => 5,
    ],

    'framing' => [
        'excellent_threshold' => 0.65,
        'good_threshold' => 0.30,
        'mosaic_threshold' => 1.20,
        'mosaic_overlap' => 0.20,
        'similar_focal_length_tolerance' => 0.25,
    ],

    'catalog' => [
        'version' => 'v20260501',
        'openngc_url' => env(
            'OPENNGC_URL',
            'https://raw.githubusercontent.com/mattiaverga/OpenNGC/refs/tags/v20260501/database_files/NGC.csv'
        ),
        'addendum_url' => env(
            'OPENNGC_ADDENDUM_URL',
            'https://raw.githubusercontent.com/mattiaverga/OpenNGC/refs/tags/v20260501/database_files/addendum.csv'
        ),
        'release_api_url' => 'https://api.github.com/repos/mattiaverga/OpenNGC/releases/latest',
        'release_download_url' => 'https://raw.githubusercontent.com/mattiaverga/OpenNGC/refs/tags/{version}/database_files/NGC.csv',
        'addendum_download_url' => 'https://raw.githubusercontent.com/mattiaverga/OpenNGC/refs/tags/{version}/database_files/addendum.csv',
    ],

    'notifications' => [
        'timezone' => env('NIGHTSCOPE_NOTIFICATIONS_TIMEZONE', 'Europe/Paris'),
    ],

    'commons' => [
        'endpoint' => 'https://commons.wikimedia.org/w/api.php',
        'cache_hours' => 24,
        'timeout' => 12,
        'connect_timeout' => 5,
        'user_agent' => env('NIGHTSCOPE_COMMONS_USER_AGENT', 'DeepskyNightScope/1.0 (nightscope; local)'),
    ],

    /*
     | Ciel en direct (page de connexion et observatoire public) : position
     | publique (centre-ville), jamais celle d'un lieu d'observation privé.
     */
    'live_sky' => [
        'label' => env('LIVE_SKY_LABEL', 'Nantes'),
        'latitude' => (float) env('LIVE_SKY_LATITUDE', 47.218),
        'longitude' => (float) env('LIVE_SKY_LONGITUDE', -1.554),
        'timezone' => env('LIVE_SKY_TIMEZONE', 'Europe/Paris'),
    ],
];

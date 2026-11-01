/* Ciel étoilé animé, apparitions au défilement et bascule FR / EN. */
(() => {
    const en = {
        'nav.public': 'The site', 'nav.nightscope': 'NightScope', 'nav.security': 'Security', 'nav.install': 'Install',
        'hero.eyebrow': 'Self-hosted · Open source · FR / EN',
        'hero.title': 'Your deep-sky photos,<br><em>with the sky around them.</em>',
        'hero.lead': 'Deepsky is an astrophotography portfolio that thinks like an observatory: every image is plate-solved, placed in the sky and dated by the age of its light. Behind the scenes, NightScope plans your imaging nights.',
        'hero.live': 'See the live site ↗', 'hero.install': 'Install with Docker',
        'alt.gallery': 'Deepsky gallery',
        'public.kicker': '01 · The public site', 'public.title': 'A gallery that tells the night behind every image',
        'f.gallery.title': 'Gallery and photo pages',
        'f.gallery.text': 'Filter by object type, constellation, year and equipment. Each page details the acquisition sessions, filters, equipment, starless version and astrometric annotations: objects in the field are identified automatically by Astrometry.net.',
        'f.gallery.1': 'Plate solving and field annotations', 'f.gallery.2': 'SIMBAD distances for the target and the whole field',
        'f.gallery.3': 'Compare versions of the same target', 'f.gallery.4': 'Full resolution, sharing and likes',
        'f.obs.title': 'Observatory: the live sky',
        'f.obs.text': 'A planetarium computed in the browser: stars, constellations, the Milky Way, planets and the Moon exactly where they are above your site. The objects you have photographed are circled; one click opens the photo. Zoom, pan and a slider to move through the night.',
        'f.obs.1': 'Full darkness, Moon phase and rise, visible planets', 'f.obs.2': 'Logbook: calendar of acquisition nights', 'f.obs.3': 'Integration by filter, target and optical tube',
        'c.ar.title': 'Look up', 'c.ar.text': 'Augmented reality on mobile: point your phone at the sky and Deepsky draws constellations, planets and portfolio objects where they really are, with an arrow guiding you to the chosen target.',
        'c.dive.title': 'The Dive', 'c.dive.text': 'From a photo page, an animation starts from the whole sky and dives into the image’s field. Each identified object tells how long its light has been travelling.',
        'c.time.title': 'Back in time', 'c.time.text': 'All photos placed on a timeline by the age of their light, alongside the history of the Earth: from the Roman Empire to the first dinosaurs.',
        'c.news.title': 'Newsletter', 'c.news.text': 'One email per publication, with double opt-in, captcha (Turnstile or hCaptcha) and one-click unsubscribe. Sent in the subscriber’s language.',
        'ns.kicker': '02 · NightScope', 'ns.title': 'The cockpit to plan, shoot and publish',
        'ns.lead': 'The admin area is built for the field: usable on a phone, red night mode, plans available offline.',
        'f.tonight.title': 'Tonight: what should I shoot?',
        'f.tonight.text': 'A score for each target combining altitude during astronomical night, the Moon, the weather, framing with your rig and how interesting the object is. With your garden’s horizon mask, trees and roofs are taken into account.',
        'f.tonight.1': '7-night astro forecast (seeing, transparency, clouds)', 'f.tonight.2': 'Filter advice based on the sky, the Moon and the Bortle class',
        'f.tonight.3': 'Framing, rotation and mosaic grid', 'f.tonight.4': 'Night Planner, favourites and imaging sessions',
        'c.fits.title': 'FITS / XISF import', 'c.fits.text': 'Drop your lights, flats and darks: headers are read in the browser (nothing is uploaded) and fill in sessions, exposures, filters, gain and temperature.',
        'c.pub.title': 'Scheduled publication', 'c.pub.text': 'Draft, image conversion tracking, then publish by hand or at a chosen time, newsletter included.',
        'c.ai.title': 'Gemini texts and translations', 'c.ai.text': 'An accessible presentation of the object written from the catalogue, and the English version of your texts in one click.',
        'c.tasks.title': 'Automated tasks', 'c.tasks.text': 'Processing history, one-click reruns, recovery of stuck conversions, purges and Telegram, Gotify, ntfy or email alerts on failure.',
        'sec.kicker': '03 · Security', 'sec.title': 'A well-guarded admin area',
        'sec.1': 'TOTP two-factor authentication and recovery codes', 'sec.2': 'Lockout after 10 wrong passwords, per-IP rate limiting',
        'sec.3': 'Sign-in log and alerts on every sign-in', 'sec.4': 'Remote sign-out of other sessions',
        'sec.5': 'CSP, HSTS, anti-clickjacking, trusted hosts and proxies', 'sec.6': 'Secrets encrypted in the database, garden coordinates never published',
        'inst.kicker': '04 · Install', 'inst.title': 'Your own Deepsky in a few minutes',
        'inst.lead': 'A Docker image is published to the GitHub Container Registry for every release. You need Docker Compose, an S3-compatible bucket (Cloudflare R2, MinIO…) for the images, and a domain name behind a reverse proxy.',
        'inst.1': 'Get the configuration', 'inst.2': 'Fill in the .env', 'inst.3': 'Start',
        'env.url': 'Public address of the site', 'env.key': 'Key generated with php artisan key:generate --show', 'env.db': 'MariaDB password',
        'env.admin': 'Email address(es) of the admin account(s)', 'env.s3': 'S3 / R2 bucket that stores the images',
        'env.proxy': 'Reverse proxy address (or * on a private network)', 'env.locale': 'Default language: fr or en',
        'inst.3text': 'Migrations, caches and the queue start with the container, which exposes a health check. A daily database backup is written to ./backups.',
        'inst.releases': 'Release notes', 'inst.readme': 'Full documentation',
        'footer.text': 'Made with patience under the skies of Nantes, one 300 s exposure at a time.<br>Code under the MIT licence · Photos © pokertour, all rights reserved · <a href="https://github.com/pokertour">pokertour</a>',
    };
    const fr = {};
    const texts = document.querySelectorAll('[data-i18n]');
    const htmls = document.querySelectorAll('[data-i18n-html]');
    const alts = document.querySelectorAll('[data-i18n-alt]');
    texts.forEach((el) => { fr[el.dataset.i18n] = el.textContent; });
    htmls.forEach((el) => { fr[el.dataset.i18nHtml] = el.innerHTML; });
    alts.forEach((el) => { fr[el.dataset.i18nAlt] = el.alt; });
    const meta = { fr: { title: document.title }, en: { title: 'Deepsky — Astrophotography portfolio and personal observatory' } };

    const apply = (lang) => {
        const dict = lang === 'en' ? en : fr;
        texts.forEach((el) => { el.textContent = dict[el.dataset.i18n] ?? fr[el.dataset.i18n]; });
        htmls.forEach((el) => { el.innerHTML = dict[el.dataset.i18nHtml] ?? fr[el.dataset.i18nHtml]; });
        alts.forEach((el) => { el.alt = dict[el.dataset.i18nAlt] ?? fr[el.dataset.i18nAlt]; });
        document.documentElement.lang = lang;
        document.title = meta[lang].title;
        document.querySelectorAll('[data-lang]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.lang === lang)));
        try { localStorage.setItem('deepsky-pages-lang', lang); } catch { /* navigation privée */ }
    };

    const stored = (() => { try { return localStorage.getItem('deepsky-pages-lang'); } catch { return null; } })();
    const requested = new URLSearchParams(location.search).get('lang');
    const initial = ['fr', 'en'].includes(requested) ? requested
        : ['fr', 'en'].includes(stored) ? stored
        : (navigator.language || 'fr').toLowerCase().startsWith('fr') ? 'fr' : 'en';
    apply(initial);
    document.querySelectorAll('[data-lang]').forEach((button) => button.addEventListener('click', () => apply(button.dataset.lang)));

    /* Apparitions au défilement */
    const revealables = document.querySelectorAll('.feature, .card, .duo, .security, .steps > li, .frame.wide, .section > h2');
    revealables.forEach((el) => el.classList.add('reveal'));
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
    }), { rootMargin: '0px 0px -8% 0px' });
    revealables.forEach((el) => observer.observe(el));

    /* Ciel étoilé : étoiles scintillantes et étoile filante occasionnelle */
    const canvas = document.getElementById('sky');
    const context = canvas.getContext('2d');
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let stars = [];
    let meteor = null;
    const resize = () => {
        const ratio = Math.min(devicePixelRatio || 1, 2);
        canvas.width = innerWidth * ratio;
        canvas.height = innerHeight * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        const count = Math.round(innerWidth * innerHeight / 5200);
        stars = Array.from({ length: count }, () => ({
            x: Math.random() * innerWidth, y: Math.random() * innerHeight,
            r: Math.random() ** 3 * 1.4 + .25, phase: Math.random() * Math.PI * 2,
            speed: .4 + Math.random() * 1.2, hue: Math.random() < .15 ? 30 : Math.random() < .3 ? 215 : 0,
        }));
    };
    const draw = (time) => {
        context.clearRect(0, 0, innerWidth, innerHeight);
        for (const star of stars) {
            const alpha = .35 + .65 * (reduceMotion ? 1 : (Math.sin(time / 1000 * star.speed + star.phase) + 1) / 2);
            context.fillStyle = star.hue ? `hsla(${star.hue}, 80%, 85%, ${alpha})` : `rgba(255, 255, 255, ${alpha})`;
            context.beginPath(); context.arc(star.x, star.y, star.r, 0, Math.PI * 2); context.fill();
        }
        if (!reduceMotion) {
            if (!meteor && Math.random() < .003) meteor = { x: Math.random() * innerWidth, y: Math.random() * innerHeight * .4, life: 0 };
            if (meteor) {
                meteor.life += 1;
                const length = 90, progress = meteor.life * 9;
                const x = meteor.x + progress, y = meteor.y + progress * .45;
                const gradient = context.createLinearGradient(x, y, x - length, y - length * .45);
                gradient.addColorStop(0, `rgba(200, 225, 255, ${1 - meteor.life / 40})`);
                gradient.addColorStop(1, 'rgba(200, 225, 255, 0)');
                context.strokeStyle = gradient; context.lineWidth = 1.4;
                context.beginPath(); context.moveTo(x, y); context.lineTo(x - length, y - length * .45); context.stroke();
                if (meteor.life > 40) meteor = null;
            }
            requestAnimationFrame(draw);
        }
    };
    resize();
    addEventListener('resize', resize);
    requestAnimationFrame(draw);
})();

import {onPageLoad} from './page-lifecycle';

window.onNewsletterCaptchaVerified = () => {
    const form = document.getElementById('newsletter-form');
    if (form?.reportValidity()) {
        HTMLFormElement.prototype.submit.call(form);
    }
};

let captchaReady = false;

function renderNewsletterCaptcha() {
    if (!captchaReady) return;
    document.querySelectorAll('#newsletter-form .cf-turnstile, #newsletter-form .h-captcha').forEach((element) => {
        const turnstile = element.classList.contains('cf-turnstile');
        const provider = turnstile ? window.turnstile : window.hcaptcha;
        if (!provider || element.dataset.captchaWidget !== undefined) return;

        const options = {sitekey: element.dataset.sitekey, size: element.dataset.size};
        if (turnstile) {
            options.action = element.dataset.action;
            options.appearance = element.dataset.appearance;
            // Le défi n'est lancé qu'à l'envoi du formulaire : chaque jeton émis est
            // ensuite vérifié côté serveur (Siteverify), au lieu d'un jeton par page vue.
            options.execution = 'execute';
            options.callback = window.onNewsletterCaptchaVerified;
        } else {
            options.callback = window.onNewsletterCaptchaVerified;
        }
        element.dataset.captchaWidget = provider.render(element, options);
    });
}

window.onNewsletterCaptchaLoaded = () => {
    captchaReady = true;
    renderNewsletterCaptcha();
};

// Turnstile en mode « execute » : le premier envoi déclenche le défi, et son callback
// soumet le formulaire une fois le jeton obtenu.
function requestTurnstileToken(event) {
    const form = event.target;
    const widget = form.querySelector('.cf-turnstile[data-captcha-widget]');
    if (!widget || !window.turnstile) return;

    const token = form.querySelector('[name="cf-turnstile-response"]')?.value;
    if (token) return;

    event.preventDefault();
    if (form.reportValidity()) {
        window.turnstile.execute(widget);
    }
}

onPageLoad(() => {
    renderNewsletterCaptcha();
    const form = document.getElementById('newsletter-form');
    form?.addEventListener('submit', requestTurnstileToken);
    const newsletter = document.getElementById('newsletter');
    if (newsletter?.dataset.hasFeedback === 'true') {
        newsletter.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'center',
        });
        newsletter.querySelector('[role="alert"], [role="status"]')?.focus({preventScroll: true});
    }

    return () => {
        form?.removeEventListener('submit', requestTurnstileToken);
        document.querySelectorAll('#newsletter-form [data-captcha-widget]').forEach((element) => {
            const provider = element.classList.contains('cf-turnstile') ? window.turnstile : window.hcaptcha;
            provider?.remove(element.dataset.captchaWidget);
            delete element.dataset.captchaWidget;
        });
    };

});

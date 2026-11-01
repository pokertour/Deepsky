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

onPageLoad(() => {
    renderNewsletterCaptcha();
    const newsletter = document.getElementById('newsletter');
    if (newsletter?.dataset.hasFeedback === 'true') {
        newsletter.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'center',
        });
        newsletter.querySelector('[role="alert"], [role="status"]')?.focus({preventScroll: true});
    }

    return () => {
        document.querySelectorAll('#newsletter-form [data-captcha-widget]').forEach((element) => {
            const provider = element.classList.contains('cf-turnstile') ? window.turnstile : window.hcaptcha;
            provider?.remove(element.dataset.captchaWidget);
            delete element.dataset.captchaWidget;
        });
    };

});

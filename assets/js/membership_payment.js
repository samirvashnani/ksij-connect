(() => {
    'use strict';
    const form = document.querySelector('[data-membership-payment]');
    if (!form) return;
    const button = form.querySelector('button[type="submit"]');
    const initiallyDisabled = button.disabled;
    const progress = form.querySelector('[data-payment-progress]');
    let processing = false;
    let timer;
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (processing) return;
        processing = true;
        button.disabled = true;
        progress.hidden = false;
        form.setAttribute('aria-busy', 'true');
        // The receipt is rendered only after the server transaction succeeds.
        timer = window.setTimeout(() => HTMLFormElement.prototype.submit.call(form), 1200);
    });
    window.addEventListener('pageshow', () => {
        window.clearTimeout(timer);
        processing = false;
        button.disabled = initiallyDisabled;
        progress.hidden = true;
        form.removeAttribute('aria-busy');
    });
})();

(() => {
    'use strict';
    const form = document.querySelector('[data-member-access-form]');
    if (!form) return;
    const code = form.querySelector('[data-otp-code]');
    if (code) {
        const group = document.createElement('div');
        group.className = 'otp-digits';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', 'Six-digit verification code');
        const digits = Array.from({ length: 6 }, (_, index) => {
            const input = document.createElement('input');
            input.type = 'text';
            input.id = `otp-digit-${index + 1}`;
            input.inputMode = 'numeric';
            input.autocomplete = index === 0 ? 'one-time-code' : 'off';
            input.maxLength = index === 0 ? 6 : 1;
            input.pattern = '[0-9]';
            input.required = true;
            input.setAttribute('aria-label', `Digit ${index + 1} of 6`);
            input.setAttribute('aria-describedby', 'otp-note');
            group.appendChild(input);
            return input;
        });
        const sync = () => { code.value = digits.map(input => input.value).join(''); };
        const fill = (value, start) => {
            const characters = value.replace(/\D/g, '').slice(0, 6).split('');
            if (characters.length === 6) start = 0;
            characters.slice(0, 6 - start).forEach((character, offset) => { digits[start + offset].value = character; });
            sync();
            digits[Math.min(5, start + characters.length)].focus();
        };
        digits.forEach((input, index) => {
            input.addEventListener('focus', () => input.select());
            input.addEventListener('input', () => {
                const value = input.value.replace(/\D/g, '');
                input.value = value.slice(0, 1);
                if (value) fill(value, index);
                else sync();
            });
            input.addEventListener('paste', event => {
                event.preventDefault();
                fill(event.clipboardData.getData('text'), index);
            });
            input.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && !input.value && index > 0) {
                    event.preventDefault();
                    digits[index - 1].value = '';
                    sync();
                    digits[index - 1].focus();
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    digits[Math.max(0, Math.min(5, index + (event.key === 'ArrowLeft' ? -1 : 1)))].focus();
                }
            });
        });
        code.after(group);
        form.querySelector('label[for="otp"]').htmlFor = digits[0].id;
        code.hidden = true;
        code.type = 'hidden';
        code.required = false;
        code.autocomplete = 'off';
        if (code.value) fill(code.value, 0);
        form.addEventListener('submit', sync);
        form.addEventListener('reset', () => { digits.forEach(input => { input.value = ''; }); code.value = ''; });
    }
    const submit = form.querySelector('[type="submit"]');
    form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        submit.disabled = true;
    });
    window.addEventListener('pageshow', () => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        submit.disabled = false;
    });
})();

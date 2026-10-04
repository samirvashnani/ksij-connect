(() => {
    'use strict';
    const root = document.querySelector('[data-donation-ledger]');
    if (!root) return;
    const form = root.querySelector('[data-ledger-filter]');
    const results = root.querySelector('[data-ledger-results]');
    const feedback = root.querySelector('[data-ledger-feedback]');
    let controller;
    let timer;
    async function refresh(page = 1, focus = false) {
        clearTimeout(timer);
        controller?.abort();
        const request = new AbortController();
        controller = request;
        const timeout = setTimeout(() => request.abort(), 30000);
        const url = new URL(form.getAttribute('action'), location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        url.searchParams.set('page', page);
        results.setAttribute('aria-busy', 'true');
        feedback.textContent = 'Loading payments...';
        try {
            const response = await fetch(url, { credentials: 'same-origin', signal: request.signal });
            if (response.redirected || response.status === 403) throw new Error('Your session changed. Reload to sign in again.');
            if (!response.ok) throw new Error('Payments could not be loaded. Please try again.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (controller !== request) return;
            const next = doc.querySelector('[data-ledger-results]');
            if (!next) throw new Error('The ledger could not be loaded. Reload the page.');
            if (next.hasAttribute('data-load-error')) throw new Error(next.querySelector('.error')?.textContent || 'Check the selected filters.');
            results.replaceChildren(...next.childNodes);
            results.dataset.page = next.dataset.page;
            results.removeAttribute('data-load-error');
            url.searchParams.set('page', next.dataset.page);
            history.replaceState(null, '', url);
            const extra = form.querySelector('.ledger-extra-filters');
            const active = ['mode','category','from','to'].some(key => form.elements.namedItem(key).value !== '') || form.elements.namedItem('scope').value !== 'all';
            extra.querySelector('summary').textContent = active ? 'More filters (active)' : 'More filters';
            feedback.textContent = results.querySelector('[data-ledger-count]').textContent;
            if (focus) { results.tabIndex = -1; results.focus(); }
        } catch (error) {
            if (controller === request) feedback.textContent = request.signal.aborted ? 'Loading timed out. Please try again.' : error.message;
        } finally {
            clearTimeout(timeout);
            if (controller === request) { controller = null; results.removeAttribute('aria-busy'); }
        }
    }
    form.addEventListener('submit', event => { event.preventDefault(); refresh(); });
    form.addEventListener('change', event => { if (event.target.matches('select,input[type="date"]')) refresh(); });
    form.addEventListener('input', event => {
        if (!event.target.matches('input[type="search"]')) return;
        controller?.abort(); controller = null;
        clearTimeout(timer);
        timer = setTimeout(() => refresh(), 350);
    });
    results.addEventListener('click', event => {
        const link = event.target.closest('[data-ledger-page]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault(); refresh(new URL(link.href).searchParams.get('page'), true);
    });
})();

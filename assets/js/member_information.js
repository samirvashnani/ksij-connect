(() => {
    'use strict';
    const root = document.querySelector('[data-information]');
    if (!root) return;
    const form = root.querySelector('[data-information-filter]');
    const results = root.querySelector('[data-information-results]');
    const status = root.querySelector('[data-information-status]');
    let controller;
    let timer;
    root.addEventListener('toggle', event => {
        if (!event.target.matches('.information-expand')) return;
        event.target.querySelector('.information-read-more').textContent = event.target.open ? 'Show fewer details' : 'Read full details';
    }, true);
    async function refresh(url, paginate = false) {
        clearTimeout(timer);
        controller?.abort();
        const request = new AbortController();
        controller = request;
        const timeout = setTimeout(() => request.abort(), 30000);
        results.setAttribute('aria-busy', 'true');
        status.textContent = 'Loading...';
        try {
            const response = await fetch(url, { credentials: 'same-origin', signal: request.signal });
            if (response.redirected) throw new Error('Your session changed. Reload to sign in again.');
            if (!response.ok) throw new Error('Could not load records. Please try again.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.querySelector('[data-information-results]');
            if (!next) throw new Error('Could not load records. Please reload the page.');
            if (controller !== request) return;
            results.replaceChildren(...next.childNodes);
            history.replaceState(null, '', url);
            status.textContent = results.querySelector('.error')?.textContent || results.querySelector('[data-information-count]')?.textContent || 'Updated.';
            if (paginate) { results.tabIndex = -1; results.focus(); }
        } catch (error) {
            if (controller === request) status.textContent = request.signal.aborted ? 'Loading timed out. Please try again.' : error.message;
        } finally {
            clearTimeout(timeout);
            if (controller === request) results.removeAttribute('aria-busy');
        }
    }
    function search() {
        const url = new URL(form.getAttribute('action'), location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        refresh(url);
    }
    form.addEventListener('submit', event => { event.preventDefault(); search(); });
    form.addEventListener('change', event => { if (event.target.matches('select')) search(); });
    form.querySelector('input[type="search"]').addEventListener('input', () => {
        controller?.abort();
        controller = null;
        clearTimeout(timer);
        timer = setTimeout(search, 350);
    });
    results.addEventListener('click', event => {
        const link = event.target.closest('[data-information-page]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        refresh(link.href, true);
    });
})();

(() => {
    'use strict';
    const root = document.querySelector('[data-office-requests]');
    if (!root) return;
    const filters = root.querySelector('[data-office-filter]');
    const results = root.querySelector('[data-office-results]');
    const feedback = root.querySelector('[data-office-feedback]');
    const refreshButton = root.querySelector('[data-office-refresh]');
    const disabled = new Map();
    let controller;
    let timer;
    let saving = false;
    refreshButton.hidden = false;
    function announce(message, error = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', error);
    }
    function busy(value, all = false) {
        if (value) {
            results.setAttribute('aria-busy', 'true');
            (all ? root : results).querySelectorAll('button,input,select').forEach(control => {
                if (!disabled.has(control)) disabled.set(control, control.disabled);
                control.disabled = true;
            });
        } else {
            results.removeAttribute('aria-busy');
            disabled.forEach((value, control) => { control.disabled = value; });
            disabled.clear();
        }
    }
    function cancelLoad() {
        controller?.abort();
        controller = null;
        clearTimeout(timer);
        busy(false);
    }
    async function refresh(page = 1, message = '', focus = false) {
        if (saving) return;
        cancelLoad();
        const url = new URL(filters.getAttribute('action'), location.href);
        url.search = new URLSearchParams(new FormData(filters)).toString();
        url.searchParams.set('page', page);
        const current = new AbortController();
        controller = current;
        const timeout = setTimeout(() => current.abort(), 30000);
        busy(true);
        announce(message || 'Loading requests...');
        try {
            const response = await fetch(url, { credentials: 'same-origin', signal: current.signal });
            if (response.redirected || response.status === 403) throw new Error('Your session changed. Reload to sign in again.');
            if (!response.ok) throw new Error('Requests could not be loaded. Use Refresh to try again.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.querySelector('[data-office-results]');
            if (!next) throw new Error('Requests are unavailable. Reload the page.');
            if (controller !== current) return;
            if (next.hasAttribute('data-load-error')) throw new Error(next.querySelector('.error')?.textContent || 'Check the filters.');
            results.replaceChildren(...next.childNodes);
            results.dataset.page = next.dataset.page;
            results.removeAttribute('data-load-error');
            url.searchParams.set('page', next.dataset.page);
            history.replaceState(null, '', url);
            announce(message || results.querySelector('[data-office-count]').textContent);
            if (focus) { results.tabIndex = -1; results.focus(); }
        } catch (error) {
            if (controller === current) announce((message ? message + ' ' : '') + (current.signal.aborted ? 'Loading timed out. Refresh the list.' : error.message), true);
        } finally {
            clearTimeout(timeout);
            if (controller === current) { controller = null; busy(false); }
        }
    }
    filters.addEventListener('submit', event => { event.preventDefault(); refresh(); });
    filters.addEventListener('change', event => { if (event.target.matches('select,input[type="date"]')) refresh(); });
    filters.addEventListener('input', event => {
        if (saving || !event.target.matches('input[type="search"]')) return;
        cancelLoad();
        timer = setTimeout(() => refresh(), 350);
    });
    refreshButton.addEventListener('click', () => refresh(results.dataset.page));
    root.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (saving && link) { event.preventDefault(); return; }
        if (!link?.matches('[data-office-page]') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        refresh(new URL(link.href).searchParams.get('page'), '', true);
    });
    results.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-office-decision]')) return;
        event.preventDefault();
        if (saving || controller) return;
        const decision = event.submitter?.value;
        if (!['approved', 'rejected'].includes(decision)) return;
        const id = form.elements.namedItem('request_id').value;
        if (!window.confirm('Record request #' + id + ' as ' + decision + '? This office decision is final.')) return;
        cancelLoad();
        const body = new FormData(form);
        body.set('decision', decision);
        saving = true;
        busy(true, true);
        announce('Saving office decision...');
        const mutation = new AbortController();
        const timeout = setTimeout(() => mutation.abort(), 45000);
        let message = '';
        try {
            const response = await fetch(form.getAttribute('action'), { method: 'POST', body, credentials: 'same-origin', signal: mutation.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Your session may have changed. Reload and check the request before trying again.');
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'The decision could not be confirmed. Refresh the list.');
            message = payload.message;
        } catch (error) {
            announce(error.name === 'AbortError' ? 'The decision could not be confirmed. Refresh and check its status before trying again.' : error.message, true);
        } finally {
            clearTimeout(timeout);
            saving = false;
            busy(false);
        }
        if (message) await refresh(results.dataset.page, message, true);
    });
})();

(() => {
    'use strict';
    const board = document.querySelector('[data-help-board]');
    if (!board) return;
    const filters = board.querySelector('[data-help-filter]');
    const results = board.querySelector('[data-help-results]');
    const feedback = board.querySelector('[data-help-feedback]');
    const refreshButton = board.querySelector('[data-help-refresh]');
    const drafts = new Map();
    const disabledStates = new Map();
    let controller = null;
    let saving = false;
    let timer;
    let page = results.dataset.page || '1';
    refreshButton.hidden = false;

    function announce(message, error = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', error);
    }

    function setBusy(busy, all = false) {
        if (busy) {
            results.setAttribute('aria-busy', 'true');
            const scope = all ? board : results;
            scope.querySelectorAll('button,input,select').forEach(control => {
                if (!disabledStates.has(control)) disabledStates.set(control, control.disabled);
                control.disabled = true;
            });
        } else {
            results.removeAttribute('aria-busy');
            disabledStates.forEach((disabled, control) => { control.disabled = disabled; });
            disabledStates.clear();
        }
    }

    function filterUrl(nextPage = page) {
        const url = new URL(filters.action);
        new FormData(filters).forEach((value, key) => url.searchParams.set(key, value));
        url.searchParams.set('page', String(nextPage));
        return url;
    }

    function rememberDrafts(exclude = '') {
        results.querySelectorAll('[data-help-assign]').forEach(form => {
            const id = form.elements.request_id.value;
            if (id !== exclude) drafts.set(id, {
                value: form.elements.volunteer_id.value,
                assignee: form.elements.expected_assignee.value,
                status: form.elements.expected_status.value
            });
        });
    }

    async function refresh(nextPage = page, exclude = '', focusResults = false) {
        if (saving) return false;
        clearTimeout(timer);
        if (controller) controller.abort();
        const current = new AbortController();
        controller = current;
        const timeout = setTimeout(() => current.abort(), 30000);
        const url = filterUrl(nextPage);
        setBusy(true);
        announce('Loading requests...');
        try {
            const response = await fetch(url, { credentials: 'same-origin', signal: current.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
            if (response.redirected || response.status === 403) throw new Error('Your session changed. Reload the page to sign in again.');
            if (!response.ok) throw new Error('Requests could not be loaded. Use Refresh to try again.');
            const incomingDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (controller !== current || current.signal.aborted) return false;
            const incoming = incomingDocument.querySelector('[data-help-results]');
            if (!incoming) throw new Error('Requests are unavailable. Reload to check your access.');
            if (incoming.hasAttribute('data-load-error')) throw new Error(incoming.querySelector('.error')?.textContent || 'Requests could not be loaded.');
            rememberDrafts(exclude);
            results.replaceChildren(...incoming.childNodes);
            results.removeAttribute('data-load-error');
            page = incoming.dataset.page || '1';
            results.dataset.page = page;
            results.querySelectorAll('[data-help-assign]').forEach(form => {
                const draft = drafts.get(form.elements.request_id.value);
                if (draft && draft.assignee === form.elements.expected_assignee.value && draft.status === form.elements.expected_status.value
                    && [...form.elements.volunteer_id.options].some(option => option.value === draft.value)) {
                    form.elements.volunteer_id.value = draft.value;
                }
            });
            url.searchParams.set('page', page);
            history.replaceState(null, '', url);
            announce('');
            if (focusResults) {
                const heading = results.querySelector('h2, h3');
                if (heading) { heading.tabIndex = -1; heading.focus(); }
            }
            return true;
        } catch (error) {
            if (controller === current) announce(error.name === 'AbortError' ? 'Loading timed out. Use Refresh to try again.' : error.message, true);
            return false;
        } finally {
            clearTimeout(timeout);
            if (controller === current) { controller = null; setBusy(false); }
        }
    }

    filters.addEventListener('submit', event => { event.preventDefault(); refresh(1); });
    filters.addEventListener('change', event => { if (event.target.matches('select')) refresh(1); });
    filters.addEventListener('input', event => {
        if (!event.target.matches('input[type="search"]')) return;
        if (controller) { controller.abort(); controller = null; setBusy(false); }
        clearTimeout(timer);
        timer = setTimeout(() => refresh(1), 350);
    });
    refreshButton.addEventListener('click', () => refresh());
    board.addEventListener('click', event => {
        const link = event.target.closest('[data-help-clear], .pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (saving) return;
        if (link.matches('[data-help-clear]')) {
            [...filters.elements].forEach(control => {
                if (control.matches('input[type="search"], select')) control.value = '';
            });
            refresh(1);
        } else {
            refresh(new URL(link.href).searchParams.get('page') || 1, '', true);
        }
    });
    board.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-help-assign]')) return;
        event.preventDefault();
        if (saving || controller) return;
        clearTimeout(timer);
        const body = new FormData(form);
        const id = form.elements.request_id.value;
        saving = true;
        setBusy(true, true);
        announce('Saving assignment...');
        const mutation = new AbortController();
        const timeout = setTimeout(() => mutation.abort(), 45000);
        let saved = false;
        let message = '';
        try {
            const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', signal: mutation.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) {
                throw new Error('Your session changed. Reload and check the request before trying again.');
            }
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Assignment could not be saved.');
            saved = true;
            message = data.message;
            drafts.delete(id);
        } catch (error) {
            announce(error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
                ? 'The assignment could not be confirmed. Refresh to check its status before trying again.' : error.message, true);
        } finally {
            clearTimeout(timeout);
            saving = false;
            setBusy(false);
        }
        if (saved) {
            const loaded = await refresh(page, id);
            announce(loaded ? message : message + ' Refresh to load the updated queue.', !loaded);
            if (loaded) {
                const updated = results.querySelector(`[data-help-card="${id}"] select`);
                if (updated) updated.focus({ preventScroll: true });
                else refreshButton.focus({ preventScroll: true });
            }
        }
    });
})();

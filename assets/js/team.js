(() => {
    'use strict';
    const workspace = document.querySelector('[data-team-workspace]');
    if (!workspace) return;
    const navigation = workspace.querySelector('.team-nav');
    const tabs = navigation ? [...navigation.querySelectorAll('a')] : [];
    const panels = [...workspace.querySelectorAll('[data-team-panel]')];
    const feedback = workspace.querySelector('[data-team-feedback]');
    const requests = new Map();
    const cancelled = new WeakSet();
    const timers = new Map();
    const mutations = new Set();
    const drafts = new Map();
    const disabledButtons = new WeakMap();
    const stateUrl = new URL(location.href);
    // Keep existing bookmarked queue links working after splitting the workspace.
    if (!workspace.dataset.defaultPanel) {
        const legacyPages = { 'assigned-tasks': 'staff_tasks.php', 'open-help': 'staff_help.php',
            'guarantor-reviews': 'staff_reviews.php', 'volunteer-activity': 'staff_activity.php' };
        const destination = legacyPages[location.hash.slice(1)];
        if (destination) {
            const url = new URL(destination, location.href);
            url.search = location.search;
            location.replace(url);
            return;
        }
    }

    function cancelRequest(panel) {
        const controller = requests.get(panel);
        if (controller) {
            cancelled.add(controller);
            controller.abort();
        }
    }

    function announce(message, error = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', error);
    }

    function currentUrl(panel, page = null) {
        const url = new URL(stateUrl);
        workspace.querySelectorAll('[data-team-filter]').forEach(form => {
            [...form.elements].forEach(control => {
                if (control.name && control.type !== 'hidden') url.searchParams.set(control.name, control.value);
            });
        });
        if (page !== null) url.searchParams.set(panel.dataset.page, String(page));
        url.hash = panel.id;
        return url;
    }

    function rememberDrafts(panel, excludedId = '') {
        panel.querySelectorAll('[data-team-results] form').forEach(form => {
            const id = form.elements.request_id?.value;
            if (!id || id === excludedId) return;
            const values = {};
            ['notes', 'volunteer_id'].forEach(name => {
                if (form.elements[name]) values[name] = form.elements[name].value;
            });
            drafts.set(`${panel.id}:${id}`, values);
        });
    }

    function restoreDrafts(panel) {
        panel.querySelectorAll('[data-team-results] form').forEach(form => {
            const values = drafts.get(`${panel.id}:${form.elements.request_id?.value}`);
            if (!values) return;
            Object.entries(values).forEach(([name, value]) => {
                const control = form.elements[name];
                if (!control) return;
                if (control.tagName !== 'SELECT' || [...control.options].some(option => option.value === value)) {
                    control.value = value;
                }
            });
        });
    }

    async function refresh(panel, url = currentUrl(panel), excludedId = '') {
        if (!panel.dataset.page || mutations.has(panel)) return false;
        clearTimeout(timers.get(panel));
        cancelRequest(panel);
        const controller = new AbortController();
        requests.set(panel, controller);
        const timeout = setTimeout(() => controller.abort(), 30000);
        const results = panel.querySelector('[data-team-results]');
        results.setAttribute('aria-busy', 'true');
        results.querySelectorAll('button').forEach(button => {
            if (!disabledButtons.has(button)) disabledButtons.set(button, button.disabled);
            button.disabled = true;
        });
        if (!panel.hidden) announce('Updating results...');
        try {
            // Reuse server-rendered rows; only the requested queue is replaced.
            const response = await fetch(url, {
                credentials: 'same-origin', signal: controller.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Team-Panel': panel.id, Accept: 'text/html' }
            });
            if (response.redirected || response.status === 403) {
                throw new Error('Your session or permissions changed. Reload the page to sign in again.');
            }
            if (!response.ok) throw new Error('Could not load results. Please try again.');
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (requests.get(panel) !== controller || controller.signal.aborted) return false;
            const incoming = document.getElementById(panel.id);
            const incomingResults = incoming?.querySelector('[data-team-results]');
            if (!incomingResults) throw new Error('Could not load this queue. Reload the page to check your access.');
            rememberDrafts(panel, excludedId);
            results.replaceChildren(...incomingResults.childNodes);
            panel.querySelector('[data-team-count]').textContent = incoming.querySelector('[data-team-count]').textContent;
            const total = incoming.querySelector('[data-team-count]').dataset.total;
            panel.querySelector('[data-team-count]').dataset.total = total;
            const summary = workspace.querySelector(`[data-team-summary="${panel.id}"]`);
            if (summary) summary.textContent = total;
            restoreDrafts(panel);
            stateUrl.searchParams.set(panel.dataset.page, results.querySelector('.pagination')?.dataset.currentPage || '1');
            // Keep each queue's search/filter state without moving focus or replacing the composer.
            const form = panel.querySelector('[data-team-filter]');
            [...form.elements].forEach(control => {
                if (control.name && control.type !== 'hidden') stateUrl.searchParams.set(control.name, control.value);
            });
            if (!panel.hidden) {
                stateUrl.hash = panel.id;
                history.replaceState(null, '', stateUrl);
                announce('');
            }
            return true;
        } catch (error) {
            if (requests.get(panel) === controller && !cancelled.has(controller) && !panel.hidden) {
                announce(error.name === 'AbortError' ? 'Loading timed out. Use Refresh to try again.' : error.message, true);
            }
            return false;
        } finally {
            clearTimeout(timeout);
            if (requests.get(panel) === controller) {
                requests.delete(panel);
                results.removeAttribute('aria-busy');
                results.querySelectorAll('button').forEach(button => {
                    if (disabledButtons.has(button)) {
                        button.disabled = disabledButtons.get(button);
                        disabledButtons.delete(button);
                    }
                });
            }
        }
    }

    function activate(id, reload = true) {
        const wantsChat = id === 'chat-heading' || id === 'team-helpdesk';
        const selected = panels.find(panel => panel.id === id)
            || panels.find(panel => panel.id === workspace.dataset.defaultPanel) || panels[0];
        if (!selected) {
            if (wantsChat && reload) document.dispatchEvent(new CustomEvent('ksij:chat-open'));
            return;
        }
        panels.forEach(panel => { panel.hidden = panel !== selected; });
        tabs.forEach(tab => {
            const active = tab.hash === `#${selected.id}`;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
        stateUrl.hash = wantsChat ? 'chat-heading' : selected.id;
        history.replaceState(null, '', stateUrl);
        document.dispatchEvent(new CustomEvent('ksij:team-panel', { detail: { id: wantsChat ? 'chat-heading' : selected.id } }));
        announce('');
        if (wantsChat && reload) document.dispatchEvent(new CustomEvent('ksij:chat-open'));
        if (reload) refresh(selected);
    }

    if (navigation) navigation.setAttribute('role', 'tablist');
    tabs.forEach((tab, index) => {
        const panel = panels.find(item => `#${item.id}` === tab.hash);
        if (!panel) return;
        tab.id = `team-tab-${index}`;
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', panel.id);
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tab.id);
        tab.addEventListener('click', event => {
            event.preventDefault();
            activate(panel.id);
        });
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next === undefined) return;
            event.preventDefault();
            tabs[next].focus();
            activate(tabs[next].hash.slice(1));
        });
    });

    workspace.querySelectorAll('[data-team-filter]').forEach(form => {
        const panel = form.closest('[data-team-panel]');
        const apply = () => refresh(panel, currentUrl(panel, 1));
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button-secondary team-refresh';
        button.title = 'Refresh queue';
        button.setAttribute('aria-label', 'Refresh queue');
        const refreshIcon = document.createElement('img');
        refreshIcon.src = new URL('../icons/refresh-cw.svg', document.querySelector('script[src*="assets/js/team.js"]').src).href;
        refreshIcon.className = 'ui-icon';
        refreshIcon.alt = '';
        refreshIcon.setAttribute('aria-hidden', 'true');
        button.appendChild(refreshIcon);
        button.addEventListener('click', () => refresh(panel));
        form.appendChild(button);
        form.addEventListener('submit', event => { event.preventDefault(); apply(); });
        form.addEventListener('change', event => {
            if (event.target.matches('select')) apply();
        });
        form.addEventListener('input', event => {
            if (!event.target.matches('input[type="search"]')) return;
            cancelRequest(panel);
            clearTimeout(timers.get(panel));
            timers.set(panel, setTimeout(apply, 350));
        });
    });

    workspace.addEventListener('click', event => {
        const queueLink = event.target.closest('[data-team-link]');
        if (queueLink && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            activate(queueLink.hash.slice(1));
            return;
        }
        const link = event.target.closest('.pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const panel = link.closest('[data-team-panel]');
        const page = new URL(link.href).searchParams.get(panel.dataset.page);
        refresh(panel, currentUrl(panel, page));
    });

    workspace.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('.team-command, .team-assignment, .team-review-form')) return;
        event.preventDefault();
        const panel = form.closest('[data-team-panel]');
        if (mutations.has(panel) || requests.has(panel)) return;
        const body = new FormData(form);
        if (event.submitter?.name) body.set(event.submitter.name, event.submitter.value);
        if (body.get('decision') === 'rejected' && !String(body.get('notes') || '').trim()) {
            announce('Please give a reason for rejecting this request.', true);
            form.elements.notes.focus();
            return;
        }
        clearTimeout(timers.get(panel));
        mutations.add(panel);
        const controls = [...panel.querySelectorAll('input, select, textarea, button')];
        const previous = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        announce('Saving update...');
        let message = '';
        let saved = false;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 45000);
        try {
            // A hidden input named action shadows the form.action property.
            const response = await fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST', body, credentials: 'same-origin', signal: controller.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
            });
            if (response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) {
                throw new Error('Your session or permissions may have changed. Reload before trying again.');
            }
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'The update could not be saved.');
            saved = true;
            message = data.message;
            drafts.delete(`${panel.id}:${body.get('request_id')}`);
        } catch (error) {
            announce(error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
                ? 'The update could not be confirmed. Refresh the queue to check its status before trying again.'
                : error.message, true);
        } finally {
            clearTimeout(timeout);
            controls.forEach((control, index) => { control.disabled = previous[index]; });
            form.removeAttribute('aria-busy');
            mutations.delete(panel);
        }
        if (saved) {
            const loaded = await refresh(panel, currentUrl(panel), String(body.get('request_id')));
            announce(loaded ? message : `${message} The list could not refresh; use Refresh to reload it.`, !loaded);
        }
    });

    window.addEventListener('hashchange', () => activate(location.hash.slice(1)));
    activate(location.hash.slice(1), false);
})();

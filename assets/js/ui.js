(() => {
    'use strict';
    const sidebar = document.getElementById('app-sidebar');
    const toggle = document.querySelector('[data-nav-toggle]');
    if (!sidebar || !toggle) return;
    const backdrop = document.querySelector('[data-nav-backdrop]');
    const mobile = window.matchMedia('(max-width: 960px)');
    let open = false;

    function update(value, restoreFocus = false) {
        open = value && mobile.matches;
        document.body.classList.toggle('navigation-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        sidebar.inert = mobile.matches && !open;
        if (open) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            sidebar.querySelector('[data-nav-close]').focus();
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (restoreFocus) toggle.focus();
        }
    }
    toggle.addEventListener('click', () => update(!open, open));
    backdrop.addEventListener('click', () => update(false, true));
    sidebar.querySelector('[data-nav-close]').addEventListener('click', () => update(false, true));
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => update(false, true)));
    document.addEventListener('keydown', event => {
        if (!open) return;
        if (event.key === 'Escape') { event.preventDefault(); update(false, true); }
        if (event.key !== 'Tab') return;
        const controls = Array.from(sidebar.querySelectorAll('a[href],button')).filter(element => element.getClientRects().length);
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    mobile.addEventListener('change', () => update(false));
    update(false);

    function highlightNavigation(panelId = '') {
        const isTeamPage = Boolean(document.querySelector('[data-team-workspace]'));
        const hash = panelId ? `#${panelId}` : location.hash
            || (isTeamPage ? `#${document.querySelector('[data-team-workspace]').dataset.defaultPanel}` : '');
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            const url = new URL(link.href);
            const samePage = url.pathname === location.pathname;
            const active = samePage && (url.hash ? url.hash === hash : !isTeamPage || !hash);
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', url.hash ? 'location' : 'page');
            else link.removeAttribute('aria-current');
        });
    }
    window.addEventListener('hashchange', () => highlightNavigation());
    document.addEventListener('ksij:team-panel', event => highlightNavigation(event.detail.id));
    highlightNavigation();

    document.querySelectorAll('.notification-bell').forEach(bell => {
        document.addEventListener('click', event => { if (!bell.contains(event.target)) bell.open = false; });
        bell.addEventListener('keydown', event => {
            if (event.key === 'Escape') { bell.open = false; bell.querySelector('summary').focus(); }
        });
    });
})();

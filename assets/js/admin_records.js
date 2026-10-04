(() => {
    'use strict';
    const workspace = document.querySelector('.admin-workspace');
    if (!workspace) return;
    const script = document.querySelector('script[src*="assets/js/admin_records.js"]');
    function icon(name) {
        const image = document.createElement('img');
        image.src = new URL(`../icons/${name}.svg`, script.src).href;
        image.className = 'ui-icon';
        image.alt = '';
        image.setAttribute('aria-hidden', 'true');
        return image;
    }
    workspace.querySelectorAll('.admin-grid.two-columns').forEach(grid => {
        const formSection = [...grid.children].find(section => section.querySelector('.admin-form'));
        if (!formSection) return;
        const form = formSection.querySelector('.admin-form');
        const heading = formSection.querySelector('h2');
        const details = document.createElement('details');
        details.className = 'admin-editor';
        details.open = Number(form.elements.id?.value) > 0 || Boolean(workspace.querySelector('.error'));
        const summary = document.createElement('summary');
        summary.append(icon(Number(form.elements.id?.value) > 0 ? 'pencil' : 'plus'));
        const label = document.createElement('span');
        label.textContent = heading?.textContent || 'Record details';
        summary.append(label, icon('chevron-right'));
        details.appendChild(summary);
        formSection.before(details);
        details.appendChild(formSection);
        if (heading) heading.remove();
        grid.classList.add('admin-records-layout');
        const actions = document.createElement('div');
        actions.className = 'form-actions admin-editor-actions';
        const save = form.querySelector('button[type="submit"]');
        if (save) { save.prepend(icon('save')); actions.appendChild(save); }
        const cancel = document.createElement('a');
        cancel.href = location.pathname;
        cancel.textContent = Number(form.elements.id?.value) > 0 ? 'Cancel editing' : 'Close';
        if (!Number(form.elements.id?.value)) cancel.addEventListener('click', event => {
            event.preventDefault(); details.open = false; summary.focus();
        });
        actions.appendChild(cancel);
        form.appendChild(actions);
        form.addEventListener('invalid', () => { details.open = true; }, true);
    });

    workspace.querySelectorAll('.data-table').forEach((table, index) => {
        const tbody = table.tBodies[0];
        if (!tbody) return;
        const rows = [...tbody.rows].filter(row => !row.querySelector('.empty-state'));
        const wrap = table.closest('.table-wrap');
        if (!wrap) return;
        const title = table.closest('.admin-card')?.querySelector('h2')?.textContent || 'Records';
        wrap.tabIndex = 0;
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', title);
        table.querySelectorAll('thead th').forEach(cell => { cell.scope = 'col'; });
        const toolbar = document.createElement('div');
        toolbar.className = 'admin-records-toolbar';
        const field = document.createElement('div');
        const label = document.createElement('label');
        const search = document.createElement('input');
        search.type = 'search';
        search.id = `record-search-${index}`;
        search.placeholder = 'Search records';
        search.autocomplete = 'off';
        label.htmlFor = search.id;
        label.textContent = `Search ${title.toLowerCase()}`;
        field.append(label, search);
        const count = document.createElement('span');
        count.className = 'muted';
        count.setAttribute('role', 'status');
        toolbar.append(field, count);
        wrap.before(toolbar);
        const empty = document.createElement('tr');
        const emptyCell = document.createElement('td');
        emptyCell.colSpan = table.tHead?.rows[0]?.cells.length || 1;
        emptyCell.className = 'empty-state';
        empty.appendChild(emptyCell);
        [...tbody.rows].filter(row => row.querySelector('.empty-state')).forEach(row => row.remove());
        tbody.appendChild(empty);
        const text = rows.map(row => [...row.cells].filter(cell => !cell.querySelector('form') && !cell.classList.contains('row-actions')).map(cell => cell.textContent).join(' ').toLocaleLowerCase());
        let page = 1;
        const perPage = 12;
        const nav = document.createElement('nav');
        nav.className = 'pagination';
        nav.setAttribute('aria-label', `${title} pages`);
        const pageLabel = document.createElement('span');
        function pageButton(name, direction) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'icon-button button-secondary';
            button.title = name;
            button.setAttribute('aria-label', name);
            button.appendChild(icon(direction < 0 ? 'chevron-left' : 'chevron-right'));
            button.addEventListener('click', () => { page += direction; update(); });
            return button;
        }
        const previous = pageButton('Previous page', -1);
        const next = pageButton('Next page', 1);
        nav.append(pageLabel, previous, next);
        wrap.after(nav);
        function update() {
            const query = search.value.trim().toLocaleLowerCase();
            const matched = rows.filter((row, rowIndex) => text[rowIndex].includes(query));
            const pages = Math.max(1, Math.ceil(matched.length / perPage));
            page = Math.max(1, Math.min(page, pages));
            rows.forEach(row => { row.hidden = true; });
            matched.slice((page - 1) * perPage, page * perPage).forEach(row => { row.hidden = false; });
            empty.hidden = matched.length > 0;
            emptyCell.textContent = rows.length ? 'No records match your search.' : 'No records yet.';
            count.textContent = `${matched.length} ${matched.length === 1 ? 'record' : 'records'}`;
            pageLabel.textContent = `Page ${page} of ${pages}`;
            previous.disabled = page === 1;
            next.disabled = page === pages;
            nav.hidden = pages === 1;
        }
        search.addEventListener('input', () => { page = 1; update(); });
        update();
    });
    workspace.querySelectorAll('.row-actions a').forEach(link => {
        if (link.textContent.trim() !== 'Edit') return;
        link.classList.add('record-icon-action');
        link.title = 'Edit record'; link.setAttribute('aria-label', 'Edit record');
        link.replaceChildren(icon('pencil'));
    });
    workspace.querySelectorAll('.row-actions button').forEach(button => {
        if (button.textContent.trim() !== 'Delete') return;
        button.classList.add('record-icon-action');
        button.title = 'Delete record'; button.setAttribute('aria-label', 'Delete record');
        button.replaceChildren(icon('trash-2'));
    });
})();

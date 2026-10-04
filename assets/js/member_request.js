(() => {
    'use strict';
    const form = document.querySelector('[data-formal-request]');
    if (!form) return;
    const type = form.elements.type;
    const marks = form.elements.last_exam_marks;
    const total = form.elements.last_exam_total;
    const groups = [...form.querySelectorAll('[data-request-types]')];
    const guarantorSlots = [form.elements.guarantor1_id, form.elements.guarantor2_id];
    const choices = [...form.querySelectorAll('[data-guarantor-choice]')];
    const directory = form.querySelector('[data-guarantor-directory]');
    function syncGuarantors() {
        const selected = new Set(guarantorSlots.map(control => control.value).filter(Boolean));
        guarantorSlots[1].setCustomValidity(guarantorSlots[0].value && guarantorSlots[0].value === guarantorSlots[1].value
            ? 'Choose two different guarantors.' : '');
        form.querySelector('[data-guarantor-count]').textContent = `${selected.size} / 2 selected`;
        choices.forEach(choice => {
            choice.checked = selected.has(choice.value);
            choice.disabled = selected.size >= 2 && !choice.checked;
            choice.closest('[data-guarantor-option]').classList.toggle('is-selected', choice.checked);
        });
    }
    directory.hidden = false;
    guarantorSlots.forEach(control => control.addEventListener('change', syncGuarantors));
    choices.forEach(choice => choice.addEventListener('change', () => {
        if (choice.checked) {
            const freeSlot = guarantorSlots.find(control => !control.value);
            if (freeSlot) freeSlot.value = choice.value;
        } else {
            guarantorSlots.forEach(control => { if (control.value === choice.value) control.value = ''; });
        }
        syncGuarantors();
    }));
    form.querySelector('#guarantor-search').addEventListener('input', event => {
        const query = event.target.value.trim().toLocaleLowerCase();
        const options = [...form.querySelectorAll('[data-guarantor-option]')];
        options.forEach(option => { option.hidden = !option.dataset.search.includes(query); });
        form.querySelector('[data-guarantor-empty]').hidden = options.some(option => !option.hidden);
    });
    function validateMarks() {
        marks.setCustomValidity(type.value === 'scholarship' && Number.isFinite(marks.valueAsNumber)
            && Number.isFinite(total.valueAsNumber) && marks.valueAsNumber > total.valueAsNumber
            ? 'Marks obtained cannot exceed the total possible marks.' : '');
    }
    function update() {
        groups.forEach(group => {
            const active = group.dataset.requestTypes.split(' ').includes(type.value);
            group.hidden = !active;
            group.disabled = !active;
            group.querySelectorAll('[data-request-required]').forEach(control => { control.required = active; });
        });
        validateMarks();
        syncGuarantors();
    }
    marks.addEventListener('input', validateMarks);
    total.addEventListener('input', validateMarks);
    type.addEventListener('change', update);
    window.addEventListener('pageshow', update);
    update();
})();

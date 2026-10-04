(() => {
    'use strict';
    const panel = document.querySelector('[data-chat]');
    if (!panel) return;
    const form = panel.querySelector('[data-chat-form]');
    const question = form.elements.message;
    const messages = panel.querySelector('[data-chat-messages]');
    const status = panel.querySelector('[data-chat-status]');
    const clear = panel.querySelector('[data-chat-clear]');
    const send = form.querySelector('button');
    const launcher = document.querySelector('[data-chat-launcher]');
    const unread = launcher.querySelector('[data-chat-unread]');
    const topics = [...panel.querySelectorAll('[data-chat-prompt]')];
    let previousFocus = null;
    let unreadCount = 0;
    let busy = false;

    function fitChat() {
        const viewport = window.visualViewport;
        const height = viewport?.height || window.innerHeight;
        const bottom = viewport ? Math.max(0, window.innerHeight - height - viewport.offsetTop) : 0;
        panel.style.setProperty('--chat-viewport-height', `${height}px`);
        panel.style.setProperty('--chat-viewport-bottom', `${bottom}px`);
        panel.classList.toggle('is-compact', height <= 520);
    }
    window.addEventListener('resize', fitChat);
    window.visualViewport?.addEventListener('resize', fitChat);
    window.visualViewport?.addEventListener('scroll', fitChat);

    function setStatus(message, error = false) {
        status.textContent = message;
        status.classList.toggle('is-error', error);
    }

    function setUnread(value) {
        unreadCount = value;
        unread.hidden = value === 0;
        unread.textContent = value > 9 ? '9+' : String(value);
        launcher.setAttribute('aria-label', value ? `Open KSIJ Assistant (${value} new replies)` : 'Open KSIJ Assistant');
    }

    function openChat() {
        if (panel.open) { focusComposer(); return; }
        if (document.body.classList.contains('navigation-open')) {
            document.querySelector('[data-nav-close]')?.click();
        }
        previousFocus = document.activeElement;
        fitChat();
        panel.showModal();
        document.body.classList.add('floating-chat-open');
        launcher.setAttribute('aria-expanded', 'true');
        setUnread(0);
        messages.scrollTop = messages.scrollHeight;
        focusComposer();
    }

    function closeChat() {
        panel.close();
    }

    launcher.addEventListener('click', openChat);
    panel.querySelectorAll('[data-chat-close]').forEach(button => button.addEventListener('click', closeChat));
    panel.addEventListener('cancel', event => { event.preventDefault(); closeChat(); });
    panel.addEventListener('close', () => {
        document.body.classList.remove('floating-chat-open');
        launcher.setAttribute('aria-expanded', 'false');
        const target = previousFocus !== document.body && previousFocus?.isConnected && previousFocus.getClientRects().length ? previousFocus : launcher;
        target.focus({ preventScroll: true });
    });
    panel.addEventListener('click', event => {
        if (event.target !== panel) return;
        const bounds = panel.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeChat();
    });
    document.addEventListener('click', event => {
        if (event.target.closest('[data-chat-open]')) openChat();
    });
    document.addEventListener('ksij:chat-open', openChat);
    window.addEventListener('hashchange', () => {
        if (['#chat-heading', '#team-helpdesk'].includes(location.hash)) openChat();
    });

    function updateTopics() {
        topics.forEach(button => { button.disabled = busy || question.value.trim() !== ''; });
    }
    question.addEventListener('input', updateTopics);
    question.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (!busy) form.requestSubmit();
        }
    });
    topics.forEach(button => button.addEventListener('click', () => {
        if (busy || question.value.trim()) return;
        question.value = button.dataset.chatPrompt;
        form.requestSubmit();
    }));

    function addMessage(text, kind) {
        const item = document.createElement('p');
        item.className = `chat-message chat-${kind}`;
        item.textContent = text;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
        if (kind === 'assistant' && !panel.open) setUnread(unreadCount + 1);
        return item;
    }

    function setBusy(value) {
        busy = value;
        send.disabled = clear.disabled = value;
        messages.setAttribute('aria-busy', String(value));
        updateTopics();
    }

    function focusComposer() {
        if (!panel.open) return;
        question.focus({ preventScroll: true });
    }

    async function request(body) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 55000);
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ...body, csrf_token: panel.dataset.csrf })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.text || 'The helpdesk is unavailable.');
            return data;
        } finally {
            clearTimeout(timeout);
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const text = question.value.trim();
        if (!text) return;
        if (new TextEncoder().encode(text).length > 2000) {
            setStatus('Your question is too long. Please shorten it.', true);
            return;
        }
        panel.querySelector('[data-chat-welcome]')?.remove();
        const userMessage = addMessage(text, 'user');
        question.value = '';
        setStatus('Thinking...');
        setBusy(true);
        try {
            const data = await request({ message: text, language: form.elements.language.value });
            if (typeof data.text !== 'string' || !data.text.trim()) {
                throw new Error('The helpdesk returned an empty reply. Please try again.');
            }
            addMessage(data.text, 'assistant');
            setStatus('');
        } catch (error) {
            userMessage.remove();
            if (!question.value) question.value = text;
            setStatus(error.name === 'AbortError' ? 'The request timed out. Reload the page before retrying.' : (error instanceof SyntaxError ? 'The helpdesk is unavailable. Reload the page and try again.' : error.message), true);
        } finally {
            setBusy(false);
            focusComposer();
        }
    });

    clear.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        setStatus('');
        const draft = question.value;
        try {
            await request({ action: 'clear' });
            messages.replaceChildren();
            if (question.value === draft) question.value = '';
            addMessage('Assalamu alaikum. How can I help you today?', 'assistant');
        } catch (error) {
            setStatus('Could not clear the conversation. Reload and try again.', true);
        } finally {
            setBusy(false);
            focusComposer();
        }
    });
    messages.scrollTop = messages.scrollHeight;
    if (panel.dataset.autoOpen === 'true' || ['#chat-heading', '#team-helpdesk'].includes(location.hash)) openChat();
})();

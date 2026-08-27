(function () {
    const config = window.BOB_C || {};
    const chat = document.getElementById('chat');
    const form = document.getElementById('ask-form');
    const input = document.getElementById('message');
    const statusPill = document.getElementById('status-pill');
    const clearBtn = document.getElementById('clear-btn');

    if (!chat || !form) {
        return;
    }

    function headers() {
        const h = { 'Content-Type': 'application/json' };
        if (config.token) {
            h['X-Bob-C-Token'] = config.token;
        }
        return h;
    }

    function apiUrl(action) {
        const token = config.token ? '&token=' + encodeURIComponent(config.token) : '';
        return config.api + '?action=' + encodeURIComponent(action) + token;
    }

    function addMessage(role, content) {
        const wrap = document.createElement('div');
        wrap.className = 'msg ' + (role === 'user' ? 'user' : 'assistant');
        const who = document.createElement('div');
        who.className = 'who';
        who.textContent = role === 'user' ? 'You' : 'Bob G';
        const body = document.createElement('div');
        body.className = 'body';
        body.textContent = content;
        wrap.appendChild(who);
        wrap.appendChild(body);
        chat.appendChild(wrap);
        chat.scrollTop = chat.scrollHeight;
    }

    function renderHistory(messages) {
        chat.innerHTML = '';
        if (!messages || messages.length === 0) {
            addMessage('assistant', 'Ask Bob G to create a Laravel function or integrate a PSP.');
            return;
        }
        messages.forEach(function (row) {
            addMessage(row.role, row.content);
        });
    }

    async function loadStatus() {
        const res = await fetch(apiUrl('status'), { headers: headers() });
        const data = await res.json();
        if (!res.ok) {
            statusPill.textContent = data.error || 'BOB C locked';
            return;
        }
        if (data.connected) {
            statusPill.textContent = 'Bob G connected · ' + data.model;
            statusPill.classList.add('ok');
        } else {
            statusPill.textContent = 'Local helper · add XAI_API_KEY for Bob G';
        }
    }

    async function loadHistory() {
        const res = await fetch(apiUrl('history'), { headers: headers() });
        const data = await res.json();
        renderHistory(data.messages || []);
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) {
            return;
        }
        addMessage('user', message);
        input.value = '';
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const res = await fetch(apiUrl('ask'), {
                method: 'POST',
                headers: headers(),
                body: JSON.stringify({ message: message })
            });
            const data = await res.json();
            if (!res.ok) {
                addMessage('assistant', data.error || 'Bob G could not answer.');
                return;
            }
            addMessage('assistant', data.reply);
            if (data.notice) {
                addMessage('assistant', data.notice);
            }
        } catch (error) {
            addMessage('assistant', 'Network error talking to Bob G.');
        } finally {
            button.disabled = false;
            input.focus();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', async function () {
            await fetch(apiUrl('clear'), { method: 'POST', headers: headers(), body: JSON.stringify({}) });
            renderHistory([]);
        });
    }

    loadStatus();
    loadHistory();
})();

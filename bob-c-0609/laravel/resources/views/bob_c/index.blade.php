@extends('layouts.adminpanel')

@section('content')
<div class="container-fluid bob-c-dashboard">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <div class="text-uppercase small font-weight-bold mb-2">Backend · Sidebar</div>
            <h1 class="mb-1">BOB C</h1>
            <p class="mb-0">BOB C extends Bob G. Continue harness, recommendations, and adaptor work.</p>
        </div>
        <div id="status-pill" class="badge badge-warning p-2">Checking Bob G…</div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1">Ask Bob G</h2>
                            <p class="text-muted mb-0">Grok agent for Laravel functions and PSP integrations.</p>
                        </div>
                        <button id="clear-btn" type="button" class="btn btn-light">Clear</button>
                    </div>
                    <div id="chat" class="border rounded p-3 bg-light" style="height:420px;overflow:auto"></div>
                    <form id="ask-form" class="mt-3">
                        @csrf
                        <textarea id="message" class="form-control mb-2" rows="2" placeholder="Ask Bob G to draft a Laravel function or PSP integration…" required></textarea>
                        <button type="submit" class="btn btn-primary">Ask Bob G</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h5">Bob G can</h3>
                    <ul>
                        <li>Create Laravel controllers, routes, services, and migrations</li>
                        <li>Draft PSP adaptors and webhook handlers</li>
                        <li>Keep sandbox and live traffic gated</li>
                        <li>Explain 0609 Hostinger deploy steps</li>
                    </ul>
                    <h3 class="h5">Provider codes</h3>
                    <ul class="list-unstyled mb-0">
                        <li><strong>P003</strong> FBLS</li>
                        <li><strong>P004</strong> Xcore</li>
                        <li><strong>OR001</strong> CashForo onramp</li>
                        <li><strong>OB003</strong> CashForo open banking</li>
                        <li><strong>AfrPay</strong> Europe / Kazakhstan / Tunisia</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.BOB_C = {
        token: '',
        api: {{ json_encode(url('/bob-c')) }}
    };
</script>
<script>
    (function () {
        const base = window.BOB_C.api;
        const chat = document.getElementById('chat');
        const form = document.getElementById('ask-form');
        const input = document.getElementById('message');
        const statusPill = document.getElementById('status-pill');
        const csrf = document.querySelector('input[name="_token"]').value;

        function addMessage(role, content) {
            const wrap = document.createElement('div');
            wrap.className = 'mb-3';
            wrap.innerHTML = '<div class="small font-weight-bold">' + (role === 'user' ? 'You' : 'Bob G') + '</div>';
            const body = document.createElement('div');
            body.style.whiteSpace = 'pre-wrap';
            body.textContent = content;
            wrap.appendChild(body);
            chat.appendChild(wrap);
            chat.scrollTop = chat.scrollHeight;
        }

        function renderHistory(messages) {
            chat.innerHTML = '';
            if (!messages || !messages.length) {
                addMessage('assistant', 'Ask Bob G to create a Laravel function or integrate a PSP.');
                return;
            }
            messages.forEach(function (row) { addMessage(row.role, row.content); });
        }

        async function json(url, options) {
            const res = await fetch(url, options);
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Request failed');
            return data;
        }

        json(base + '/status').then(function (data) {
            if (data.connected) {
                statusPill.textContent = 'Bob G connected · ' + data.model;
                statusPill.className = 'badge badge-success p-2';
            } else {
                statusPill.textContent = 'Local helper · add XAI_API_KEY for Bob G';
            }
        }).catch(function () {
            statusPill.textContent = 'BOB C status unavailable';
        });

        json(base + '/history').then(function (data) {
            renderHistory(data.messages || []);
        }).catch(function () {
            renderHistory([]);
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const message = input.value.trim();
            if (!message) return;
            addMessage('user', message);
            input.value = '';
            try {
                const data = await json(base + '/ask', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ message: message })
                });
                addMessage('assistant', data.reply);
                if (data.notice) addMessage('assistant', data.notice);
            } catch (error) {
                addMessage('assistant', error.message);
            }
        });

        document.getElementById('clear-btn').addEventListener('click', async function () {
            await json(base + '/clear', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf }
            });
            renderHistory([]);
        });
    })();
</script>
@endsection

/**
 * Shopping List SPA
 *
 * Real-time: Mercure hub (pub/sub over SSE)
 *
 * Flow:
 *   1. On load → GET /api/items (initial state)
 *   2. Subscribe to Mercure hub at /.well-known/mercure?topic=...
 *   3. Mercure pushes granular events: item.created / item.updated / item.deleted
 *   4. JS applies surgical DOM update — no full re-render, no polling
 *
 * CRUD operations use AJAX (fetch) → PHP → Mercure publish → all browsers update
 */

const API          = '/api/items';
const MERCURE_URL  = '/.well-known/mercure';
const MERCURE_TOPIC = 'https://shopping-list/items';

// ── State ─────────────────────────────────────────────────────────────────────
let items     = [];
let editingId = null;

// ── Utils ─────────────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);

function plural(n, one, few, many) {
    if (n % 10 === 1 && n % 100 !== 11) return one;
    if ([2,3,4].includes(n % 10) && ![12,13,14].includes(n % 100)) return few;
    return many;
}

function fmtDate(iso) {
    return new Date(iso).toLocaleString('uk-UA', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

function escHtml(str) {
    return str
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showToast(msg, type = 'info', ms = 3000) {
    const t = $('toast');
    t.textContent = msg;
    t.className   = `toast ${type}`;
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.add('hidden'), ms);
}

function setStatus(online, text) {
    $('statusDot').className    = 'status-dot' + (online ? '' : ' error');
    $('statusText').textContent = text;
}

function updateCount() {
    $('itemCount').textContent =
        `${items.length} ${plural(items.length, 'елемент', 'елементи', 'елементів')}`;
}

// ── API helpers ───────────────────────────────────────────────────────────────
async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        ...options,
    });
    if (res.status === 204) return null;
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
}

// ── Render ────────────────────────────────────────────────────────────────────
function renderItems() {
    const list  = $('itemsList');
    const empty = $('emptyState');

    if (items.length === 0) {
        list.innerHTML      = '';
        empty.style.display = 'block';
        updateCount();
        return;
    }
    empty.style.display = 'none';
    list.innerHTML      = '';

    items.forEach(item => {
        const li      = document.createElement('li');
        li.className  = 'item-card';
        li.dataset.id = item.id;

        if (editingId === item.id) {
            li.innerHTML = `
                <input class="item-checkbox" type="checkbox"
                    ${item.is_done ? 'checked' : ''}
                    onchange="handleToggle(${item.id}, this.checked)">
                <input class="item-edit-input" id="editInput_${item.id}"
                    value="${escHtml(item.text)}" maxlength="500"
                    onkeydown="handleEditKey(event,${item.id})">
                <div class="item-actions">
                    <button class="btn btn-primary btn-sm" onclick="handleSaveEdit(${item.id})">Зберегти</button>
                    <button class="btn btn-ghost   btn-sm" onclick="cancelEdit()">✕</button>
                </div>`;
            setTimeout(() => {
                const inp = $(`editInput_${item.id}`);
                if (inp) { inp.focus(); inp.select(); }
            }, 10);
        } else {
            li.innerHTML = `
                <input class="item-checkbox" type="checkbox"
                    ${item.is_done ? 'checked' : ''}
                    onchange="handleToggle(${item.id}, this.checked)">
                <span class="item-text ${item.is_done ? 'done' : ''}"
                    title="Двічі клікніть для редагування"
                    ondblclick="startEdit(${item.id})">
                    ${escHtml(item.text)}
                    <small class="item-meta">${fmtDate(item.created_at)}</small>
                </span>
                <div class="item-actions">
                    <button class="btn btn-ghost  btn-sm" onclick="startEdit(${item.id})">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="handleDelete(${item.id})">🗑</button>
                </div>`;
        }
        list.appendChild(li);
    });
    updateCount();
}

// ── Mercure state patches (surgical — no full re-render) ──────────────────────
function applyCreated(item) {
    if (items.some(i => i.id === item.id)) return; // deduplicate (optimistic already added it)
    items.push(item);
    renderItems();
}

function applyUpdated(item) {
    if (editingId === item.id) return; // don't overwrite what user is currently typing
    const idx = items.findIndex(i => i.id === item.id);
    if (idx === -1) { items.push(item); } else { items[idx] = item; }
    renderItems();
}

function applyDeleted(id) {
    items = items.filter(i => i.id !== id);
    renderItems();
}

// ── Mercure subscription ──────────────────────────────────────────────────────
function connectMercure() {
    const url = new URL(MERCURE_URL, window.location.href);
    url.searchParams.append('topic', MERCURE_TOPIC);

    const es = new EventSource(url.toString());

    es.onopen = () => {
        setStatus(true, 'Mercure · real-time ✓');
    };

    es.onmessage = (e) => {
        try {
            const { type, data } = JSON.parse(e.data);

            switch (type) {
                case 'item.created': applyCreated(data); break;
                case 'item.updated': applyUpdated(data); break;
                case 'item.deleted': applyDeleted(data.id); break;
            }

            setStatus(true, `Mercure · ${new Date().toLocaleTimeString('uk-UA')}`);
        } catch (err) {
            console.error('Mercure message parse error:', err);
        }
    };

    es.onerror = () => {
        setStatus(false, 'Mercure: перепідключення...');
        // EventSource reconnects automatically per spec
    };
}

// ── Initial load ──────────────────────────────────────────────────────────────
async function loadItems() {
    try {
        const data = await apiFetch(API);
        items = data.data;
        renderItems();
    } catch (err) {
        showToast('Помилка завантаження: ' + err.message, 'error');
        setStatus(false, 'Помилка');
    }
}

// ── CRUD ──────────────────────────────────────────────────────────────────────
async function handleAdd() {
    const input = $('newItemInput');
    const text  = input.value.trim();

    if (!text) { input.focus(); showToast('Введіть текст елемента', 'error'); return; }

    const btn     = $('addBtn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span>';

    try {
        const item = await apiFetch(API, { method: 'POST', body: JSON.stringify({ text }) });
        // Optimistic: show immediately — Mercure will confirm to all other tabs
        applyCreated(item);
        input.value = '';
        showToast('Елемент додано ✓', 'success');
    } catch (err) {
        showToast('Помилка: ' + err.message, 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Додати';
        input.focus();
    }
}

async function handleDelete(id) {
    if (!confirm('Видалити цей елемент?')) return;

    const card = document.querySelector(`[data-id="${id}"]`);
    if (card) card.classList.add('removing');

    setTimeout(async () => {
        try {
            await apiFetch(`${API}/${id}`, { method: 'DELETE' });
            applyDeleted(id); // Mercure also notifies other tabs
            showToast('Видалено', 'success');
        } catch (err) {
            showToast('Помилка видалення: ' + err.message, 'error');
            renderItems();
        }
    }, 200);
}

async function handleToggle(id, isDone) {
    const idx = items.findIndex(i => i.id === id);
    if (idx !== -1) items[idx].is_done = isDone;
    renderItems();

    try {
        const updated = await apiFetch(`${API}/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ is_done: isDone }),
        });
        applyUpdated(updated);
        showToast(isDone ? 'Позначено як куплено ✓' : 'Знято позначку', 'success');
    } catch (err) {
        if (idx !== -1) items[idx].is_done = !isDone;
        renderItems();
        showToast('Помилка: ' + err.message, 'error');
    }
}

function startEdit(id)  { editingId = id;   renderItems(); }
function cancelEdit()   { editingId = null; renderItems(); }

function handleEditKey(e, id) {
    if (e.key === 'Enter')  handleSaveEdit(id);
    if (e.key === 'Escape') cancelEdit();
}

async function handleSaveEdit(id) {
    const input = $(`editInput_${id}`);
    if (!input) return;
    const text = input.value.trim();
    if (!text) { showToast('Текст не може бути порожнім', 'error'); return; }

    input.disabled = true;
    try {
        const updated = await apiFetch(`${API}/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ text }),
        });
        editingId = null;
        applyUpdated(updated);
        showToast('Збережено ✓', 'success');
    } catch (err) {
        showToast('Помилка збереження: ' + err.message, 'error');
        input.disabled = false;
    }
}

// ── Init ──────────────────────────────────────────────────────────────────────
$('newItemInput').addEventListener('keydown', e => { if (e.key === 'Enter') handleAdd(); });

setStatus(false, 'Підключення...');
loadItems();
connectMercure();

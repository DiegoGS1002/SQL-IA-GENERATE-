const $ = (selector) => document.querySelector(selector);
const state = { connections: [], conversations: [], connectionId: null, conversationId: null, busy: false };
const csrf = $('meta[name="csrf-token"]').content;

async function api(path, method = 'GET', payload) {
    const response = await fetch(`/api/v1${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: payload ? JSON.stringify(payload) : undefined,
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Falha na operação.');
    return data;
}

function notice(message) { $('#notice').textContent = message; $('#notice').hidden = false; }
function settings(open) { $('#settings-panel').hidden = !open; $('#chat-panel').hidden = open; }
function setConnection(id) {
    state.connectionId = id;
    const connection = state.connections.find((item) => item.id === id);
    $('#connection-label').textContent = connection?.label || 'Nenhuma conexão';
    $('#connection-button').classList.toggle('is-connected', connection?.status === 'connected');
}
function resetChat() {
    state.conversationId = null;
    $('#conversation-title').textContent = 'Nova conversa';
    $('#messages').replaceChildren();
    $('#empty-state').hidden = false;
    settings(false);
    renderConversations();
    $('#prompt').focus();
}
function renderConversations() {
    const list = $('#conversation-list');
    list.replaceChildren();
    const term = $('#chat-search').value.toLocaleLowerCase();
    for (const chat of state.conversations.filter((item) => item.title.toLocaleLowerCase().includes(term))) {
        const row = document.createElement('div');
        row.className = `conversation-row ${chat.id === state.conversationId ? 'selected' : ''}`;
        const open = document.createElement('button');
        open.className = 'conversation-open'; open.textContent = chat.title;
        open.onclick = () => openConversation(chat.id);
        const more = document.createElement('button');
        more.className = 'row-menu'; more.textContent = '···'; more.title = 'Renomear ou excluir';
        more.onclick = async () => {
            const action = window.prompt('Digite R para renomear ou E para excluir.');
            try {
                if (action?.toUpperCase() === 'R') {
                    const title = window.prompt('Novo nome:', chat.title)?.trim();
                    if (title) await api(`/conversations/${chat.id}`, 'PATCH', { title });
                } else if (action?.toUpperCase() === 'E' && window.confirm('Excluir esta conversa?')) {
                    await api(`/conversations/${chat.id}`, 'DELETE');
                    if (chat.id === state.conversationId) resetChat();
                }
                await loadConversations();
            } catch (error) { window.alert(error.message); }
        };
        row.append(open, more); list.append(row);
    }
}
function messageElement(message) {
    const article = document.createElement('article');
    article.className = `message ${message.role}`;
    const avatar = document.createElement('div'); avatar.className = 'message-avatar'; avatar.textContent = message.role === 'user' ? 'EU' : 'C';
    const body = document.createElement('div'); body.className = 'message-content';
    const name = document.createElement('strong'); name.textContent = message.role === 'user' ? 'Você' : 'CoreD';
    const text = document.createElement('p'); text.textContent = message.content;
    body.append(name, text);
    if (message.generation?.sql) {
        const badge = document.createElement('span'); badge.className = 'validation-badge'; badge.textContent = 'Rascunho · revisão necessária';
        const block = document.createElement('div'); block.className = 'sql-block';
        const head = document.createElement('div'); head.className = 'sql-heading'; head.textContent = 'SQL · Firebird 3.0';
        const copy = document.createElement('button'); copy.textContent = 'Copiar SQL';
        copy.onclick = async () => { await navigator.clipboard.writeText(message.generation.sql); copy.textContent = 'Copiado'; };
        head.append(copy);
        const pre = document.createElement('pre'); pre.textContent = message.generation.sql;
        block.append(head, pre); body.append(badge, block);
        if (message.generation.parameters?.length) {
            const params = document.createElement('p'); params.textContent = `Parâmetros: ${message.generation.parameters.join(', ')}`; body.append(params);
        }
        for (const warning of message.generation.warnings || []) {
            const item = document.createElement('p'); item.className = 'warning'; item.textContent = warning; body.append(item);
        }
    }
    article.append(avatar, body);
    return article;
}
async function openConversation(id) {
    const chat = await api(`/conversations/${id}`);
    state.conversationId = id;
    setConnection(chat.firebird_connection_id);
    $('#conversation-title').textContent = chat.title;
    $('#messages').replaceChildren(...chat.messages.map(messageElement));
    $('#empty-state').hidden = chat.messages.length > 0;
    settings(false); renderConversations();
    $('#messages').scrollTop = $('#messages').scrollHeight;
}
async function loadConversations() { state.conversations = await api('/conversations'); renderConversations(); }
async function loadConnections() {
    state.connections = await api('/connections');
    const list = $('#connections-list'); list.replaceChildren();
    if (!state.connections.length) { const empty = document.createElement('p'); empty.textContent = 'Nenhuma conexão cadastrada.'; list.append(empty); }
    for (const connection of state.connections) {
        const row = document.createElement('div'); row.className = 'connection-item';
        const title = document.createElement('strong'); title.textContent = connection.label;
        const info = document.createElement('small'); info.textContent = `${connection.host}:${connection.port} · ${connection.status}`;
        const actions = document.createElement('div');
        for (const [label, action] of [
            ['Usar', async () => { setConnection(connection.id); resetChat(); }],
            ['Testar', async () => { const result = await api(`/connections/${connection.id}/test`, 'POST'); notice(`Conectado ao Firebird ${result.version}.`); await loadConnections(); }],
            ['Sincronizar esquema', async () => { const result = await api(`/connections/${connection.id}/sync`, 'POST'); notice(`Esquema sincronizado: ${result.tables} tabelas ou views.`); await loadConnections(); }],
            ['Ver esquema', async () => {
                const result = await api(`/connections/${connection.id}/schema`);
                const preview = $('#schema-preview'); preview.replaceChildren();
                const heading = document.createElement('h2'); heading.textContent = `Esquema de ${connection.label}`; preview.append(heading);
                for (const table of result.catalog.tables) {
                    const item = document.createElement('p');
                    item.textContent = `${table.name} · ${table.kind} · ${table.columns.map((column) => column.name).join(', ')}`;
                    preview.append(item);
                }
                preview.hidden = false;
            }],
        ]) {
            const button = document.createElement('button'); button.textContent = label;
            button.onclick = async () => { try { await action(); } catch (error) { notice(error.message); } };
            actions.append(button);
        }
        row.append(title, info, actions); list.append(row);
    }
    if (state.connectionId) setConnection(state.connectionId);
}

$('#new-chat').onclick = resetChat;
$('#settings-button').onclick = () => settings($('#settings-panel').hidden);
$('#connection-button').onclick = () => settings(true);
$('#chat-search').oninput = renderConversations;
$('#pick-file').onclick = async () => {
    if (!window.coreD?.pickDatabaseFile) { notice('A seleção de arquivo funciona no aplicativo Electron. Você também pode informar o caminho no servidor.'); return; }
    const path = await window.coreD.pickDatabaseFile();
    if (path) $('#connection-form').elements.database_path.value = path;
};
$('#connection-form').onsubmit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form)); data.port = Number(data.port);
    try {
        const connection = await api('/connections', 'POST', data);
        form.reset(); form.elements.host.value = '127.0.0.1'; form.elements.port.value = '3050';
        await loadConnections(); setConnection(connection.id);
        notice('Conexão salva. Teste e sincronize o esquema antes de gerar SQL.');
    } catch (error) { notice(error.message); }
};
document.querySelectorAll('[data-prompt]').forEach((button) => button.onclick = () => { $('#prompt').value = button.dataset.prompt; $('#prompt').focus(); });
$('#prompt').onkeydown = (event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); $('#composer').requestSubmit(); } };
$('#composer').onsubmit = async (event) => {
    event.preventDefault();
    const content = $('#prompt').value.trim();
    if (!content || state.busy) return;
    if (!state.connectionId) { settings(true); notice('Cadastre e selecione uma conexão Firebird.'); return; }
    state.busy = true; $('#send-button').disabled = true; $('#prompt').value = '';
    try {
        if (!state.conversationId) {
            const chat = await api('/conversations', 'POST', { firebird_connection_id: state.connectionId, title: content.slice(0, 75) });
            state.conversationId = chat.id; $('#conversation-title').textContent = chat.title;
        }
        $('#empty-state').hidden = true;
        $('#messages').append(messageElement({ role: 'user', content }));
        const reply = await api(`/conversations/${state.conversationId}/messages`, 'POST', { content });
        $('#messages').append(messageElement(reply));
        await loadConversations(); $('#messages').scrollTop = $('#messages').scrollHeight;
    } catch (error) { $('#messages').append(messageElement({ role: 'assistant', content: error.message })); }
    finally { state.busy = false; $('#send-button').disabled = false; }
};

Promise.all([loadConnections(), loadConversations()]).catch((error) => window.alert(error.message));

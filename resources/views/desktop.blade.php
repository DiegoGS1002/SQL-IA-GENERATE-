<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CoreD SQL Generator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" class="desktop-shell">
        <aside class="sidebar">
            <div class="brand"><img src="{{ asset('Logo.png') }}" alt="RelatorIA" class="brand-logo"><span>CoreD SQL Generator</span></div>
            <button id="new-chat" class="primary-button sidebar-action" type="button">＋ &nbsp; Novo chat</button>
            <label class="search-field"><span>⌕</span><input id="chat-search" placeholder="Pesquisar conversas" aria-label="Pesquisar conversas"></label>
            <div class="sidebar-heading">CONVERSAS</div>
            <nav id="conversation-list" class="conversation-list" aria-label="Conversas"></nav>
            <div class="sidebar-footer"><button id="settings-button" class="ghost-button" type="button">⚙ &nbsp; Configurações</button><div class="local-tag"><span class="status-dot"></span> Aplicativo local</div></div>
        </aside>
        <main class="workspace">
            <header class="topbar"><div class="topbar-title"><strong id="conversation-title">Nova conversa</strong><span>Assistente Firebird 3.0</span></div><button id="connection-button" class="connection-pill" type="button"><span class="status-dot"></span><span id="connection-label">Nenhuma conexão</span><span>⌄</span></button></header>
            <section id="chat-panel" class="chat-panel">
                <div id="messages" class="messages"></div>
                <div id="empty-state" class="empty-state"><div class="empty-icon">&lt;/&gt;</div><span class="eyebrow">SEU ESPAÇO DE CONSULTAS</span><h1>Dados em perguntas.<br><em>Respostas em SQL.</em></h1><p>Descreva o relatório que você precisa. O CoreD consulta o esquema da sua conexão Firebird antes de propor uma consulta.</p><div class="suggestions"><button type="button" data-prompt="Liste as tabelas disponíveis e explique quais podem ser usadas em relatórios.">Explorar o esquema <span>↗</span></button><button type="button" data-prompt="Preciso de um relatório mensal. Quais tabelas e campos permitem criá-lo?">Criar relatório mensal <span>↗</span></button></div></div>
                <div class="composer-wrap"><form id="composer" class="composer"><textarea id="prompt" rows="2" placeholder="Descreva o relatório que deseja gerar..." aria-label="Pedido de relatório"></textarea><div class="composer-bottom"><span>Enter para enviar · Shift+Enter para nova linha</span><button id="send-button" type="submit" aria-label="Enviar">↑</button></div></form><p class="composer-note">SQL gerado para revisão. Nenhuma consulta é executada automaticamente.</p></div>
            </section>
            <section id="settings-panel" class="settings-panel" hidden>
                <div class="settings-header"><span class="eyebrow">PREFERÊNCIAS</span><h1>Conexões Firebird</h1><p>Configure uma fonte de dados para que o assistente use somente o esquema real do seu banco.</p></div>
                <div class="settings-grid"><form id="connection-form" class="settings-card"><h2>Nova conexão</h2><div class="form-grid"><label>Nome da conexão<input name="label" placeholder="Ex.: ERP principal" required maxlength="120"></label><label>Host<input name="host" value="127.0.0.1" required></label><label>Porta<input name="port" type="number" value="3050" min="1" max="65535" required></label><label>Charset<select name="charset"><option>UTF8</option><option>WIN1252</option><option>ISO8859_1</option><option>NONE</option></select></label><label class="full-width">Caminho no servidor ou alias<div class="input-action"><input name="database_path" placeholder="/dados/empresa.fdb" required><button id="pick-file" type="button">Selecionar .fdb</button></div></label><label>Usuário de leitura<input name="username" autocomplete="off" required></label><label>Senha<input name="password" type="password" autocomplete="new-password" required></label></div><p class="field-help">O caminho é interpretado pelo servidor Firebird. Para servidor remoto, informe o caminho no servidor ou um alias.</p><button class="primary-button" type="submit">Salvar conexão</button></form><div class="settings-card connection-card"><h2>Fontes cadastradas</h2><div id="connections-list" class="connections-list"></div></div></div>
                <div id="notice" class="notice" role="status" hidden></div>
                <div id="schema-preview" class="settings-card schema-preview" hidden></div>
            </section>
        </main>
    </div>
</body>
</html>

# CoreD SQL Generator

Aplicativo local para criar rascunhos de consultas SQL para **Firebird 3.x** a partir de pedidos em linguagem natural. O CoreD lê a estrutura da fonte cadastrada, envia o catálogo e o contexto da conversa ao serviço da OpenAI e apresenta o SQL gerado para revisão. **O aplicativo não executa o SQL gerado no Firebird.**

O sistema usa dois bancos com funções diferentes:

| Banco | Finalidade |
| --- | --- |
| MySQL | Guarda conexões cadastradas, esquemas sincronizados, conversas e gerações. Roda no Docker e é acessado pelo PHP local em `127.0.0.1:33316`. |
| Firebird 3.x | Fonte de dados do usuário. O aplicativo testa a conexão e lê metadados para montar o catálogo; não executa os relatórios gerados. |

## Requisitos

- PHP 8.4 com as extensões exigidas pelo Composer, `pdo_mysql` e **`pdo_firebird`** para testar e sincronizar fontes Firebird. O PHP usado pelo Electron deve ter essas extensões.
- Composer.
- Node.js 22 e npm.
- Docker com Docker Compose em execução para o MySQL.
- Acesso a um servidor Firebird 3.x e uma chave de API da OpenAI para gerar SQL.

Confira as extensões do PHP com `php -m`. Sem `pdo_firebird`, a interface e o cadastro funcionam, mas as ações **Testar** e **Sincronizar esquema** retornam erro. A imagem PHP de `compose.yaml` contém `pdo_mysql`, mas não instala `pdo_firebird`; o fluxo de uso do Firebird é o aplicativo com PHP local.

## Instalação no Windows

Execute os comandos no PowerShell, na raiz do projeto. A porta 33316 evita conflito com outro MySQL na porta 3306.

```powershell
composer install
npm install
if (-not (Test-Path .env)) {
    Copy-Item .env.desktop.example .env
    php artisan key:generate --no-interaction
}
docker compose up --wait --wait-timeout 90 mysql
php artisan migrate --force --no-interaction
npm run build
.\desktop.ps1 run
```

Se `.env` já existir, **não o substitua**: preserve `APP_KEY` e as credenciais atuais. Confira nele `DB_HOST=127.0.0.1`, `DB_PORT=33316` e `FORWARD_DB_PORT=33316`. `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` devem corresponder aos valores usados pelo serviço MySQL em `compose.yaml`. Para outra porta livre, altere `DB_PORT` e `FORWARD_DB_PORT` para o mesmo número antes de iniciar o contêiner.

Preencha `OPENAI_API_KEY` no `.env` para habilitar a geração de SQL. `OPENAI_MODEL` é opcional; o valor do exemplo é `gpt-4.1-mini`. Mantenha `.env` fora do controle de versão. Se alterar `APP_KEY` depois de cadastrar conexões, as senhas Firebird armazenadas com criptografia deixarão de ser legíveis.

O comando `.\desktop.ps1 run` inicia o Electron. A cada abertura, o Electron executa `docker compose up --wait mysql`, espera o banco ficar saudável e então inicia o servidor Laravel em uma porta local disponível. O Docker deve estar em execução. O comando `run` não instala dependências nem aplica migrações.

Também é possível usar `npm run desktop` após a instalação. O script `.\desktop.ps1 build` recompila os arquivos da interface; ele **não** cria um instalador ou inclui PHP no aplicativo.

### Uso no navegador local

Para trabalhar sem a janela do Electron, inicie o MySQL e o Laravel separadamente:

```bash
docker compose up --wait --wait-timeout 90 mysql
php artisan serve --host=127.0.0.1 --port=8000 --no-interaction
```

Abra o endereço local exibido pelo Artisan. Nesse modo, informe o caminho ou alias do banco Firebird manualmente: o botão **Selecionar .fdb** depende do Electron. A aplicação aceita requisições apenas da própria máquina.

## Como usar

1. Em **Preferências → Conexões Firebird**, cadastre um nome, host, porta, caminho ou alias do banco, usuário, senha e charset. O caminho é interpretado pelo servidor Firebird; para servidor remoto, use o caminho nesse servidor ou um alias configurado nele.
2. Na conexão cadastrada, clique em **Testar**. O aplicativo verifica a conexão e exige Firebird 3.x.
3. Clique em **Sincronizar esquema**. O catálogo registra tabelas, views, colunas, chaves e relações declaradas. Use **Ver esquema** para inspecionar os dados encontrados.
4. Clique em **Usar**, crie uma conversa e descreva o relatório desejado. Cada conversa permanece vinculada à fonte escolhida quando foi criada.
5. Revise a explicação, os avisos, os parâmetros e o SQL exibidos. O botão **Copiar SQL** apenas copia o texto; execute uma versão revisada somente na ferramenta apropriada para seu Firebird.

É possível pesquisar, renomear e excluir conversas. O histórico, os catálogos e os rascunhos ficam no MySQL local. As senhas das conexões Firebird são criptografadas pela aplicação e não são incluídas nas respostas da API.

## Limites da geração

- O catálogo precisa estar sincronizado e `OPENAI_API_KEY` precisa estar configurada antes da primeira geração.
- O CoreD solicita consultas de leitura e examina o texto retornado: exige início com `SELECT`, rejeita comentários, múltiplas instruções, comandos de alteração e tabelas ausentes do catálogo. Essa triagem **não é um analisador SQL completo**; cada resultado continua sendo um rascunho para revisão.
- Se a validação falhar, a resposta mantém a explicação e os avisos, mas não apresenta SQL copiável.
- Pedidos que incluam o formato especial `--SELECT` podem receber esse mesmo formato, acompanhado de aviso de que a linha não é SQL executável diretamente no Firebird.
- O catálogo e o pedido são enviados à API da OpenAI durante a geração. Avalie os dados que serão compartilhados antes de usar uma fonte real.

## API local

As rotas JSON usam o prefixo `/api/v1` e são consumidas pela interface. Elas fazem parte do grupo web do Laravel e usam a sessão e a proteção CSRF do aplicativo; não foram preparadas como API pública remota.

| Método | Rota | Função |
| --- | --- | --- |
| `GET`, `POST` | `/connections` | Listar ou cadastrar fontes Firebird. |
| `POST` | `/connections/{connection}/test` | Testar a fonte e verificar a versão. |
| `POST` | `/connections/{connection}/sync` | Ler e salvar um novo catálogo. |
| `GET` | `/connections/{connection}/schema` | Consultar o catálogo salvo; aceita `?query=` para filtrar tabelas e colunas. |
| `GET`, `POST` | `/conversations` | Listar ou criar conversas. |
| `GET`, `PATCH`, `DELETE` | `/conversations/{conversation}` | Ler, renomear ou excluir uma conversa. |
| `POST` | `/conversations/{conversation}/messages` | Enviar um pedido e receber a resposta com o rascunho, quando houver. |

## Desenvolvimento e manutenção

| Caminho | Responsabilidade |
| --- | --- |
| `electron/` e `desktop.ps1` | Janela desktop, servidor local e inicialização do MySQL. |
| `resources/views/desktop.blade.php` e `resources/js/app.js` | Interface de conversas e conexões. |
| `app/Services/Firebird/` | Conexão e leitura do esquema Firebird. |
| `app/Services/Reports/` | Chamada à OpenAI e triagem do SQL retornado. |
| `routes/web.php` e `app/Http/Controllers/` | Página local e endpoints JSON. |
| `database/migrations/` | Estrutura do banco MySQL da aplicação. |

Comandos úteis:

```bash
docker compose ps
php artisan migrate:status --no-interaction
php artisan test --compact
npm run build
```

Os testes automatizados usam SQLite em memória, conforme `phpunit.xml`; eles não substituem um teste de conexão com seu servidor Firebird. Para parar os contêineres, use `docker compose down`. Esse comando preserva o volume `geradorsql-mysql-data`, onde ficam os dados do MySQL.

### Problemas comuns

| Sintoma | Verificação |
| --- | --- |
| Electron informa que não conseguiu iniciar o MySQL | Inicie o Docker e confira `docker compose ps`. Veja se a porta configurada em `FORWARD_DB_PORT` está livre. |
| Laravel não conecta ao MySQL | Confirme `DB_HOST=127.0.0.1`, `DB_PORT=33316`, credenciais e estado saudável do contêiner. O nome `mysql` só funciona para o PHP que roda dentro da rede do Compose. |
| Falta a tabela de sessões ou conversas | Execute `php artisan migrate --force --no-interaction` depois de iniciar o MySQL. |
| Testar ou sincronizar informa que falta `pdo_firebird` | Ative a extensão no mesmo PHP indicado por `php --ini` e confirme com `php -m`. |
| Não aparece SQL na conversa | Sincronize o esquema, configure `OPENAI_API_KEY` e leia os avisos da resposta. |
| Arquivos da interface não aparecem | Execute `npm run build`. |

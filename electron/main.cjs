const { app, BrowserWindow, dialog, ipcMain, shell } = require('electron');
const { spawn } = require('node:child_process');
const net = require('node:net');
const path = require('node:path');

let server;
let window;
let serverError;

async function availablePort() {
    return new Promise((resolve, reject) => {
        const probe = net.createServer();
        probe.once('error', reject);
        probe.listen(0, '127.0.0.1', () => {
            const port = probe.address().port;
            probe.close(() => resolve(port));
        });
    });
}

async function waitForServer(url) {
    for (let attempt = 0; attempt < 50; attempt += 1) {
        if (serverError) throw serverError;
        if (server?.exitCode !== null) throw new Error('O processo Laravel terminou antes de iniciar.');
        try {
            const response = await fetch(url);
            if (response.ok) return;
        } catch { /* Waiting for the local server. */ }
        await new Promise((resolve) => setTimeout(resolve, 150));
    }
    throw new Error('O servidor local não iniciou a tempo.');
}

async function startDatabase(root) {
    await new Promise((resolve, reject) => {
        const database = spawn('docker', ['compose', 'up', '--wait', '--wait-timeout', '90', 'mysql'], {
            cwd: root,
            windowsHide: true,
        });
        let errorOutput = '';

        database.stderr.on('data', (chunk) => { errorOutput += chunk.toString(); });
        database.once('error', (error) => { reject(new Error(`Não foi possível iniciar o MySQL pelo Docker: ${error.message}`)); });
        database.once('close', (code) => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Não foi possível iniciar o MySQL pelo Docker. Verifique se o Docker está em execução. ${errorOutput.trim()}`));
            }
        });
    });
}

async function createWindow() {
    const root = path.resolve(__dirname, '..');
    await startDatabase(root);
    const port = await availablePort();
    const url = `http://127.0.0.1:${port}/`;
    server = spawn(process.env.CORED_PHP_BINARY || 'php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`, '--no-interaction'], {
        cwd: root,
        env: { ...process.env, APP_URL: url },
        stdio: 'ignore',
        windowsHide: true,
    });
    serverError = null;
    server.once('error', (error) => { serverError = error; });
    await waitForServer(url);

    window = new BrowserWindow({
        width: 1380,
        height: 900,
        minWidth: 900,
        minHeight: 640,
        title: 'CoreD SQL Generator',
        backgroundColor: '#0b1220',
        webPreferences: {
            preload: path.join(__dirname, 'preload.cjs'),
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: true,
        },
    });
    window.webContents.setWindowOpenHandler(({ url: target }) => {
        if (target.startsWith('https://')) shell.openExternal(target);
        return { action: 'deny' };
    });
    window.webContents.on('will-navigate', (event, target) => {
        if (!target.startsWith(url)) event.preventDefault();
    });
    window.on('closed', () => { server?.kill(); server = null; window = null; });
    await window.loadURL(url);
}

ipcMain.handle('database:pick-file', async () => {
    const result = await dialog.showOpenDialog(window, {
        title: 'Selecione o banco Firebird',
        properties: ['openFile'],
        filters: [{ name: 'Banco Firebird', extensions: ['fdb'] }],
    });
    return result.canceled ? null : result.filePaths[0];
});

app.whenReady().then(createWindow).catch((error) => {
    dialog.showErrorBox('CoreD SQL Generator', error.message);
    app.quit();
});
app.on('before-quit', () => server?.kill());
app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
app.on('activate', () => { if (BrowserWindow.getAllWindows().length === 0) createWindow(); });

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('coreD', {
    pickDatabaseFile: () => ipcRenderer.invoke('database:pick-file'),
});

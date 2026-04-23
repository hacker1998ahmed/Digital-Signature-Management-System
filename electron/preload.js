/**
 * Digital Signature Factory - Electron Preload Script
 * Secure bridge between renderer and main process
 * 
 * @version 2.0.0
 */

const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods to renderer process
contextBridge.exposeInMainWorld('electronAPI', {
    // File operations
    openFileDialog: (options) => ipcRenderer.invoke('open-file-dialog', options),
    saveFileDialog: (options) => ipcRenderer.invoke('save-file-dialog', options),
    
    // Settings
    getSettings: () => ipcRenderer.invoke('get-settings'),
    setSetting: (key, value) => ipcRenderer.invoke('set-setting', key, value),
    
    // Token operations
    detectTokensNative: () => ipcRenderer.invoke('detect-tokens-native'),
    
    // Certificate operations
    exportCertificate: (certId, options) => ipcRenderer.invoke('export-certificate', certId, options),
    
    // Notifications
    showNotification: (options) => ipcRenderer.invoke('show-notification', options),
    
    // Auto-start
    setAutoStart: (enabled) => ipcRenderer.invoke('set-auto-start', enabled),
    
    // Updates
    checkForUpdates: () => ipcRenderer.invoke('check-for-updates'),
    restartApp: () => ipcRenderer.invoke('restart-app'),
    
    // Event listeners
    onUpdateStatus: (callback) => {
        ipcRenderer.on('update-status', (event, data) => callback(data));
    },
    onScanTokens: (callback) => {
        ipcRenderer.on('scan-tokens', () => callback());
    },
    onSignDocument: (callback) => {
        ipcRenderer.on('sign-document', () => callback());
    },
    onBulkSign: (callback) => {
        ipcRenderer.on('bulk-sign', () => callback());
    },
    onViewCertificates: (callback) => {
        ipcRenderer.on('view-certificates', () => callback());
    },
    onOpenSettings: (callback) => {
        ipcRenderer.on('open-settings', () => callback());
    },
    onOpenHelp: (callback) => {
        ipcRenderer.on('open-help', () => callback());
    },
    
    // Platform info
    platform: process.platform,
    isDev: !process.resourcesPath
});

// Log for debugging (remove in production)
console.log('Preload script loaded');

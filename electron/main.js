/**
 * Digital Signature Factory - Electron Main Process
 * Desktop Application Entry Point
 * 
 * @version 2.0.0
 */

const { app, BrowserWindow, ipcMain, dialog, Menu, Tray, nativeImage } = require('electron');
const path = require('path');
const fs = require('fs');
const Store = require('electron-store');
const { autoUpdater } = require('electron-updater');
const fetch = require('node-fetch');

// Initialize store for persistent data
const store = new Store({
    schema: {
        apiBaseUrl: {
            type: 'string',
            default: 'http://localhost:8000'
        },
        autoStart: {
            type: 'boolean',
            default: false
        },
        minimizeToTray: {
            type: 'boolean',
            default: true
        },
        language: {
            type: 'string',
            default: 'ar'
        },
        lastTokenId: {
            type: 'string',
            default: ''
        }
    }
});

let mainWindow;
let tray = null;
let isQuitting = false;

// Check if running in development mode
const isDev = process.env.NODE_ENV === 'development' || !app.isPackaged;

/**
 * Create main application window
 */
function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1400,
        height: 900,
        minWidth: 1024,
        minHeight: 768,
        title: 'Digital Signature Factory',
        icon: path.join(__dirname, 'build/icon.png'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js'),
            spellcheck: false
        },
        show: false,
        backgroundColor: '#1a1a2e'
    });

    // Load application
    if (isDev) {
        mainWindow.loadURL('http://localhost:3000');
        mainWindow.webContents.openDevTools();
    } else {
        mainWindow.loadFile(path.join(__dirname, '../frontend/build/index.html'));
    }

    // Show window when ready
    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
        mainWindow.focus();
    });

    // Handle window close
    mainWindow.on('close', (event) => {
        if (!isQuitting && store.get('minimizeToTray')) {
            event.preventDefault();
            mainWindow.hide();
            if (process.platform !== 'darwin') {
                tray.displayBalloon({
                    title: 'Digital Signature Factory',
                    content: 'التطبيق يعمل في الخلفية\nApplication running in background'
                });
            }
        }
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    // Create application menu
    createMenu();
}

/**
 * Create system tray icon
 */
function createTray() {
    const iconPath = path.join(__dirname, 'build/tray-icon.png');
    const icon = nativeImage.createFromPath(iconPath).resize({ width: 16, height: 16 });
    
    tray = new Tray(icon);
    
    const contextMenu = Menu.buildFromTemplate([
        {
            label: 'فتح التطبيق / Open App',
            click: () => {
                mainWindow.show();
                mainWindow.focus();
            }
        },
        {
            label: 'فحص التوكن / Scan Token',
            click: () => {
                mainWindow.webContents.send('scan-tokens');
                mainWindow.show();
            }
        },
        { type: 'separator' },
        {
            label: 'إعدادات / Settings',
            click: () => {
                mainWindow.webContents.send('open-settings');
                mainWindow.show();
            }
        },
        { type: 'separator' },
        {
            label: 'خروج / Quit',
            click: () => {
                isQuitting = true;
                app.quit();
            }
        }
    ]);
    
    tray.setToolTip('Digital Signature Factory');
    tray.setContextMenu(contextMenu);
    
    tray.on('double-click', () => {
        mainWindow.show();
        mainWindow.focus();
    });
}

/**
 * Create application menu
 */
function createMenu() {
    const template = [
        {
            label: 'ملف / File',
            submenu: [
                {
                    label: 'توقيع مستند / Sign Document',
                    accelerator: 'CmdOrCtrl+S',
                    click: () => mainWindow.webContents.send('sign-document')
                },
                {
                    label: 'توقيع جماعي / Bulk Sign',
                    accelerator: 'CmdOrCtrl+B',
                    click: () => mainWindow.webContents.send('bulk-sign')
                },
                { type: 'separator' },
                {
                    label: 'إعدادات / Settings',
                    accelerator: 'CmdOrCtrl+,',
                    click: () => mainWindow.webContents.send('open-settings')
                },
                { type: 'separator' },
                {
                    label: 'خروج / Quit',
                    accelerator: 'CmdOrCtrl+Q',
                    click: () => app.quit()
                }
            ]
        },
        {
            label: 'توكن / Token',
            submenu: [
                {
                    label: 'فحص التوكن / Scan Tokens',
                    accelerator: 'F5',
                    click: () => mainWindow.webContents.send('scan-tokens')
                },
                {
                    label: 'عرض الشهادات / View Certificates',
                    accelerator: 'CmdOrCtrl+C',
                    click: () => mainWindow.webContents.send('view-certificates')
                },
                { type: 'separator' },
                {
                    label: 'تحديث / Refresh',
                    accelerator: 'CmdOrCtrl+R',
                    click: () => mainWindow.webContents.reload()
                }
            ]
        },
        {
            label: 'مساعدة / Help',
            submenu: [
                {
                    label: 'دليل الاستخدام / User Guide',
                    click: () => mainWindow.webContents.send('open-help')
                },
                {
                    label: 'حول / About',
                    click: () => {
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'حول Digital Signature Factory',
                            message: 'Digital Signature Factory v2.0.0',
                            detail: 'نظام إدارة التوقيع الإلكتروني المتكامل\nComplete Electronic Signature Management System\n\n© 2024 All Rights Reserved',
                            buttons: ['OK']
                        });
                    }
                }
            ]
        }
    ];

    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);
}

/**
 * Setup IPC handlers for renderer-main communication
 */
function setupIpcHandlers() {
    // File dialog
    ipcMain.handle('open-file-dialog', async (event, options) => {
        const result = await dialog.showOpenDialog(mainWindow, options);
        return result;
    });

    // Save file dialog
    ipcMain.handle('save-file-dialog', async (event, options) => {
        const result = await dialog.showSaveDialog(mainWindow, options);
        return result;
    });

    // Get app settings
    ipcMain.handle('get-settings', () => {
        return store.store;
    });

    // Update app settings
    ipcMain.handle('set-setting', (event, key, value) => {
        store.set(key, value);
        return true;
    });

    // Token detection (native USB scanning)
    ipcMain.handle('detect-tokens-native', async () => {
        try {
            // In production, this would interface with native PKCS#11 modules
            // For now, return mock data or call backend API
            const apiUrl = store.get('apiBaseUrl');
            const response = await fetch(`${apiUrl}/api/tokens/scan`, {
                headers: {
                    'Authorization': `Bearer ${event.sender.token}`
                }
            });
            
            if (response.ok) {
                return await response.json();
            }
            
            return { success: false, error: 'Failed to detect tokens' };
        } catch (error) {
            return { success: false, error: error.message };
        }
    });

    // Certificate export
    ipcMain.handle('export-certificate', async (event, certId, options) => {
        const result = await dialog.showSaveDialog(mainWindow, {
            title: 'تصدير الشهادة / Export Certificate',
            defaultPath: `certificate_${certId}.pem`,
            filters: [
                { name: 'PEM Files', extensions: ['pem'] },
                { name: 'DER Files', extensions: ['der'] },
                { name: 'PKCS#12 Files', extensions: ['pfx', 'p12'] },
                { name: 'All Files', extensions: ['*'] }
            ]
        });
        
        if (!result.canceled && result.filePath) {
            return { success: true, filePath: result.filePath };
        }
        
        return { success: false, canceled: true };
    });

    // Show notification
    ipcMain.handle('show-notification', (event, options) => {
        if (tray) {
            tray.displayBalloon(options);
        }
        return true;
    });

    // Auto-start on login
    ipcMain.handle('set-auto-start', (event, enabled) => {
        app.setLoginItemSettings({
            openAtLogin: enabled,
            path: app.getPath('exe')
        });
        store.set('autoStart', enabled);
        return true;
    });

    // Check for updates
    ipcMain.handle('check-for-updates', async () => {
        if (!isDev) {
            autoUpdater.checkForUpdates();
        }
        return { success: true, message: 'Checking for updates...' };
    });

    // Restart app
    ipcMain.handle('restart-app', () => {
        app.relaunch();
        app.exit(0);
    });
}

/**
 * Setup auto-updater events
 */
function setupAutoUpdater() {
    autoUpdater.on('checking-for-update', () => {
        if (mainWindow) {
            mainWindow.webContents.send('update-status', { status: 'checking' });
        }
    });

    autoUpdater.on('update-available', (info) => {
        if (mainWindow) {
            mainWindow.webContents.send('update-status', { 
                status: 'available',
                version: info.version 
            });
            
            dialog.showMessageBox(mainWindow, {
                type: 'info',
                title: 'تحديث متاح / Update Available',
                message: `إصدار جديد متاح: ${info.version}\nNew version available: ${info.version}`,
                detail: 'هل تريد تنزيل التحديث الآن؟\nDo you want to download the update now?',
                buttons: ['تنزيل / Download', 'لاحقاً / Later'],
                cancelId: 1
            }).then((result) => {
                if (result.response === 0) {
                    autoUpdater.downloadUpdate();
                }
            });
        }
    });

    autoUpdater.on('update-not-available', () => {
        if (mainWindow) {
            mainWindow.webContents.send('update-status', { status: 'not-available' });
        }
    });

    autoUpdater.on('update-downloaded', (info) => {
        if (mainWindow) {
            mainWindow.webContents.send('update-status', { 
                status: 'downloaded',
                version: info.version 
            });
            
            dialog.showMessageBox(mainWindow, {
                type: 'info',
                title: 'التحديث جاهز / Update Ready',
                message: 'تم تنزيل التحديث بنجاح\nUpdate downloaded successfully',
                detail: 'هل تريد إعادة التشغيل لتثبيت التحديث؟\nDo you want to restart to install the update?',
                buttons: ['إعادة التشغيل / Restart', 'لاحقاً / Later'],
                cancelId: 1
            }).then((result) => {
                if (result.response === 0) {
                    autoUpdater.quitAndInstall();
                }
            });
        }
    });

    autoUpdater.on('error', (err) => {
        if (mainWindow) {
            mainWindow.webContents.send('update-status', { 
                status: 'error',
                error: err.message 
            });
        }
    });
}

// App lifecycle events
app.whenReady().then(() => {
    createWindow();
    createTray();
    setupIpcHandlers();
    setupAutoUpdater();
    
    // Check for updates after 1 minute
    setTimeout(() => {
        if (!isDev) {
            autoUpdater.checkForUpdates();
        }
    }, 60000);
});

app.on('window-all-closed', (event) => {
    if (!store.get('minimizeToTray') || isQuitting) {
        app.quit();
    } else {
        event.preventDefault();
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    } else {
        mainWindow.show();
        mainWindow.focus();
    }
});

app.on('before-quit', () => {
    isQuitting = true;
});

// Handle second instance
const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) {
                mainWindow.restore();
            }
            mainWindow.show();
            mainWindow.focus();
        }
    });
}

// Security: Disable navigation to external URLs
app.on('web-contents-created', (event, contents) => {
    contents.on('will-navigate', (event, navigationUrl) => {
        const parsedUrl = new URL(navigationUrl);
        if (parsedUrl.origin !== 'http://localhost:3000' && !isDev) {
            event.preventDefault();
        }
    });
});

console.log('Digital Signature Factory started');

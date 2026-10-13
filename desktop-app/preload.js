'use strict';
// Puente seguro ventana ajustes <-> main (sin Node expuesto al renderer).
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('ecoruta', {
  getConfig: () => ipcRenderer.invoke('cfg-get'),
  saveConfig: (cfg) => ipcRenderer.invoke('cfg-save', cfg),
  resetConfig: () => ipcRenderer.invoke('cfg-reset'),
  testUrl: (url) => ipcRenderer.invoke('net-test', url),
  testApi: (base) => ipcRenderer.invoke('api-test', base),
  statusNow: () => ipcRenderer.invoke('status-now'),
  syncNow: () => ipcRenderer.invoke('sync-now'),
  ensureLocal: () => ipcRenderer.invoke('local-ensure'),
  lastAutostart: () => ipcRenderer.invoke('local-last'),
  detectRepo: () => ipcRenderer.invoke('repo-detect'),
  getBrand: () => ipcRenderer.invoke('brand-get'),
  saveBrand: (b) => ipcRenderer.invoke('brand-save', b),
  getLicense: () => ipcRenderer.invoke('license-get'),
  installLicense: () => ipcRenderer.invoke('license-install'),
});

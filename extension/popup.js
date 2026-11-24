// SocioWatch Jateng - Instagram Sync Companion (v1.4)
// Reactive popup UI with Dynamic Account Selector & 2x Daily Auto-Schedule

const DEFAULT_BACKEND_URL = 'http://localhost:8000';

// DOM Elements
const igStatusEl = document.getElementById('igStatus');
const fbStatusEl = document.getElementById('fbStatus');
const serverStatusEl = document.getElementById('serverStatus');
const btnStartSync = document.getElementById('btnStartSync');
const btnStopSync = document.getElementById('btnStopSync');
const progressWrapper = document.getElementById('progressWrapper');
const progressBar = document.getElementById('progressBar');
const progressPercent = document.getElementById('progressPercent');
const progressLabel = document.getElementById('progressLabel');

// Schedule Elements
const scheduleEnabledEl = document.getElementById('scheduleEnabled');
const scheduleBodyEl = document.getElementById('scheduleBody');
const scheduleTime1El = document.getElementById('scheduleTime1');
const scheduleTime2El = document.getElementById('scheduleTime2');
const scheduleScopeEl = document.getElementById('scheduleScope');
const btnSaveSchedule = document.getElementById('btnSaveSchedule');
const nextRunTimeEl = document.getElementById('nextRunTime');

// Account Selector Elements
const accountListEl = document.getElementById('accountList');
const accountCountBadge = document.getElementById('accountCountBadge');
const accountSearchEl = document.getElementById('accountSearch');
const filterChipsEl = document.getElementById('filterChips');
const btnSelectAll = document.getElementById('btnSelectAll');
const btnDeselectAll = document.getElementById('btnDeselectAll');
const btnRefreshAccounts = document.getElementById('btnRefreshAccounts');

// Log & Settings Elements
const logBox = document.getElementById('logBox');
const btnClearLog = document.getElementById('btnClearLog');
const toggleSettings = document.getElementById('toggleSettings');
const settingsPanel = document.getElementById('settingsPanel');
const backendUrlInput = document.getElementById('backendUrl');
const syncTokenInput = document.getElementById('syncToken');
const syncAllAccountsCheckbox = document.getElementById('syncAllAccounts');
const btnSaveSettings = document.getElementById('btnSaveSettings');
const btnTestServer = document.getElementById('btnTestServer');

let activeAccounts = [];
let selectedSet = new Set(); // lowercase usernames
let hasSavedSelection = false;
let currentFilter = 'all'; // 'all' | 'personal' | 'synced'
let searchQuery = '';
let lastKnownSyncing = false;

// ----------------- Initialization -----------------
document.addEventListener('DOMContentLoaded', async () => {
  await loadSettings();
  await restoreSyncState();
  await loadScheduleInfo();
  attachEventListeners();
  subscribeStorageUpdates();
  await checkInstagramSession();
  await checkFacebookSession();
  await loadTargetAccounts();
});

function attachEventListeners() {
  btnStartSync.addEventListener('click', handleStartSync);
  btnStopSync.addEventListener('click', handleStopSync);
  btnRefreshAccounts.addEventListener('click', () => loadTargetAccounts());

  // Schedule events
  scheduleEnabledEl.addEventListener('change', () => {
    scheduleBodyEl.style.display = scheduleEnabledEl.checked ? 'flex' : 'none';
    saveScheduleConfig();
  });
  btnSaveSchedule.addEventListener('click', saveScheduleConfig);
  scheduleScopeEl.addEventListener('change', saveScheduleConfig);

  // Account selector events
  accountSearchEl.addEventListener('input', (e) => {
    searchQuery = e.target.value.trim().toLowerCase();
    renderAccountList();
  });

  filterChipsEl.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    filterChipsEl.querySelectorAll('.chip').forEach((c) => c.classList.remove('active'));
    chip.classList.add('active');
    currentFilter = chip.getAttribute('data-filter') || 'all';
    renderAccountList();
  });

  btnSelectAll.addEventListener('click', () => {
    getFilteredAccounts().forEach((acc) => selectedSet.add(acc.username.toLowerCase()));
    persistSelection();
    renderAccountList();
  });

  btnDeselectAll.addEventListener('click', () => {
    getFilteredAccounts().forEach((acc) => selectedSet.delete(acc.username.toLowerCase()));
    persistSelection();
    renderAccountList();
  });

  // Logs & Settings
  btnClearLog.addEventListener('click', () => {
    chrome.runtime.sendMessage({ type: 'CLEAR_LOGS' });
    logBox.innerHTML = '';
  });
  toggleSettings.addEventListener('click', () => {
    const isVisible = settingsPanel.style.display === 'flex';
    settingsPanel.style.display = isVisible ? 'none' : 'flex';
  });
  btnSaveSettings.addEventListener('click', saveSettings);
  btnTestServer.addEventListener('click', testServerConnection);
}

// ----------------- Schedule Management -----------------
async function loadScheduleInfo() {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage({ type: 'GET_SCHEDULE_INFO' }, (res) => {
      if (chrome.runtime.lastError || !res || !res.ok) {
        nextRunTimeEl.textContent = 'Belum dijadwalkan';
        resolve();
        return;
      }
      const cfg = res.config || {};
      scheduleEnabledEl.checked = Boolean(cfg.enabled);
      scheduleBodyEl.style.display = cfg.enabled ? 'flex' : 'none';
      if (cfg.time1) scheduleTime1El.value = cfg.time1;
      if (cfg.time2) scheduleTime2El.value = cfg.time2;
      if (cfg.scope) scheduleScopeEl.value = cfg.scope;
      renderNextAlarm(res.nextAlarm);
      resolve();
    });
  });
}

function saveScheduleConfig() {
  const config = {
    enabled: scheduleEnabledEl.checked,
    time1: scheduleTime1El.value || '08:00',
    time2: scheduleTime2El.value || '20:00',
    scope: scheduleScopeEl.value || 'all_targets',
  };

  btnSaveSchedule.textContent = '...';
  chrome.runtime.sendMessage({ type: 'UPDATE_SCHEDULE', config }, (res) => {
    btnSaveSchedule.textContent = '✓';
    setTimeout(() => { btnSaveSchedule.textContent = 'Simpan'; }, 1000);
    if (res && res.ok) {
      renderNextAlarm(res.nextAlarm);
    }
  });
}

function renderNextAlarm(nextAlarm) {
  if (!scheduleEnabledEl.checked || !nextAlarm || !nextAlarm.scheduled || !nextAlarm.nextTime) {
    nextRunTimeEl.textContent = 'Nonaktif';
    return;
  }

  const dt = new Date(nextAlarm.nextTime);
  const now = new Date();
  const isToday =
    dt.getDate() === now.getDate() &&
    dt.getMonth() === now.getMonth() &&
    dt.getFullYear() === now.getFullYear();

  const hh = String(dt.getHours()).padStart(2, '0');
  const mm = String(dt.getMinutes()).padStart(2, '0');
  const dayLabel = isToday ? 'Hari ini' : 'Besok';
  nextRunTimeEl.textContent = `${dayLabel}, ${hh}:${mm}`;
}

// ----------------- Sync State Subscription -----------------
async function restoreSyncState() {
  const data = await chrome.storage.local.get(['syncState']);
  if (data.syncState) {
    renderSyncState(data.syncState);
  }
}

function subscribeStorageUpdates() {
  chrome.storage.onChanged.addListener((changes, areaName) => {
    if (areaName !== 'local' || !changes.syncState) return;
    const newState = changes.syncState.newValue;
    if (!newState) return;

    const wasSyncing = lastKnownSyncing;
    renderSyncState(newState);

    if (wasSyncing && !newState.isSyncing) {
      setTimeout(() => loadTargetAccounts(), 600);
    }
  });
}

function renderSyncState(state) {
  lastKnownSyncing = Boolean(state.isSyncing);

  if (Array.isArray(state.logs)) {
    logBox.innerHTML = '';
    for (const entry of state.logs) {
      const div = document.createElement('div');
      div.className = `log-entry log-${entry.type || 'info'}`;
      div.textContent = `[${entry.time || '--:--:--'}] ${entry.msg || ''}`;
      logBox.appendChild(div);
    }
    logBox.scrollTop = logBox.scrollHeight;
  }

  if (state.isSyncing) {
    btnStartSync.disabled = true;
    btnStartSync.innerHTML = '<span>Berjalan di latar belakang...</span>';
    btnStopSync.style.display = 'inline-flex';
    progressWrapper.style.display = 'flex';
    progressBar.style.width = `${state.percent || 0}%`;
    progressPercent.textContent = `${state.percent || 0}%`;
    progressLabel.textContent = state.label || 'Memproses...';
  } else {
    btnStopSync.style.display = 'none';
    updateStartButtonLabel();

    if (state.lastFinishedAt) {
      progressWrapper.style.display = 'flex';
      progressBar.style.width = `${state.percent || 100}%`;
      progressPercent.textContent = `${state.percent || 100}%`;
      progressLabel.textContent = state.label || 'Selesai!';
    } else {
      progressWrapper.style.display = 'none';
    }
  }
}

function updateStartButtonLabel() {
  if (lastKnownSyncing) return;
  const count = getSelectedAccounts().length;
  btnStartSync.disabled = count === 0;
  btnStartSync.innerHTML = `
    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
      <polygon points="5 3 19 12 5 21 5 3"></polygon>
    </svg>
    <span>${count > 0 ? `Sinkronkan (${count} Akun)` : 'Pilih Akun Dahulu'}</span>
  `;
}

// ----------------- Settings & Storage -----------------
async function loadSettings() {
  const items = await chrome.storage.local.get(['backendUrl', 'syncToken', 'syncAllAccounts', 'selectedUsernames']);
  backendUrlInput.value = items.backendUrl || DEFAULT_BACKEND_URL;
  syncTokenInput.value = items.syncToken || '';
  if (typeof items.syncAllAccounts === 'boolean') {
    syncAllAccountsCheckbox.checked = items.syncAllAccounts;
  }
  if (Array.isArray(items.selectedUsernames)) {
    hasSavedSelection = true;
    selectedSet = new Set(items.selectedUsernames.map((u) => String(u).toLowerCase()));
  }
}

async function saveSettings() {
  let url = backendUrlInput.value.trim().replace(/\/+$/, '');
  if (!url) url = DEFAULT_BACKEND_URL;
  backendUrlInput.value = url;

  const syncToken = syncTokenInput.value.trim();
  const syncAll = syncAllAccountsCheckbox.checked;
  await chrome.storage.local.set({ backendUrl: url, syncToken, syncAllAccounts: syncAll });
  await testServerConnection();
  await loadTargetAccounts();
}

function persistSelection() {
  hasSavedSelection = true;
  chrome.storage.local.set({ selectedUsernames: Array.from(selectedSet) });
}

function getBackendUrl() {
  const url = backendUrlInput.value.trim().replace(/\/+$/, '');
  return url || DEFAULT_BACKEND_URL;
}

function getSyncToken() {
  return syncTokenInput.value.trim();
}

function getBackendHeaders() {
  const headers = { Accept: 'application/json' };
  const token = getSyncToken();
  if (token) {
    headers['X-Sync-Token'] = token;
  }
  return headers;
}

// ----------------- Instagram & Facebook Session Check -----------------
async function checkInstagramSession() {
  igStatusEl.innerHTML = '<span class="dot yellow"></span><span>Cek...</span>';
  try {
    if (chrome?.cookies) {
      const cookie = await chrome.cookies.get({ url: 'https://www.instagram.com', name: 'sessionid' });
      if (cookie && cookie.value) {
        igStatusEl.innerHTML = '<span class="dot green"></span><span>Aktif</span>';
        return true;
      } else {
        igStatusEl.innerHTML = '<span class="dot red"></span><span>Belum</span>';
        return false;
      }
    }
    igStatusEl.innerHTML = '<span class="dot yellow"></span><span>Siap</span>';
    return true;
  } catch (_) {
    igStatusEl.innerHTML = '<span class="dot gray"></span><span>Manual</span>';
    return true;
  }
}

async function checkFacebookSession() {
  if (!fbStatusEl) return true;
  fbStatusEl.innerHTML = '<span class="dot yellow"></span><span>Cek...</span>';
  try {
    if (chrome?.cookies) {
      const cookie = await chrome.cookies.get({ url: 'https://www.facebook.com', name: 'c_user' });
      if (cookie && cookie.value) {
        fbStatusEl.innerHTML = '<span class="dot green"></span><span>Aktif</span>';
        return true;
      } else {
        fbStatusEl.innerHTML = '<span class="dot red"></span><span>Belum</span>';
        return false;
      }
    }
    fbStatusEl.innerHTML = '<span class="dot yellow"></span><span>Siap</span>';
    return true;
  } catch (_) {
    fbStatusEl.innerHTML = '<span class="dot gray"></span><span>Manual</span>';
    return true;
  }
}

// ----------------- Server & Account Loading -----------------
async function testServerConnection() {
  serverStatusEl.innerHTML = '<span class="dot yellow"></span><span>Menguji...</span>';
  const url = `${getBackendUrl()}/api/sync/unsupported-accounts`;
  try {
    const res = await fetch(url, { method: 'GET', headers: getBackendHeaders() });
    if (res.ok) {
      serverStatusEl.innerHTML = '<span class="dot green"></span><span>Terhubung</span>';
      return true;
    } else if (res.status === 401) {
      serverStatusEl.innerHTML = '<span class="dot red"></span><span>Token Salah (401)</span>';
      return false;
    } else {
      serverStatusEl.innerHTML = '<span class="dot red"></span><span>HTTP ' + res.status + '</span>';
      return false;
    }
  } catch (_) {
    serverStatusEl.innerHTML = '<span class="dot red"></span><span>Tidak Terhubung</span>';
    return false;
  }
}

async function loadTargetAccounts() {
  const isConnected = await testServerConnection();
  if (!isConnected) {
    accountListEl.innerHTML = `
      <div style="padding: 16px; text-align: center; color: var(--danger); font-size: 11px;">
        Gagal menghubungi backend SocioWatch.<br>Pastikan server aktif di <b>${escapeHtml(getBackendUrl())}</b>.
      </div>
    `;
    accountCountBadge.textContent = '0/0 dipilih';
    updateStartButtonLabel();
    return;
  }

  const syncAll = syncAllAccountsCheckbox.checked ? 1 : 0;
  const endpoint = `${getBackendUrl()}/api/sync/unsupported-accounts?all=${syncAll}`;

  try {
    const res = await fetch(endpoint, { headers: getBackendHeaders() });
    const data = await res.json();

    if (data.success && Array.isArray(data.accounts)) {
      activeAccounts = data.accounts;
      // Default: select all accounts on first run if user has never customized selection
      if (!hasSavedSelection) {
        selectedSet = new Set(activeAccounts.map((a) => a.username.toLowerCase()));
      }
      renderAccountList();
    } else {
      activeAccounts = [];
      renderAccountList();
    }
  } catch (_) {
    accountListEl.innerHTML = '<div style="padding: 12px; text-align: center; color: var(--danger);">Gagal memuat daftar akun.</div>';
  }
}

// ----------------- Dynamic Filtering & Rendering -----------------
function getFilteredAccounts() {
  return activeAccounts.filter((acc) => {
    const platform = (acc.platform || 'instagram').toLowerCase();
    const isPersonal = platform === 'instagram' && (acc.last_fetch_status === 'unsupported' || !acc.last_fetch_status);
    if (currentFilter === 'instagram' && platform !== 'instagram') return false;
    if (currentFilter === 'facebook' && platform !== 'facebook') return false;
    if (currentFilter === 'personal' && !isPersonal) return false;

    if (searchQuery) {
      const haystack = [
        acc.platform,
        acc.username,
        acc.display_name,
        acc.category,
        acc.region,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      if (!haystack.includes(searchQuery)) return false;
    }
    return true;
  });
}

function getSelectedAccounts() {
  return activeAccounts.filter((acc) => selectedSet.has(acc.username.toLowerCase()));
}

function formatRelativeTime(isoString) {
  if (!isoString) return 'Belum pernah sync';
  const diffSec = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000);
  if (isNaN(diffSec) || diffSec < 0) return 'Baru saja';
  if (diffSec < 60) return 'Baru saja';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)} mnt lalu`;
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} jam lalu`;
  return `${Math.floor(diffSec / 86400)} hari lalu`;
}

function renderAccountList() {
  const filtered = getFilteredAccounts();
  const totalSelected = getSelectedAccounts().length;
  accountCountBadge.textContent = `${totalSelected}/${activeAccounts.length} dipilih`;
  updateStartButtonLabel();

  if (activeAccounts.length === 0) {
    accountListEl.innerHTML = `
      <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 11px;">
        Tidak ada akun target ditemukan.
      </div>
    `;
    return;
  }

  if (filtered.length === 0) {
    accountListEl.innerHTML = `
      <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 11px;">
        Tidak ada akun cocok dengan pencarian/filter.
      </div>
    `;
    return;
  }

  let html = '';
  for (const acc of filtered) {
    const platform = (acc.platform || 'instagram').toLowerCase();
    const unameLower = acc.username.toLowerCase();
    const isChecked = selectedSet.has(unameLower);
    const platformBadge = platform === 'facebook'
      ? '<span class="badge badge-fb">FB</span>'
      : '<span class="badge badge-ig">IG</span>';
    const isSynced = acc.last_fetch_status === 'ok';
    const statusBadge = isSynced
      ? '<span class="badge badge-green">Synced</span>'
      : '<span class="badge badge-orange">Baru</span>';
    const initials = (acc.display_name || acc.username).replace(/^@/, '').slice(0, 2).toUpperCase();
    const avatarBg = platform === 'facebook'
      ? 'background: linear-gradient(135deg, #2563eb, #1d4ed8);'
      : '';
    const subParts = [
      `${Number(acc.followers_count || 0).toLocaleString()} flw`,
      acc.region || acc.category || null,
      formatRelativeTime(acc.last_fetched_at),
    ].filter(Boolean);
    const titleLabel = platform === 'facebook'
      ? (acc.display_name || acc.username)
      : `@${acc.username}`;

    html += `
      <div class="account-item ${isChecked ? 'selected' : ''}" data-username="${escapeHtml(unameLower)}">
        <input type="checkbox" class="acc-checkbox" data-username="${escapeHtml(unameLower)}" ${isChecked ? 'checked' : ''}>
        <div class="acc-avatar" style="${avatarBg}">${escapeHtml(initials)}</div>
        <div class="account-info">
          <div class="account-name-row">
            ${platformBadge}
            <span class="account-name">${escapeHtml(titleLabel)}</span>
          </div>
          <div class="account-meta">${escapeHtml(subParts.join(' • '))}</div>
        </div>
        ${statusBadge}
      </div>
    `;
  }

  accountListEl.innerHTML = html;

  // Bind row click & checkbox change
  accountListEl.querySelectorAll('.account-item').forEach((row) => {
    row.addEventListener('click', (e) => {
      const uname = row.getAttribute('data-username');
      const cb = row.querySelector('.acc-checkbox');
      if (e.target !== cb) {
        cb.checked = !cb.checked;
      }
      if (cb.checked) {
        selectedSet.add(uname);
        row.classList.add('selected');
      } else {
        selectedSet.delete(uname);
        row.classList.remove('selected');
      }
      persistSelection();
      accountCountBadge.textContent = `${getSelectedAccounts().length}/${activeAccounts.length} dipilih`;
      updateStartButtonLabel();
    });
  });
}

// ----------------- Actions -----------------
function handleStartSync() {
  if (lastKnownSyncing) return;

  const targets = getSelectedAccounts();
  if (targets.length === 0) {
    alert('Silakan pilih minimal 1 akun untuk disinkronkan.');
    return;
  }

  chrome.runtime.sendMessage({
    type: 'START_SYNC',
    targets,
    backendUrl: getBackendUrl(),
    syncToken: getSyncToken(),
  });
}

function handleStopSync() {
  chrome.runtime.sendMessage({ type: 'STOP_SYNC' });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

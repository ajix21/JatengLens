// SocioWatch Jateng - Instagram Sync Companion (v1.5)
// Background Service Worker: keeps sync running in background, manages 2x daily auto-schedule
// aligned before server snapshot (06:30 & 18:30), supports X-Sync-Token auth & not_found reporting.

const IG_APP_ID = '936619743392459';
const DEFAULT_BACKEND_URL = 'http://localhost:8000';
const MAX_LOGS = 150;
const ALARM_SLOT_1 = 'sociowatch_sync_slot_1';
const ALARM_SLOT_2 = 'sociowatch_sync_slot_2';

const DEFAULT_SCHEDULE = {
  enabled: true,
  time1: '08:00',
  time2: '20:00',
  scope: 'all_targets', // 'all_targets' | 'selected_only'
};

function buildBackendHeaders(syncToken, includeJsonContentType = false) {
  const headers = { Accept: 'application/json' };
  if (includeJsonContentType) {
    headers['Content-Type'] = 'application/json';
  }
  const cleanToken = String(syncToken || '').trim();
  if (cleanToken) {
    headers['X-Sync-Token'] = cleanToken;
  }
  return headers;
}

let isRunning = false;
let cancelRequested = false;

const defaultState = () => ({
  isSyncing: false,
  percent: 0,
  label: 'Siap',
  logs: [{ time: nowTime(), msg: '[Siap] Ekstensi SocioWatch siap digunakan.', type: 'info' }],
  lastFinishedAt: null,
});

function nowTime() {
  return new Date().toTimeString().split(' ')[0];
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function getState() {
  const data = await chrome.storage.local.get(['syncState']);
  return data.syncState || defaultState();
}

async function updateState(patch) {
  const current = await getState();
  const next = { ...current, ...patch };
  await chrome.storage.local.set({ syncState: next });
  return next;
}

async function appendLog(msg, type = 'info') {
  const current = await getState();
  const logs = Array.isArray(current.logs) ? [...current.logs] : [];
  logs.push({ time: nowTime(), msg, type });
  if (logs.length > MAX_LOGS) {
    logs.splice(0, logs.length - MAX_LOGS);
  }
  await chrome.storage.local.set({ syncState: { ...current, logs } });
}

function setBadge(text, color = '#4f46e5') {
  try {
    chrome.action.setBadgeText({ text: String(text || '') });
    chrome.action.setBadgeBackgroundColor({ color });
  } catch (_) {}
}

// ----------------- Schedule (2x Daily Alarms) -----------------
function computeNextTriggerMs(hhmm) {
  const [hStr, mStr] = String(hhmm || '08:00').split(':');
  const h = Math.max(0, Math.min(23, parseInt(hStr, 10) || 0));
  const m = Math.max(0, Math.min(59, parseInt(mStr, 10) || 0));
  const now = new Date();
  const target = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0, 0);
  if (target.getTime() <= now.getTime() + 5000) {
    target.setDate(target.getDate() + 1);
  }
  return target.getTime();
}

async function getScheduleConfig() {
  const data = await chrome.storage.local.get(['scheduleConfig']);
  return { ...DEFAULT_SCHEDULE, ...(data.scheduleConfig || {}) };
}

async function applyScheduleAlarms(configInput = null) {
  const config = configInput ? { ...DEFAULT_SCHEDULE, ...configInput } : await getScheduleConfig();
  await chrome.storage.local.set({ scheduleConfig: config });

  await chrome.alarms.clear(ALARM_SLOT_1);
  await chrome.alarms.clear(ALARM_SLOT_2);

  if (config.enabled) {
    if (config.time1) {
      chrome.alarms.create(ALARM_SLOT_1, {
        when: computeNextTriggerMs(config.time1),
        periodInMinutes: 1440,
      });
    }
    if (config.time2 && config.time2 !== config.time1) {
      chrome.alarms.create(ALARM_SLOT_2, {
        when: computeNextTriggerMs(config.time2),
        periodInMinutes: 1440,
      });
    }
  }

  return getNextAlarmInfo();
}

async function getNextAlarmInfo() {
  const alarms = await chrome.alarms.getAll();
  const syncAlarms = alarms
    .filter((a) => a.name === ALARM_SLOT_1 || a.name === ALARM_SLOT_2)
    .sort((a, b) => a.scheduledTime - b.scheduledTime);

  if (syncAlarms.length === 0) {
    return { scheduled: false, nextTime: null };
  }
  return { scheduled: true, nextTime: syncAlarms[0].scheduledTime, slot: syncAlarms[0].name };
}

async function handleScheduledAlarm(alarm) {
  if (alarm.name !== ALARM_SLOT_1 && alarm.name !== ALARM_SLOT_2) return;
  if (isRunning) {
    await appendLog('[Terjadwal] Dilewati karena sinkronisasi lain sedang berjalan.', 'warn');
    return;
  }

  const config = await getScheduleConfig();
  if (!config.enabled) return;

  const slotLabel = alarm.name === ALARM_SLOT_1 ? `Jadwal 1 (${config.time1})` : `Jadwal 2 (${config.time2})`;
  const stored = await chrome.storage.local.get(['backendUrl', 'syncToken', 'syncAllAccounts', 'selectedUsernames']);
  const backendUrl = String(stored.backendUrl || DEFAULT_BACKEND_URL).replace(/\/+$/, '');
  const syncToken = String(stored.syncToken || '').trim();
  const syncAll = stored.syncAllAccounts ? 1 : 0;

  await appendLog(`[Terjadwal] Memicu sinkronisasi otomatis — ${slotLabel}...`, 'info');

  try {
    const res = await fetch(`${backendUrl}/api/sync/unsupported-accounts?all=${syncAll}`, {
      headers: buildBackendHeaders(syncToken),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    let targets = Array.isArray(data.accounts) ? data.accounts : [];

    if (config.scope === 'selected_only' && Array.isArray(stored.selectedUsernames) && stored.selectedUsernames.length > 0) {
      const selectedSet = new Set(stored.selectedUsernames.map((u) => String(u).toLowerCase()));
      targets = targets.filter((acc) => selectedSet.has(String(acc.username).toLowerCase()));
    }

    if (targets.length === 0) {
      await appendLog('[Terjadwal] Tidak ada akun target untuk disinkronkan.', 'warn');
      return;
    }

    await runSyncJob(targets, backendUrl, slotLabel, syncToken);
  } catch (err) {
    await appendLog(`[Terjadwal] Gagal mengambil daftar akun dari server (${backendUrl}): ${err.message}`, 'error');
  }
}

chrome.alarms.onAlarm.addListener(handleScheduledAlarm);

// ----------------- Message Listener -----------------
chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (!message || !message.type) return;

  if (message.type === 'START_SYNC') {
    if (isRunning) {
      sendResponse({ started: false, reason: 'already_running' });
      return;
    }
    const targets = Array.isArray(message.targets) ? message.targets : [];
    const backendUrl = String(message.backendUrl || DEFAULT_BACKEND_URL).replace(/\/+$/, '');
    const syncToken = String(message.syncToken || '').trim();
    if (targets.length === 0) {
      sendResponse({ started: false, reason: 'no_targets' });
      return;
    }

    runSyncJob(targets, backendUrl, 'Manual', syncToken);
    sendResponse({ started: true });
    return;
  }

  if (message.type === 'STOP_SYNC') {
    if (isRunning) {
      cancelRequested = true;
      appendLog('Permintaan berhenti diterima, menyelesaikan proses saat ini...', 'warn');
    }
    sendResponse({ stopping: true });
    return;
  }

  if (message.type === 'CLEAR_LOGS') {
    updateState({ logs: [] }).then(() => sendResponse({ ok: true }));
    return true;
  }

  if (message.type === 'UPDATE_SCHEDULE') {
    applyScheduleAlarms(message.config).then((info) => sendResponse({ ok: true, nextAlarm: info }));
    return true;
  }

  if (message.type === 'GET_SCHEDULE_INFO') {
    Promise.all([getScheduleConfig(), getNextAlarmInfo()]).then(([config, nextAlarm]) => {
      sendResponse({ ok: true, config, nextAlarm });
    });
    return true;
  }
});

// Initialize alarms & migrate old 06:30/18:30 default to 08:00/20:00 WIB (aligned before 10:00 report)
chrome.runtime.onInstalled.addListener(async () => {
  const cfg = await getScheduleConfig();
  if (cfg.time1 === '06:30' && cfg.time2 === '18:30') {
    cfg.time1 = '08:00';
    cfg.time2 = '20:00';
    await applyScheduleAlarms(cfg);
  } else {
    await applyScheduleAlarms();
  }
});

chrome.runtime.onStartup.addListener(async () => {
  const state = await getState();
  if (state.isSyncing && !isRunning) {
    await updateState({ isSyncing: false, label: 'Terhenti' });
    setBadge('');
  }
  await applyScheduleAlarms();
});

// ----------------- Background Tab Helpers -----------------
function waitForTabLoad(tabId, timeoutMs = 15000) {
  return new Promise((resolve) => {
    let settled = false;
    const done = () => {
      if (settled) return;
      settled = true;
      chrome.tabs.onUpdated.removeListener(listener);
      clearTimeout(timer);
      resolve();
    };

    const listener = (updatedTabId, changeInfo) => {
      if (updatedTabId === tabId && changeInfo.status === 'complete') {
        done();
      }
    };

    chrome.tabs.onUpdated.addListener(listener);
    const timer = setTimeout(done, timeoutMs);

    chrome.tabs.get(tabId, (tab) => {
      if (chrome.runtime.lastError || (tab && tab.status === 'complete')) {
        done();
      }
    });
  });
}

async function createBackgroundIgTab(url = 'https://www.instagram.com/') {
  const tab = await chrome.tabs.create({ url, active: false });
  await waitForTabLoad(tab.id, 15000);
  await sleep(800);
  return tab.id;
}

async function navigateTab(tabId, url) {
  await chrome.tabs.update(tabId, { url });
  await waitForTabLoad(tabId, 15000);
  await sleep(2200);
}

// ----------------- In-Page Script 1: Same-Origin API Fetch -----------------
async function inPageFetchInstagramApi(username, appId) {
  const cleanUsername = String(username || '').replace(/^@/, '').trim();

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  const csrfToken = getCookie('csrftoken');
  let wwwClaim = '0';
  try {
    wwwClaim = sessionStorage.getItem('www-claim-v2') || '0';
  } catch (_) {}

  const headers = {
    'x-ig-app-id': appId,
    'x-asbd-id': '129477',
    'x-csrftoken': csrfToken,
    'x-ig-www-claim': wwwClaim,
    'x-requested-with': 'XMLHttpRequest',
    'accept': '*/*',
  };

  let profile = null;
  let posts = [];
  let methodUsed = '';

  function mapFeedItems(items) {
    const mapped = [];
    for (const item of items || []) {
      const code = item.code || item.shortcode;
      if (!code) continue;

      const isReel = item.product_type === 'clips';
      const postUrl = isReel
        ? `https://www.instagram.com/reel/${code}/`
        : `https://www.instagram.com/p/${code}/`;

      let mediaType = 'image';
      if (isReel) mediaType = 'reel';
      else if (item.media_type === 2) mediaType = 'video';
      else if (item.media_type === 8) mediaType = 'carousel';

      const mediaUrl =
        item.image_versions2?.candidates?.[0]?.url ||
        item.carousel_media?.[0]?.image_versions2?.candidates?.[0]?.url ||
        null;

      const takenAt = item.taken_at
        ? new Date(Number(item.taken_at) * 1000).toISOString()
        : new Date().toISOString();

      mapped.push({
        external_id: String(item.pk || item.id || `ig_${code}`),
        url: postUrl,
        content: String(item.caption?.text || ''),
        media_type: mediaType,
        likes_count: Number(item.like_count || 0),
        comments_count: Number(item.comment_count || 0),
        views_count: Number(item.play_count || item.view_count || 0),
        posted_at: takenAt,
        media_url: mediaUrl,
      });
    }
    return mapped;
  }

  try {
    const res = await fetch(
      `/api/v1/users/web_profile_info/?username=${encodeURIComponent(cleanUsername)}`,
      { method: 'GET', headers, credentials: 'include' }
    );
    if (res.ok) {
      const json = await res.json();
      const user = json?.data?.user;
      if (user) {
        profile = {
          external_id: String(user.id || ''),
          followers_count: Number(user.edge_followed_by?.count || 0),
          following_count: Number(user.edge_follow?.count || 0),
          post_count: Number(user.edge_owner_to_timeline_media?.count || 0),
          bio: String(user.biography || ''),
          profile_pic_url: user.profile_pic_url_hd || user.profile_pic_url || '',
        };

        const edges = user.edge_owner_to_timeline_media?.edges || [];
        for (const edge of edges) {
          const node = edge?.node;
          if (!node || !node.shortcode) continue;
          let mediaType = 'image';
          if (node.is_video || node.__typename === 'GraphVideo') mediaType = 'video';
          else if (node.__typename === 'GraphSidecar') mediaType = 'carousel';

          posts.push({
            external_id: String(node.id || `ig_${node.shortcode}`),
            url: `https://www.instagram.com/p/${node.shortcode}/`,
            content: String(node.edge_media_to_caption?.edges?.[0]?.node?.text || ''),
            media_type: mediaType,
            likes_count: Number(node.edge_liked_by?.count ?? node.edge_media_preview_like?.count ?? 0),
            comments_count: Number(node.edge_media_to_comment?.count || 0),
            views_count: Number(node.video_view_count || 0),
            posted_at: node.taken_at_timestamp
              ? new Date(Number(node.taken_at_timestamp) * 1000).toISOString()
              : new Date().toISOString(),
            media_url: node.display_url || null,
          });
        }
        methodUsed = 'web_profile_info';
      }
    }
  } catch (_) {}

  if (!profile || posts.length === 0) {
    try {
      const feedRes = await fetch(
        `/api/v1/feed/user/${encodeURIComponent(cleanUsername)}/username/?count=12`,
        { method: 'GET', headers, credentials: 'include' }
      );
      if (feedRes.ok) {
        const feedJson = await feedRes.json();
        const feedPosts = mapFeedItems(feedJson?.items);
        if (feedPosts.length > 0) {
          posts = feedPosts;
          methodUsed = methodUsed ? `${methodUsed}+feed_v1` : 'feed_v1';
        }
        if (!profile && feedJson?.user) {
          const u = feedJson.user;
          profile = {
            external_id: String(u.pk || u.id || ''),
            followers_count: Number(u.follower_count || 0),
            following_count: Number(u.following_count || 0),
            post_count: Number(u.media_count || feedPosts.length || 0),
            bio: String(u.biography || ''),
            profile_pic_url: u.profile_pic_url || '',
          };
        }
      }
    } catch (_) {}
  }

  if (profile) {
    return { ok: true, method: methodUsed, profile, posts };
  }

  return { ok: false, reason: 'api_unavailable' };
}

// ----------------- In-Page Script 2: Direct Profile Page JSON + DOM Extractor -----------------
async function inPageExtractProfileFromPage(username, appId) {
  const cleanUsername = String(username || '').replace(/^@/, '').trim().toLowerCase();

  const bodyText = (document.body?.innerText || '').toLowerCase();
  if (
    bodyText.includes("sorry, this page isn't available") ||
    bodyText.includes('maaf, halaman ini tidak tersedia') ||
    bodyText.includes("profile isn't available")
  ) {
    return { ok: false, notFound: true, reason: `Halaman profil @${cleanUsername} tidak tersedia di Instagram.` };
  }

  function parseCompactNumber(raw) {
    if (raw === null || raw === undefined) return 0;
    let s = String(raw).trim().toLowerCase();
    if (!s) return 0;

    let multiplier = 1;
    if (s.includes('jt') || s.includes('m') || s.includes('juta')) {
      multiplier = 1_000_000;
      s = s.replace(/jt|juta|m/g, '').trim();
    } else if (s.includes('rb') || s.includes('k') || s.includes('ribu')) {
      multiplier = 1_000;
      s = s.replace(/rb|ribu|k/g, '').trim();
    }

    if (multiplier > 1) {
      s = s.replace(',', '.').replace(/[^\d.]/g, '');
      const num = parseFloat(s);
      return isNaN(num) ? 0 : Math.round(num * multiplier);
    }

    const digits = s.replace(/[^\d]/g, '');
    return digits ? parseInt(digits, 10) : 0;
  }

  let profile = {
    external_id: '',
    followers_count: 0,
    following_count: 0,
    post_count: 0,
    bio: '',
    profile_pic_url: '',
  };
  let posts = [];
  let foundUserJson = false;

  const scripts = document.querySelectorAll('script[type="application/json"]');
  const seenCodes = new Set();

  function walkJson(obj, depth = 0) {
    if (!obj || typeof obj !== 'object' || depth > 14) return;

    if (Array.isArray(obj)) {
      for (const item of obj) walkJson(item, depth + 1);
      return;
    }

    if (
      typeof obj.username === 'string' &&
      obj.username.toLowerCase() === cleanUsername &&
      (obj.follower_count !== undefined || obj.edge_followed_by !== undefined || obj.media_count !== undefined)
    ) {
      foundUserJson = true;
      if (obj.pk || obj.id) profile.external_id = String(obj.pk || obj.id);
      const followers = obj.follower_count ?? obj.edge_followed_by?.count;
      const following = obj.following_count ?? obj.edge_follow?.count;
      const mediaCount = obj.media_count ?? obj.edge_owner_to_timeline_media?.count;
      if (followers !== undefined) profile.followers_count = Number(followers) || 0;
      if (following !== undefined) profile.following_count = Number(following) || 0;
      if (mediaCount !== undefined) profile.post_count = Number(mediaCount) || 0;
      if (obj.biography && !profile.bio) profile.bio = String(obj.biography);
      if ((obj.profile_pic_url_hd || obj.profile_pic_url) && !profile.profile_pic_url) {
        profile.profile_pic_url = obj.profile_pic_url_hd || obj.profile_pic_url;
      }
    }

    const code = obj.code || obj.shortcode;
    if (
      typeof code === 'string' &&
      code.length >= 6 &&
      code.length <= 25 &&
      (obj.taken_at !== undefined || obj.taken_at_timestamp !== undefined || obj.caption !== undefined || obj.edge_media_to_caption !== undefined) &&
      !seenCodes.has(code)
    ) {
      seenCodes.add(code);
      const isReel = obj.product_type === 'clips';
      let mediaType = 'image';
      if (isReel) mediaType = 'reel';
      else if (obj.media_type === 2 || obj.is_video || obj.__typename === 'GraphVideo') mediaType = 'video';
      else if (obj.media_type === 8 || obj.__typename === 'GraphSidecar') mediaType = 'carousel';

      const captionText =
        obj.caption?.text ??
        obj.edge_media_to_caption?.edges?.[0]?.node?.text ??
        '';

      const ts = obj.taken_at ?? obj.taken_at_timestamp;
      const postedAt = ts ? new Date(Number(ts) * 1000).toISOString() : new Date().toISOString();

      const mediaUrl =
        obj.image_versions2?.candidates?.[0]?.url ||
        obj.carousel_media?.[0]?.image_versions2?.candidates?.[0]?.url ||
        obj.display_url ||
        null;

      posts.push({
        external_id: String(obj.pk || obj.id || `ig_${code}`),
        url: isReel ? `https://www.instagram.com/reel/${code}/` : `https://www.instagram.com/p/${code}/`,
        content: String(captionText || ''),
        media_type: mediaType,
        likes_count: Number(obj.like_count ?? obj.edge_liked_by?.count ?? obj.edge_media_preview_like?.count ?? 0),
        comments_count: Number(obj.comment_count ?? obj.edge_media_to_comment?.count ?? 0),
        views_count: Number(obj.play_count ?? obj.view_count ?? obj.video_view_count ?? 0),
        posted_at: postedAt,
        media_url: mediaUrl,
      });
    }

    for (const key of Object.keys(obj)) {
      walkJson(obj[key], depth + 1);
    }
  }

  for (const s of scripts) {
    try {
      const parsed = JSON.parse(s.textContent);
      walkJson(parsed, 0);
    } catch (_) {}
  }

  const csrfMatch = document.cookie.match(/(?:^|; )csrftoken=([^;]*)/);
  const csrfToken = csrfMatch ? decodeURIComponent(csrfMatch[1]) : '';
  let wwwClaim = '0';
  try {
    wwwClaim = sessionStorage.getItem('www-claim-v2') || '0';
  } catch (_) {}

  const igHeaders = {
    'x-ig-app-id': appId,
    'x-asbd-id': '129477',
    'x-csrftoken': csrfToken,
    'x-ig-www-claim': wwwClaim,
    'x-requested-with': 'XMLHttpRequest',
    'accept': '*/*',
  };

  function isAutoAltText(text) {
    if (!text) return true;
    const t = String(text).trim();
    if (!t) return true;
    return /^(Photo|Image|Video) (by|shared by) /i.test(t) ||
      /May be (a |an )?(image|graphic|illustration|photo|text|meme|art)/i.test(t);
  }

  function shortcodeToMediaId(shortcode) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    let id = 0n;
    for (let i = 0; i < shortcode.length; i++) {
      const idx = alphabet.indexOf(shortcode[i]);
      if (idx === -1) return null;
      id = id * 64n + BigInt(idx);
    }
    return id.toString();
  }

  function decodeHtmlEntities(str) {
    const el = document.createElement('textarea');
    el.innerHTML = str;
    return el.value;
  }

  function extractCaptionFromPostHtml(html) {
    if (!html) return null;
    // 1. Try <meta name="description" content="123 likes, 4 comments - user on Date: &quot;CAPTION&quot;">
    const metaMatch =
      html.match(/<meta[^>]+name=["']description["'][^>]+content=["']([^"']+)["']/i) ||
      html.match(/<meta[^>]+content=["']([^"']+)["'][^>]+name=["']description["']/i) ||
      html.match(/<meta[^>]+property=["']og:description["'][^>]+content=["']([^"']+)["']/i) ||
      html.match(/<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:description["']/i);
    if (metaMatch && metaMatch[1]) {
      const desc = decodeHtmlEntities(metaMatch[1]);
      // Extract quoted caption after ": \""
      const capMatch = desc.match(/:\s*["“]([\s\S]+)["”]\.?\s*$/);
      if (capMatch && capMatch[1] && !isAutoAltText(capMatch[1])) {
        return capMatch[1].trim();
      }
    }
    return null;
  }

  async function enrichPost(post) {
    const m = post.url.match(/\/(p|reel)\/([^/?#]+)/);
    if (!m) return;
    const type = m[1];
    const code = m[2];
    const mediaId = shortcodeToMediaId(code);

    // Enrichment A: Official same-origin v1 media info API
    if (mediaId) {
      try {
        const res = await fetch(`/api/v1/media/${mediaId}/info/`, {
          method: 'GET',
          headers: igHeaders,
          credentials: 'include',
        });
        if (res.ok) {
          const json = await res.json();
          const item = json?.items?.[0];
          if (item) {
            post.external_id = String(item.pk || item.id || post.external_id);
            if (item.caption?.text) {
              post.content = String(item.caption.text);
            }
            if (item.like_count !== undefined) post.likes_count = Number(item.like_count) || 0;
            if (item.comment_count !== undefined) post.comments_count = Number(item.comment_count) || 0;
            if (item.play_count !== undefined || item.view_count !== undefined) {
              post.views_count = Number(item.play_count ?? item.view_count) || 0;
            }
            if (item.taken_at) {
              post.posted_at = new Date(Number(item.taken_at) * 1000).toISOString();
            }
            const imgUrl =
              item.image_versions2?.candidates?.[0]?.url ||
              item.carousel_media?.[0]?.image_versions2?.candidates?.[0]?.url;
            if (imgUrl) post.media_url = imgUrl;
            if (item.product_type === 'clips') post.media_type = 'reel';
            else if (item.media_type === 2) post.media_type = 'video';
            else if (item.media_type === 8) post.media_type = 'carousel';
            return;
          }
        }
      } catch (_) {}
    }

    // Enrichment B: Fetch individual post page HTML & parse meta description caption
    try {
      const htmlRes = await fetch(`/${type}/${code}/`, {
        method: 'GET',
        credentials: 'include',
      });
      if (htmlRes.ok) {
        const html = await htmlRes.text();
        const cap = extractCaptionFromPostHtml(html);
        if (cap) {
          post.content = cap;
        }
      }
    } catch (_) {}
  }

  // Try feed API (by numeric user_id first if known, then by username)
  if (posts.length === 0) {
    const feedUrls = [];
    if (profile.external_id && /^\d+$/.test(profile.external_id)) {
      feedUrls.push(`/api/v1/feed/user/${profile.external_id}/?count=12`);
    }
    feedUrls.push(`/api/v1/feed/user/${encodeURIComponent(cleanUsername)}/username/?count=12`);

    for (const feedUrl of feedUrls) {
      if (posts.length > 0) break;
      try {
        const feedRes = await fetch(feedUrl, {
          method: 'GET',
          headers: igHeaders,
          credentials: 'include',
        });
        if (feedRes.ok) {
          const feedJson = await feedRes.json();
          for (const item of feedJson?.items || []) {
            const code = item.code;
            if (!code || seenCodes.has(code)) continue;
            seenCodes.add(code);
            const isReel = item.product_type === 'clips';
            posts.push({
              external_id: String(item.pk || item.id || `ig_${code}`),
              url: isReel ? `https://www.instagram.com/reel/${code}/` : `https://www.instagram.com/p/${code}/`,
              content: String(item.caption?.text || ''),
              media_type: isReel ? 'reel' : item.media_type === 2 ? 'video' : item.media_type === 8 ? 'carousel' : 'image',
              likes_count: Number(item.like_count || 0),
              comments_count: Number(item.comment_count || 0),
              views_count: Number(item.play_count || item.view_count || 0),
              posted_at: item.taken_at ? new Date(Number(item.taken_at) * 1000).toISOString() : new Date().toISOString(),
              media_url: item.image_versions2?.candidates?.[0]?.url || item.carousel_media?.[0]?.image_versions2?.candidates?.[0]?.url || null,
            });
          }
        }
      } catch (_) {}
    }
  }

  const metaDesc =
    document.querySelector('meta[property="og:description"]')?.getAttribute('content') ||
    document.querySelector('meta[name="description"]')?.getAttribute('content') ||
    '';

  if (metaDesc) {
    const fMatch = metaDesc.match(/([\d.,]+\s*(?:rb|jt|k|m)?)\s*(?:followers|pengikut)/i);
    const flwMatch = metaDesc.match(/([\d.,]+\s*(?:rb|jt|k|m)?)\s*(?:following|mengikuti)/i);
    const pMatch = metaDesc.match(/([\d.,]+\s*(?:rb|jt|k|m)?)\s*(?:posts|postingan|kiriman)/i);
    if (!profile.followers_count && fMatch) profile.followers_count = parseCompactNumber(fMatch[1]);
    if (!profile.following_count && flwMatch) profile.following_count = parseCompactNumber(flwMatch[1]);
    if (!profile.post_count && pMatch) profile.post_count = parseCompactNumber(pMatch[1]);
  }

  const header = document.querySelector('header');
  if (header) {
    const followersLink = header.querySelector('a[href*="/followers"]');
    if (followersLink && !profile.followers_count) {
      const titleVal = followersLink.querySelector('span[title]')?.getAttribute('title');
      profile.followers_count = parseCompactNumber(titleVal || followersLink.innerText);
    }
    const followingLink = header.querySelector('a[href*="/following"]');
    if (followingLink && !profile.following_count) {
      const titleVal = followingLink.querySelector('span[title]')?.getAttribute('title');
      profile.following_count = parseCompactNumber(titleVal || followingLink.innerText);
    }
    const statItems = header.querySelectorAll('ul li');
    if (statItems.length >= 3) {
      if (!profile.post_count) {
        const t = statItems[0].querySelector('span[title]')?.getAttribute('title') || statItems[0].innerText;
        profile.post_count = parseCompactNumber(t);
      }
      if (!profile.followers_count) {
        const t = statItems[1].querySelector('span[title]')?.getAttribute('title') || statItems[1].innerText;
        profile.followers_count = parseCompactNumber(t);
      }
      if (!profile.following_count) {
        const t = statItems[2].querySelector('span[title]')?.getAttribute('title') || statItems[2].innerText;
        profile.following_count = parseCompactNumber(t);
      }
    }
  }

  if (posts.length === 0) {
    const postLinks = document.querySelectorAll('a[href*="/p/"], a[href*="/reel/"]');
    for (const a of postLinks) {
      if (posts.length >= 12) break;
      const href = a.getAttribute('href') || '';
      const m = href.match(/\/(p|reel)\/([^/?#]+)/);
      if (!m) continue;
      const type = m[1];
      const code = m[2];
      if (seenCodes.has(code)) continue;
      seenCodes.add(code);

      const img = a.querySelector('img');
      const rawAlt = String(img?.getAttribute('alt') || '');
      posts.push({
        external_id: `ig_${code}`,
        url: `https://www.instagram.com/${type}/${code}/`,
        // Never use Instagram's auto-generated accessibility alt text as caption
        content: isAutoAltText(rawAlt) ? '' : rawAlt,
        media_type: type === 'reel' ? 'reel' : 'image',
        likes_count: 0,
        comments_count: 0,
        views_count: 0,
        posted_at: new Date().toISOString(),
        media_url: img?.getAttribute('src') || null,
      });
    }
  }

  // Enrich any post whose caption is missing/auto-alt or lacks engagement metrics
  const finalPosts = posts.slice(0, 12);
  for (const post of finalPosts) {
    if (!post.content || isAutoAltText(post.content) || (post.likes_count === 0 && post.external_id.startsWith('ig_'))) {
      await enrichPost(post);
      // Clean out any leftover auto-alt text
      if (isAutoAltText(post.content)) {
        post.content = '';
      }
      await new Promise((r) => setTimeout(r, 250));
    }
  }

  if (!foundUserJson && !header && finalPosts.length === 0) {
    return {
      ok: false,
      reason: `Tidak dapat membaca profil @${cleanUsername}. Pastikan sudah login di instagram.com.`,
    };
  }

  return {
    ok: true,
    method: foundUserJson ? 'page_json' : 'dom+media_api',
    profile,
    posts: finalPosts,
  };
}

// ----------------- Orchestration -----------------
async function fetchInstagramAccountViaTab(tabId, username) {
  const cleanUsername = username.replace(/^@/, '').trim();

  try {
    const apiExec = await chrome.scripting.executeScript({
      target: { tabId },
      func: inPageFetchInstagramApi,
      args: [cleanUsername, IG_APP_ID],
    });
    const apiRes = apiExec?.[0]?.result;
    if (apiRes && apiRes.ok && apiRes.profile && (apiRes.posts.length > 0 || apiRes.profile.post_count === 0)) {
      return apiRes;
    }
  } catch (_) {}

  await navigateTab(tabId, `https://www.instagram.com/${encodeURIComponent(cleanUsername)}/`);

  const pageExec = await chrome.scripting.executeScript({
    target: { tabId },
    func: inPageExtractProfileFromPage,
    args: [cleanUsername, IG_APP_ID],
  });

  const pageRes = pageExec?.[0]?.result;
  if (pageRes && pageRes.ok) {
    return pageRes;
  }

  const err = new Error(pageRes?.reason || `Gagal mengekstrak data @${cleanUsername} dari halaman web.`);
  if (pageRes?.notFound) {
    err.isNotFound = true;
  }
  throw err;
}

// ----------------- In-Page Script 3: Facebook Profile / Page Extractor -----------------
// Executed directly inside https://www.facebook.com/<username> after gentle auto-scrolling.
async function inPageExtractFacebookProfile(accountUsername) {
  const bodyText = (document.body?.innerText || '').toLowerCase();

  // Detect login wall or unavailable page
  if (
    window.location.pathname.startsWith('/login') ||
    bodyText.includes('log in to facebook') && !document.querySelector('div[role="article"]')
  ) {
    return {
      ok: false,
      reason: 'Sesi Facebook belum login. Silakan buka facebook.com dan login terlebih dahulu.',
    };
  }

  if (
    bodyText.includes("this content isn't available right now") ||
    bodyText.includes('konten ini tidak tersedia saat ini') ||
    bodyText.includes('halaman ini tidak tersedia') ||
    bodyText.includes("this page isn't available")
  ) {
    return {
      ok: false,
      notFound: true,
      reason: `Halaman/profil Facebook ${accountUsername} tidak tersedia atau dibatasi.`,
    };
  }

  function parseCompactNumber(raw) {
    if (raw === null || raw === undefined) return 0;
    let s = String(raw).trim().toLowerCase();
    if (!s) return 0;

    let multiplier = 1;
    if (s.includes('jt') || s.includes('juta') || /\bm\b/.test(s) || /\d\s*m\b/.test(s)) {
      multiplier = 1_000_000;
      s = s.replace(/jt|juta|m/g, '').trim();
    } else if (s.includes('rb') || s.includes('ribu') || /\bk\b/.test(s) || /\d\s*k\b/.test(s)) {
      multiplier = 1_000;
      s = s.replace(/rb|ribu|k/g, '').trim();
    }

    if (multiplier > 1) {
      s = s.replace(',', '.').replace(/[^\d.]/g, '');
      const num = parseFloat(s);
      return isNaN(num) ? 0 : Math.round(num * multiplier);
    }

    const digits = s.replace(/[^\d]/g, '');
    return digits ? parseInt(digits, 10) : 0;
  }

  function cleanFbPostUrl(rawHref) {
    if (!rawHref) return null;
    try {
      const u = new URL(rawHref, 'https://www.facebook.com');
      if (!u.hostname.includes('facebook.com')) return null;
      const keepParams = new URLSearchParams();
      for (const k of ['story_fbid', 'id', 'fbid', 'v']) {
        if (u.searchParams.has(k)) keepParams.set(k, u.searchParams.get(k));
      }
      const qs = keepParams.toString();
      return `https://www.facebook.com${u.pathname.replace(/\/+$/, '')}${qs ? '?' + qs : ''}`;
    } catch (_) {
      return null;
    }
  }

  function parseRelativeFbTime(raw) {
    if (!raw) return null;
    const s = String(raw).trim().toLowerCase();
    const now = Date.now();

    if (s.includes('baru saja') || s.includes('just now')) return new Date(now).toISOString();

    const minMatch = s.match(/(\d+)\s*(?:mnt|menit|min|m)\b/);
    if (minMatch) return new Date(now - parseInt(minMatch[1], 10) * 60_000).toISOString();

    const hrMatch = s.match(/(\d+)\s*(?:jam|j|hr|hrs|h)\b/);
    if (hrMatch) return new Date(now - parseInt(hrMatch[1], 10) * 3_600_000).toISOString();

    const dayMatch = s.match(/(\d+)\s*(?:hari|hr|d|days?)\b/);
    if (dayMatch) return new Date(now - parseInt(dayMatch[1], 10) * 86_400_000).toISOString();

    if (s.includes('kemarin') || s.includes('yesterday')) {
      return new Date(now - 86_400_000).toISOString();
    }

    const parsed = Date.parse(raw);
    if (!isNaN(parsed)) return new Date(parsed).toISOString();
    return null;
  }

  const profile = {
    external_id: '',
    followers_count: 0,
    following_count: 0,
    post_count: 0,
    bio: '',
    profile_pic_url: '',
  };

  const posts = [];
  const seenKeys = new Set();

  function simpleHash(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) {
      h = ((h << 5) - h + str.charCodeAt(i)) | 0;
    }
    return Math.abs(h).toString(36);
  }

  // Helper: extract fields deeply from a single Facebook Comet story subtree
  function extractStoryFromSubtree(root) {
    let creationTime = 0;
    let bestMessage = '';
    let bestUrl = '';
    let postId = '';
    let likes = 0;
    let comments = 0;
    let mediaUrl = null;

    function scan(node, d = 0) {
      if (!node || typeof node !== 'object' || d > 12) return;
      if (Array.isArray(node)) {
        for (const item of node) scan(item, d + 1);
        return;
      }

      if (!creationTime && typeof node.creation_time === 'number' && node.creation_time > 1500000000) {
        creationTime = node.creation_time;
      }
      if (!postId && typeof node.post_id === 'string' && node.post_id.length >= 5) {
        postId = node.post_id;
      }
      if (typeof node.message?.text === 'string' && node.message.text.trim().length > bestMessage.length) {
        bestMessage = node.message.text.trim();
      }
      const candUrl = node.wwwURL || node.permalink_url || node.url;
      if (
        !bestUrl &&
        typeof candUrl === 'string' &&
        candUrl.includes('facebook.com') &&
        /(\/posts\/|\/permalink\/|\/videos\/|\/reel\/|story_fbid=|\/photo)/i.test(candUrl)
      ) {
        bestUrl = candUrl;
      }
      if (!likes && typeof node.reaction_count?.count === 'number' && node.reaction_count.count > 0) {
        likes = node.reaction_count.count;
      }
      if (!comments && typeof node.comment_count?.total_count === 'number' && node.comment_count.total_count > 0) {
        comments = node.comment_count.total_count;
      }
      if (!mediaUrl && typeof node.uri === 'string' && node.uri.includes('scontent') && (node.width || 0) >= 200) {
        mediaUrl = node.uri;
      }

      for (const k of Object.keys(node)) {
        scan(node[k], d + 1);
      }
    }

    scan(root, 0);
    return { creationTime, bestMessage, bestUrl, postId, likes, comments, mediaUrl };
  }

  // Layer 1: Extract followers & Comet stories from embedded Relay <script type="application/json">
  const scripts = document.querySelectorAll('script[type="application/json"]');

  function walkFbJson(obj, depth = 0) {
    if (!obj || typeof obj !== 'object' || depth > 14) return;
    if (Array.isArray(obj)) {
      for (const item of obj) walkFbJson(item, depth + 1);
      return;
    }

    if (!profile.followers_count && typeof obj.followers_count === 'number' && obj.followers_count > 0) {
      profile.followers_count = obj.followers_count;
    }
    if (!profile.followers_count && typeof obj.follower_count === 'number' && obj.follower_count > 0) {
      profile.followers_count = obj.follower_count;
    }

    // Detect Comet Story node (has comet_sections or post_id)
    if ((obj.comet_sections && typeof obj.comet_sections === 'object') || (typeof obj.post_id === 'string' && obj.post_id.length >= 8)) {
      const s = extractStoryFromSubtree(obj);
      if (s.creationTime && (s.bestMessage.length >= 3 || s.mediaUrl)) {
        const cleanUrl = cleanFbPostUrl(s.bestUrl)
          || (s.postId ? `https://www.facebook.com/${s.postId}` : `${window.location.href.split('#')[0]}#fb_${s.creationTime}`);
        const dedupKey = s.postId || cleanUrl;
        if (!seenKeys.has(dedupKey)) {
          seenKeys.add(dedupKey);
          seenKeys.add(cleanUrl);
          if (s.bestMessage) seenKeys.add('txt_' + simpleHash(s.bestMessage.slice(0, 80)));
          const isVideo = cleanUrl.includes('/videos/') || cleanUrl.includes('/reel/');
          posts.push({
            external_id: s.postId || `fb_${simpleHash(cleanUrl)}`,
            url: cleanUrl,
            content: s.bestMessage,
            media_type: isVideo ? 'video' : (s.mediaUrl ? 'image' : 'text'),
            likes_count: s.likes,
            comments_count: s.comments,
            views_count: 0,
            posted_at: new Date(s.creationTime * 1000).toISOString(),
            _hasRealTimestamp: true,
            media_url: s.mediaUrl,
          });
          return; // Don't double-walk inside an already extracted story
        }
      }
    }

    for (const key of Object.keys(obj)) {
      walkFbJson(obj[key], depth + 1);
    }
  }

  for (const s of scripts) {
    try {
      const parsed = JSON.parse(s.textContent);
      walkFbJson(parsed, 0);
    } catch (_) {}
  }

  // Extract followers count from visible header text if still 0
  if (!profile.followers_count) {
    const flwLinks = document.querySelectorAll('a[href*="followers"], a[href*="friends"]');
    for (const a of flwLinks) {
      const txt = (a.innerText || '').trim();
      const m = txt.match(/([\d.,]+\s*(?:rb|jt|ribu|juta|k|m)?)\s*(?:pengikut|followers)/i);
      if (m) {
        profile.followers_count = parseCompactNumber(m[1]);
        break;
      }
    }
  }

  // Expand any "Lihat selengkapnya" / "See more" buttons inside visible posts and WAIT for React re-render
  let clickedSeeMore = false;
  try {
    const seeMoreBtns = document.querySelectorAll('div[role="button"], span[role="button"]');
    for (const btn of seeMoreBtns) {
      const t = (btn.innerText || '').trim().toLowerCase();
      if (t === 'lihat selengkapnya' || t === 'see more') {
        // Only click buttons inside post message areas (avoid clicking unrelated UI)
        if (btn.closest('div[data-ad-preview="message"], div[data-ad-comet-preview="message"], div[data-ad-rendering-role="story_message"], div[role="article"], div[aria-posinset]')) {
          btn.click();
          clickedSeeMore = true;
        }
      }
    }
  } catch (_) {}
  if (clickedSeeMore) {
    await new Promise((r) => setTimeout(r, 750));
  }

  const FB_LINK_SELECTOR = 'a[href*="/reel/"], a[href*="/videos/"], a[href*="/watch/"], a[href*="/posts/"], a[href*="/permalink/"], a[href*="story_fbid="], a[href*="/photo"], a[href*="fbid="]';

  function findBestPostLink(container) {
    if (!container) return null;
    // Prefer /reel/, /videos/, /posts/, /permalink/, story_fbid, then /photo
    const prioritySelectors = [
      'a[href*="/reel/"]',
      'a[href*="/videos/"]',
      'a[href*="/watch/"]',
      'a[href*="/posts/"]',
      'a[href*="/permalink/"]',
      'a[href*="story_fbid="]',
      'a[href*="/photo"]',
      'a[href*="fbid="]',
    ];
    for (const sel of prioritySelectors) {
      const el = container.querySelector(sel);
      if (el) return el;
    }
    return null;
  }

  function normalizeFbUrl(rawHref) {
    const cleaned = cleanFbPostUrl(rawHref);
    if (!cleaned) return null;
    const reelMatch = cleaned.match(/\/reel\/(\d+)/);
    if (reelMatch) return `https://www.facebook.com/reel/${reelMatch[1]}/`;
    const watchMatch = cleaned.match(/\/watch\/?\?.*v=(\d+)/);
    if (watchMatch) return `https://www.facebook.com/reel/${watchMatch[1]}/`;
    return cleaned;
  }

  function isTruncatedCaption(txt) {
    if (!txt) return true;
    return /(?:…|\.\.\.)\s*(?:see more|lihat selengkapnya)\s*$/i.test(txt.trim())
      || /\b(?:see more|lihat selengkapnya)\s*$/i.test(txt.trim());
  }

  async function enrichFbPost(post) {
    if (!post.url || post.url.includes('sw_post=')) return;
    try {
      const res = await fetch(post.url, { method: 'GET', credentials: 'include' });
      if (!res.ok) return;
      const html = await res.text();

      // 1. Extract exact creation_time from embedded JSON
      const ctMatch = html.match(/"creation_time"\s*:\s*(\d{10})/);
      if (ctMatch) {
        const ts = parseInt(ctMatch[1], 10);
        if (ts > 1500000000) {
          post.posted_at = new Date(ts * 1000).toISOString();
          post._hasRealTimestamp = true;
        }
      }

      // 2. Extract full untruncated message.text from embedded Relay JSON
      const msgRegex = /"message"\s*:\s*\{\s*"text"\s*:\s*"((?:[^"\\]|\\.)*)"/g;
      let bestFullText = '';
      let m;
      while ((m = msgRegex.exec(html)) !== null) {
        try {
          const decoded = JSON.parse('"' + m[1] + '"').trim();
          if (decoded.length > bestFullText.length) {
            bestFullText = decoded;
          }
        } catch (_) {}
      }

      if (bestFullText && (isTruncatedCaption(post.content) || bestFullText.length > (post.content || '').length)) {
        post.content = bestFullText;
      }

      // 3. Extract likes / comments if still 0
      if (!post.likes_count) {
        const rMatch = html.match(/"reaction_count"\s*:\s*\{\s*"count"\s*:\s*(\d+)/);
        if (rMatch) post.likes_count = parseInt(rMatch[1], 10) || 0;
      }
      if (!post.comments_count) {
        const cMatch = html.match(/"comment_count"\s*:\s*\{\s*"total_count"\s*:\s*(\d+)/);
        if (cMatch) post.comments_count = parseInt(cMatch[1], 10) || 0;
      }
    } catch (_) {}
  }

  // Layer 2: Live rendered DOM feed posts (supports BOTH div[aria-posinset] on New Pages AND div[role="article"])
  const feedContainers = Array.from(document.querySelectorAll('div[aria-posinset], div[role="article"]'));
  const basePageUrl = window.location.href.split('#')[0];

  for (const article of feedContainers) {
    if (posts.length >= 10) break;

    if (article.parentElement?.closest('div[role="article"]')) continue;
    if (article.hasAttribute('aria-posinset') && article.querySelector('div[role="article"]')) continue;

    // 1. Trigger Comet lazy-href hydration & find best permalink (prioritizing /reel/, /videos/, /posts/)
    let linkEl = findBestPostLink(article);
    if (!linkEl) {
      const headerLinks = article.querySelectorAll('a[role="link"]');
      for (const hl of headerLinks) {
        try { hl.dispatchEvent(new MouseEvent('mouseover', { bubbles: true })); } catch (_) {}
      }
      await new Promise((r) => setTimeout(r, 120));
      linkEl = findBestPostLink(article);
    }

    // 2. Extract post text content
    let content = '';
    const msgContainer =
      article.querySelector('div[data-ad-preview="message"]') ||
      article.querySelector('div[data-ad-comet-preview="message"]') ||
      article.querySelector('div[data-ad-rendering-role="story_message"]');
    if (msgContainer) {
      content = (msgContainer.innerText || '').trim();
    } else {
      const dirAutos = article.querySelectorAll('div[dir="auto"]');
      const chunks = [];
      for (const d of dirAutos) {
        if (d.closest('a[role="link"]')) continue;
        const t = (d.innerText || '').trim();
        if (
          t.length >= 12 &&
          !/^(Suka|Komentari|Bagikan|Kirim|Like|Comment|Share|Send|Lihat selengkapnya|See more|Semua komentar|All comments|Tulis komentar)/i.test(t)
        ) {
          chunks.push(t);
        }
      }
      content = chunks.slice(0, 4).join('\n').trim();
    }

    // 3. Extract thumbnail image
    let mediaUrl = null;
    const imgs = article.querySelectorAll('img[src*="scontent"]');
    let bestArea = 0;
    for (const img of imgs) {
      const rect = img.getBoundingClientRect();
      const area = (rect.width || img.naturalWidth || 0) * (rect.height || img.naturalHeight || 0);
      if (area > bestArea && (rect.width >= 100 || img.naturalWidth >= 100)) {
        bestArea = area;
        mediaUrl = img.getAttribute('src');
      }
    }

    if (!content && !mediaUrl) continue;

    const realUrl = normalizeFbUrl(linkEl?.getAttribute('href'));
    const sig = simpleHash((content || '').slice(0, 120) + '|' + (mediaUrl || '').split('?')[0]);
    const postUrl = realUrl || `${basePageUrl}${basePageUrl.includes('?') ? '&' : '?'}sw_post=${sig}`;

    const contentSig = content ? 'txt_' + simpleHash(content.slice(0, 80)) : null;
    if (seenKeys.has(postUrl) || (contentSig && seenKeys.has(contentSig))) continue;

    let mediaType = 'text';
    if (postUrl.includes('/videos/') || postUrl.includes('/reel/')) {
      mediaType = 'video';
    } else if (mediaUrl) {
      mediaType = 'image';
    }

    let likes = 0;
    let comments = 0;
    const ariaEls = article.querySelectorAll('[aria-label]');
    for (const el of ariaEls) {
      const lbl = el.getAttribute('aria-label') || '';
      if (!likes) {
        const lm = lbl.match(/(?:Suka|Like|Reaksi|Reactions?)[:\s]*([\d.,]+\s*(?:rb|jt|k|m)?)/i)
          || lbl.match(/([\d.,]+\s*(?:rb|jt|k|m)?)\s*(?:orang|people|reaksi|reactions?|suka|likes?)/i);
        if (lm) likes = parseCompactNumber(lm[1]);
      }
      if (!comments) {
        const cm = lbl.match(/([\d.,]+\s*(?:rb|jt|k|m)?)\s*(?:komentar|comments?)/i);
        if (cm) comments = parseCompactNumber(cm[1]);
      }
    }

    const timeText =
      linkEl?.getAttribute('aria-label') ||
      article.querySelector('abbr')?.getAttribute('title') ||
      article.querySelector('abbr')?.innerText ||
      linkEl?.innerText ||
      '';
    const parsedTime = parseRelativeFbTime(timeText);

    seenKeys.add(postUrl);
    if (contentSig) seenKeys.add(contentSig);
    posts.push({
      external_id: `fb_${sig}`,
      url: postUrl,
      content: content,
      media_type: mediaType,
      likes_count: likes,
      comments_count: comments,
      views_count: 0,
      posted_at: parsedTime || new Date().toISOString(),
      _hasRealTimestamp: Boolean(parsedTime),
      media_url: mediaUrl,
    });
  }

  // Layer 3: Direct message-block extractor (always run if < 10 posts so scrolled DOM posts are never skipped when Layer 1 found only 1 SSR post!)
  if (posts.length < 10) {
    const msgBlocks = document.querySelectorAll(
      'div[data-ad-preview="message"], div[data-ad-comet-preview="message"], div[data-ad-rendering-role="story_message"]'
    );
    for (const block of msgBlocks) {
      if (posts.length >= 10) break;
      const content = (block.innerText || '').trim();
      if (content.length < 5) continue;

      const sig = simpleHash(content.slice(0, 120));
      const contentSig = 'txt_' + simpleHash(content.slice(0, 80));
      if (seenKeys.has(contentSig)) continue;

      // Walk up to 12 levels to capture the full post card (including Reel/Video/Photo/Post links)
      let card = block;
      for (let up = 0; up < 12 && card.parentElement; up++) {
        card = card.parentElement;
        if (card.querySelector(FB_LINK_SELECTOR)) {
          break;
        }
      }

      const linkEl = findBestPostLink(card);
      const realUrl = normalizeFbUrl(linkEl?.getAttribute('href'));
      const postUrl = realUrl || `${basePageUrl}${basePageUrl.includes('?') ? '&' : '?'}sw_post=${sig}`;

      let mediaUrl = null;
      for (const img of card.querySelectorAll('img[src*="scontent"]')) {
        const rect = img.getBoundingClientRect();
        if (rect.width >= 100 || img.naturalWidth >= 100) {
          mediaUrl = img.getAttribute('src');
          break;
        }
      }

      const timeText =
        linkEl?.getAttribute('aria-label') ||
        card.querySelector('abbr')?.getAttribute('title') ||
        card.querySelector('abbr')?.innerText ||
        linkEl?.innerText ||
        '';
      const parsedTime = parseRelativeFbTime(timeText);

      seenKeys.add(postUrl);
      seenKeys.add(contentSig);
      posts.push({
        external_id: `fb_${sig}`,
        url: postUrl,
        content,
        media_type: (postUrl.includes('/reel/') || postUrl.includes('/videos/')) ? 'video' : (mediaUrl ? 'image' : 'text'),
        likes_count: 0,
        comments_count: 0,
        views_count: 0,
        posted_at: parsedTime || new Date().toISOString(),
        _hasRealTimestamp: Boolean(parsedTime),
        media_url: mediaUrl,
      });
    }
  }

  // Enrich any post that still has a truncated "... See more" caption OR lacks a real publish timestamp
  const finalPosts = posts.slice(0, 10);
  for (const post of finalPosts) {
    if (!post._hasRealTimestamp || isTruncatedCaption(post.content)) {
      await enrichFbPost(post);
      await new Promise((r) => setTimeout(r, 250));
    }
    // Clean any leftover "... See more" / "Lihat selengkapnya" artifact
    if (post.content) {
      post.content = post.content.replace(/(?:…|\.\.\.)?\s*(?:see more|lihat selengkapnya)\s*$/i, '').trim();
    }
  }

  const debugInfo = finalPosts.length === 0
    ? `url=${window.location.href} | title="${(document.title || '').slice(0, 40)}" | articles=${document.querySelectorAll('div[role="article"]').length} | posinset=${document.querySelectorAll('div[aria-posinset]').length} | msgs=${document.querySelectorAll('div[data-ad-preview="message"],div[data-ad-comet-preview="message"],div[data-ad-rendering-role="story_message"]').length} | scripts=${scripts.length}`
    : null;

  return {
    ok: true,
    method: finalPosts.length > 0 ? 'fb_comet+enrich' : 'fb_profile_only',
    profile,
    posts: finalPosts,
    debug: debugInfo,
  };
}

async function fetchFacebookAccountViaTab(tabId, acc) {
  const ONE_DAY_MS = 24 * 60 * 60 * 1000;
  const ONE_WEEK_MS = 7 * 24 * 60 * 60 * 1000;

  let targetUrl = acc.profile_url || '';
  if (!targetUrl) {
    const uname = String(acc.username || '').trim();
    targetUrl = /^\d+$/.test(uname)
      ? `https://www.facebook.com/profile.php?id=${uname}`
      : `https://www.facebook.com/${encodeURIComponent(uname)}`;
  }

  await navigateTab(tabId, targetUrl);
  await sleep(1200);

  // Initial light scroll so top 2-3 posts (including first non-pinned post) render
  try {
    await chrome.scripting.executeScript({
      target: { tabId },
      func: () => { window.scrollBy({ top: 700, behavior: 'smooth' }); },
    });
  } catch (_) {}
  await sleep(1600);

  const runExtract = async () => {
    const exec = await chrome.scripting.executeScript({
      target: { tabId },
      func: inPageExtractFacebookProfile,
      args: [acc.username],
    });
    return exec?.[0]?.result;
  };

  let fbRes = await runExtract();
  if (!fbRes || !fbRes.ok) {
    const err = new Error(fbRes?.reason || `Gagal mengekstrak data Facebook ${acc.username}.`);
    if (fbRes?.notFound) err.isNotFound = true;
    throw err;
  }

  const now = Date.now();
  const getTimestamps = (list) =>
    (list || [])
      .filter((p) => p._hasRealTimestamp)
      .map((p) => Date.parse(p.posted_at))
      .filter((t) => !isNaN(t) && t <= now + 60000);

  let timestamps = getTimestamps(fbRes.posts);

  // Rule 1: If newest post is > 1 week old -> SKIP immediately without further scrolling
  if (timestamps.length > 0) {
    const newestTs = Math.max(...timestamps);
    const newestAgeMs = now - newestTs;
    if (newestAgeMs > ONE_WEEK_MS) {
      const daysAgo = Math.floor(newestAgeMs / ONE_DAY_MS);
      return {
        ok: true,
        skipped: true,
        skipReason: `Postingan terakhir ${daysAgo} hari lalu (> 1 minggu) — dilewati.`,
        method: fbRes.method,
        profile: fbRes.profile,
        posts: [],
      };
    }

    // Rule 2: If newest post <= 1 day (today / <= 24h), keep scrolling gently until we cross the 1-day mark (max 3 extra scrolls)
    if (newestAgeMs <= ONE_DAY_MS) {
      for (let s = 0; s < 3; s++) {
        const oldestTs = Math.min(...timestamps);
        if (now - oldestTs > ONE_DAY_MS) break; // Already reached posts older than 1 day

        try {
          await chrome.scripting.executeScript({
            target: { tabId },
            func: () => { window.scrollBy({ top: 1000, behavior: 'smooth' }); },
          });
        } catch (_) {}
        await sleep(1600);

        const nextRes = await runExtract();
        if (nextRes && nextRes.ok && Array.isArray(nextRes.posts)) {
          fbRes = nextRes;
          timestamps = getTimestamps(fbRes.posts);
          if (timestamps.length === 0) break;
        }
      }
    }

    // Filter: Exclude posts whose verified real timestamp is > 1 day ago (keep today's & 1-day-ago posts)
    const recentPosts = (fbRes.posts || []).filter((p) => {
      if (!p._hasRealTimestamp) return true;
      const ts = Date.parse(p.posted_at);
      return isNaN(ts) || (now - ts) <= ONE_DAY_MS;
    });

    if (recentPosts.length === 0) {
      const daysAgo = Math.max(1, Math.floor((now - newestTs) / ONE_DAY_MS));
      return {
        ok: true,
        skipped: true,
        skipReason: `Tidak ada postingan baru dalam 1 hari terakhir (terakhir ${daysAgo} hari lalu) — dilewati.`,
        method: fbRes.method,
        profile: fbRes.profile,
        posts: [],
      };
    }

    fbRes.posts = recentPosts;
  }

  return fbRes;
}

async function ingestToBackend(backendUrl, syncToken, platform, acc, profile, posts) {
  const endpoint = platform === 'facebook'
    ? `${backendUrl}/api/sync/facebook`
    : `${backendUrl}/api/sync/instagram`;

  const res = await fetch(endpoint, {
    method: 'POST',
    headers: buildBackendHeaders(syncToken, true),
    body: JSON.stringify({
      account_id: acc.id,
      username: acc.username,
      profile,
      posts,
    }),
  });

  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(data.message || `HTTP ${res.status}`);
  }
  return data;
}

async function reportStatusToBackend(backendUrl, syncToken, platform, acc, status, message) {
  try {
    const endpoint = `${backendUrl}/api/sync/report-status`;
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: buildBackendHeaders(syncToken, true),
      body: JSON.stringify({
        account_id: acc.id,
        platform,
        username: acc.username,
        status,
        message,
      }),
    });
    return res.ok;
  } catch (_) {
    return false;
  }
}

async function runSyncJob(targets, backendUrl, triggerLabel = 'Manual', syncToken = '') {
  isRunning = true;
  cancelRequested = false;

  await updateState({
    isSyncing: true,
    percent: 0,
    label: 'Membuka sesi latar belakang...',
  });
  setBadge(`0/${targets.length}`, '#4f46e5');

  await appendLog(`=== Memulai Sinkronisasi [${triggerLabel}] untuk ${targets.length} Akun ===`, 'info');
  await appendLog('Proses berjalan di latar belakang — aman jika pindah tab atau menutup popup.', 'info');

  let igTabId = null;
  let fbTabId = null;
  let successCount = 0;
  let notFoundCount = 0;
  let totalNewPosts = 0;
  let totalUpdatedPosts = 0;

  try {
    for (let i = 0; i < targets.length; i++) {
      if (cancelRequested) {
        await appendLog('Sinkronisasi dibatalkan oleh pengguna.', 'warn');
        break;
      }

      const acc = targets[i];
      const platform = (acc.platform || 'instagram').toLowerCase();
      const platformTag = platform === 'facebook' ? 'FB' : 'IG';
      const currentNum = i + 1;
      const pct = Math.round((i / targets.length) * 100);

      await updateState({
        percent: pct,
        label: `[${currentNum}/${targets.length}] (${platformTag}) ${acc.username}...`,
      });
      setBadge(`${currentNum}/${targets.length}`, '#4f46e5');
      await appendLog(`[${currentNum}/${targets.length}] Memproses [${platformTag}] ${acc.username}...`, 'info');

      try {
        let fetched;
        if (platform === 'facebook') {
          if (fbTabId === null) {
            fbTabId = await createBackgroundIgTab('https://www.facebook.com/');
          }
          fetched = await fetchFacebookAccountViaTab(fbTabId, acc);
        } else {
          if (igTabId === null) {
            igTabId = await createBackgroundIgTab('https://www.instagram.com/');
          }
          fetched = await fetchInstagramAccountViaTab(igTabId, acc.username);
        }

        const { profile, posts, method, debug, skipped, skipReason } = fetched;
        if (skipped) {
          await appendLog(`  ⏭ Dilewati [${platformTag}] ${acc.username}: ${skipReason}`, 'warn');
          successCount++;
          continue;
        }

        await appendLog(
          `  ✓ Data [${platformTag}] ${acc.username} (${method}): ${profile.followers_count.toLocaleString()} followers, ${posts.length} postingan.`,
          'info'
        );
        if (debug) {
          await appendLog(`  ℹ Diagnostik: ${debug}`, 'warn');
        }

        const ingestResult = await ingestToBackend(backendUrl, syncToken, platform, acc, profile, posts);
        const resData = ingestResult.result || {};
        const newPosts = resData.new_posts || 0;
        const updatedPosts = resData.updated_posts || 0;

        totalNewPosts += newPosts;
        totalUpdatedPosts += updatedPosts;
        successCount++;

        await appendLog(`  ✓ Tersimpan ke SocioWatch! (${newPosts} baru, ${updatedPosts} diperbarui).`, 'success');
      } catch (err) {
        await appendLog(`  ✗ Gagal [${platformTag}] ${acc.username}: ${err.message}`, 'error');
        if (err.isNotFound) {
          notFoundCount++;
          const reported = await reportStatusToBackend(backendUrl, syncToken, platform, acc, 'not_found', err.message);
          if (reported) {
            await appendLog(`  ⚠ Status ${acc.username} otomatis ditandai 'not_found' di SocioWatch.`, 'warn');
          }
        }
      }

      if (i < targets.length - 1 && !cancelRequested) {
        // Gentle delay: 2.5s for Facebook, 1.2s for Instagram
        await sleep(platform === 'facebook' ? 2500 : 1200);
      }
    }
  } catch (fatalErr) {
    await appendLog(`✗ Kesalahan sesi browser: ${fatalErr.message}`, 'error');
  } finally {
    if (igTabId !== null) {
      try { await chrome.tabs.remove(igTabId); } catch (_) {}
    }
    if (fbTabId !== null) {
      try { await chrome.tabs.remove(fbTabId); } catch (_) {}
    }

    await appendLog(`=== Selesai: ${successCount}/${targets.length} akun berhasil disinkronkan ===`, 'success');
    await appendLog(`Total Postingan Baru: ${totalNewPosts} | Diperbarui: ${totalUpdatedPosts}`, 'success');

    await updateState({
      isSyncing: false,
      percent: 100,
      label: cancelRequested ? 'Dibatalkan' : 'Selesai!',
      lastFinishedAt: Date.now(),
    });

    setBadge(cancelRequested ? '' : 'OK', '#10b981');
    isRunning = false;
    cancelRequested = false;

    setTimeout(() => {
      if (!isRunning) setBadge('');
    }, 15000);
  }
}

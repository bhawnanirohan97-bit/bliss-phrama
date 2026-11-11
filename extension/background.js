// Background service worker for Bliss Lead Auto-Buyer

chrome.runtime.onInstalled.addListener(() => {
  chrome.storage.local.get(['autoBuyEnabled', 'apiUrl', 'dailyLimit', 'boughtCount', 'lastDate', 'logs'], (data) => {
    const today = new Date().toISOString().slice(0, 10);
    const defaults = {
      autoBuyEnabled: true,
      apiUrl: 'http://127.0.0.1:8000/api/ai-bot/evaluate',
      dailyLimit: 10,
      boughtCount: 0,
      lastDate: today,
      logs: []
    };

    chrome.storage.local.set({ ...defaults, ...data });
  });
});

// Reset count if new day
async function checkDailyReset() {
  const data = await chrome.storage.local.get(['boughtCount', 'lastDate']);
  const today = new Date().toISOString().slice(0, 10);
  if (data.lastDate !== today) {
    await chrome.storage.local.set({ boughtCount: 0, lastDate: today });
  }
}

// Listen for messages from content.js and popup.js
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'EVALUATE_LEAD') {
    handleEvaluateLead(request.leadData).then(sendResponse);
    return true; // Keep message channel open for async response
  }

  if (request.action === 'LEAD_BOUGHT') {
    handleLeadBought(request.leadData).then(sendResponse);
    return true;
  }

  if (request.action === 'LOG') {
    addLog(request.message, request.type);
    sendResponse({ status: 'ok' });
    return true;
  }
});

async function handleEvaluateLead(leadData) {
  await checkDailyReset();
  const config = await chrome.storage.local.get(['autoBuyEnabled', 'apiUrl', 'dailyLimit', 'boughtCount']);

  if (!config.autoBuyEnabled) {
    return { shouldBuy: false, reason: 'Auto-buy is currently paused in extension' };
  }

  if (config.boughtCount >= config.dailyLimit) {
    return { shouldBuy: false, reason: `Daily limit reached (${config.boughtCount}/${config.dailyLimit})` };
  }

  const endpoint = config.apiUrl || 'http://127.0.0.1:8000/api/ai-bot/evaluate';

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(leadData)
    });

    if (!response.ok) {
      const errText = await response.text();
      return { shouldBuy: false, reason: `API error HTTP ${response.status}: ${errText}` };
    }

    const result = await response.json();

    if (result.enabled && result.eligible) {
      return {
        shouldBuy: true,
        reason: 'AI Approved',
        matches: result.matches || []
      };
    } else {
      return {
        shouldBuy: false,
        reason: result.reason || 'AI Rejected (Did not match criteria)',
        matches: result.matches || []
      };
    }
  } catch (error) {
    return {
      shouldBuy: false,
      reason: `Cannot reach local AI at ${endpoint}. Make sure 'php artisan serve' is running.`
    };
  }
}

async function handleLeadBought(leadData) {
  await checkDailyReset();
  const data = await chrome.storage.local.get(['boughtCount']);
  const newCount = (data.boughtCount || 0) + 1;
  await chrome.storage.local.set({ boughtCount: newCount });

  const desc = leadData.product || leadData.product_name || leadData.inquiry_text || 'Lead';
  const country = leadData.country || leadData.destination_country || 'Foreign';
  addLog(`✅ BOUGHT LEAD: ${desc.slice(0, 40)}... [${country}] (Total today: ${newCount})`, 'success');

  // Sync to local dashboard database immediately so it shows up in Inbox & Dashboard
  try {
    const recordEndpoint = 'http://127.0.0.1:8000/api/ai-bot/record-lead';
    const resp = await fetch(recordEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(leadData)
    });
    const saved = await resp.json();
    console.log('[Bliss Auto-Buyer] Lead saved to local database:', saved);
  } catch (err) {
    console.warn('[Bliss Auto-Buyer] Could not sync lead to dashboard database:', err);
  }

  return { newCount };
}

async function addLog(message, type = 'info') {
  const timestamp = new Date().toLocaleTimeString();
  const entry = { time: timestamp, message, type };

  const data = await chrome.storage.local.get(['logs']);
  const logs = data.logs || [];
  logs.unshift(entry);
  if (logs.length > 100) logs.pop();

  await chrome.storage.local.set({ logs });
}

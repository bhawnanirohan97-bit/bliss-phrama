// Popup controller for Bliss Lead Auto-Buyer

document.addEventListener('DOMContentLoaded', async () => {
  const toggleBtn = document.getElementById('toggle-btn');
  const statusBadge = document.getElementById('status-badge');
  const boughtCountEl = document.getElementById('bought-count');
  const limitDisplay = document.getElementById('limit-display');
  const dailyLimitInput = document.getElementById('daily-limit');
  const logsContainer = document.getElementById('logs-container');

  // Load state from chrome.storage
  chrome.storage.local.get(['autoBuyEnabled', 'boughtCount', 'dailyLimit', 'logs'], (data) => {
    const isEnabled = data.autoBuyEnabled !== false;
    updateStatusUI(isEnabled);

    const count = data.boughtCount || 0;
    const limit = data.dailyLimit || 10;
    boughtCountEl.innerText = count;
    limitDisplay.innerText = limit;
    dailyLimitInput.value = limit;

    renderLogs(data.logs || []);
  });

  // Toggle button click
  toggleBtn.addEventListener('click', () => {
    chrome.storage.local.get(['autoBuyEnabled'], (data) => {
      const newState = !(data.autoBuyEnabled !== false);
      chrome.storage.local.set({ autoBuyEnabled: newState }, () => {
        updateStatusUI(newState);
      });
    });
  });

  // Daily limit change
  dailyLimitInput.addEventListener('change', () => {
    const val = parseInt(dailyLimitInput.value, 10) || 10;
    chrome.storage.local.set({ dailyLimit: val }, () => {
      limitDisplay.innerText = val;
    });
  });

  function updateStatusUI(isEnabled) {
    if (isEnabled) {
      statusBadge.innerText = 'Active';
      statusBadge.className = 'badge badge-active';
      toggleBtn.innerText = 'Auto-Buyer is RUNNING (Click to Pause)';
      toggleBtn.className = 'btn btn-toggle-on';
    } else {
      statusBadge.innerText = 'Paused';
      statusBadge.className = 'badge badge-paused';
      toggleBtn.innerText = 'Auto-Buyer is PAUSED (Click to Start)';
      toggleBtn.className = 'btn btn-toggle-off';
    }
  }

  function renderLogs(logs) {
    if (!logs || logs.length === 0) return;
    logsContainer.innerHTML = '';
    logs.forEach(item => {
      const div = document.createElement('div');
      div.className = `log-item ${item.type === 'success' ? 'log-success' : (item.type === 'warn' ? 'log-warn' : '')}`;
      div.innerText = `[${item.time}] ${item.message}`;
      logsContainer.appendChild(div);
    });
  }

  // Refresh logs every 2 seconds while popup is open
  setInterval(() => {
    chrome.storage.local.get(['boughtCount', 'logs'], (data) => {
      boughtCountEl.innerText = data.boughtCount || 0;
      renderLogs(data.logs || []);
    });
  }, 2000);
});

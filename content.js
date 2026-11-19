// Bliss Pharmex - IndiaMART Lead Auto-Buyer Content Script (v1.4.0)
// Auto-Refresh Every 12-15s + Dual-World Autonomous Buying + Zero Screen Blur
// Strict Filter Matching: Target Export Countries + 499 Approved Medicines Only

(function () {
  'use strict';

  console.log('%c[Bliss Auto-Buyer v1.4.0] Autonomous Engine with 12-15s Auto-Refresh Active.', 'color: #10b981; font-weight: bold;');

  const processedCards = new WeakSet();
  const buyingInProgressCards = new WeakSet();
  let statusBanner = null;
  let isHudMinimized = false;
  let detectedCardsCount = 0;
  let lastActionMsg = 'Scanning for matching export leads...';
  let isPurchasingActive = false;

  // Defensive Chrome Extension API Wrappers (prevents "Extension context invalidated" & undefined errors)
  function isExtensionValid() {
    try {
      return Boolean(typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.id && chrome.storage && chrome.storage.local);
    } catch (e) {
      return false;
    }
  }

  function safeStorageGet(keys, callback) {
    if (!isExtensionValid()) {
      if (callback) callback({ autoBuyEnabled: true, autoRefreshEnabled: true, boughtCount: 0, dailyLimit: 100 });
      return;
    }
    try {
      chrome.storage.local.get(keys, (res) => {
        try {
          if (chrome.runtime?.lastError || !res) {
            if (callback) callback({ autoBuyEnabled: true, autoRefreshEnabled: true, boughtCount: 0, dailyLimit: 100 });
          } else {
            if (callback) callback(res);
          }
        } catch (e) {
          if (callback) callback({ autoBuyEnabled: true, autoRefreshEnabled: true, boughtCount: 0, dailyLimit: 100 });
        }
      });
    } catch (e) {
      if (callback) callback({ autoBuyEnabled: true, autoRefreshEnabled: true, boughtCount: 0, dailyLimit: 100 });
    }
  }

  function safeSendMessage(msg, callback) {
    if (!isExtensionValid()) {
      if (callback) callback(null);
      return;
    }
    try {
      chrome.runtime.sendMessage(msg, (res) => {
        try {
          if (chrome.runtime?.lastError) {
            if (callback) callback(null);
          } else {
            if (callback) callback(res);
          }
        } catch (e) {
          if (callback) callback(null);
        }
      });
    } catch (e) {
      if (callback) callback(null);
    }
  }

  const pendingApiPurchases = new Map();
  const snipedLeadsMap = new Map();

  // Synchronize rules from localhost:8000 into localStorage for 0ms in-memory sniper
  function syncAiRulesCache() {
    fetch('http://127.0.0.1:8000/api/ai-bot/settings')
      .then(r => r.json())
      .then(settings => {
        if (settings) {
          const rules = {
            enabled: settings.enabled !== false,
            reject_domestic: settings.reject_domestic !== false,
            countries: settings.country_filters || ['US', 'GB', 'AU', 'PH', 'KR', 'JP', 'CN'],
            products: settings.product_filters || []
          };
          localStorage.setItem('bliss_ai_rules', JSON.stringify(rules));
          window.postMessage({ type: 'BLISS_UPDATE_RULES_CACHE', rules }, '*');
          console.log('[Bliss Content] AI rules cache refreshed from server:', rules.products.length, 'products');
        }
      })
      .catch(() => {});
  }
  syncAiRulesCache();

  window.addEventListener('message', (event) => {
    if (!event.data || typeof event.data !== 'object') return;

    if (event.data.type === 'BLISS_SNIPER_SUCCESS') {
      const { ofrid, pur_id, matchedProduct, matchedCountry, leadTitle, buyer } = event.data;
      console.log('%c[Bliss Content] PRE-RENDER SNIPED LEAD RECEIVED:', 'color: #10b981; font-weight: bold;', matchedProduct, buyer);

      snipedLeadsMap.set(ofrid, { pur_id, matchedProduct, matchedCountry, buyer, leadTitle });
      if (leadTitle) snipedLeadsMap.set(leadTitle.toLowerCase().trim(), { pur_id, matchedProduct, matchedCountry, buyer });

      const enrichedData = {
        product_name: matchedProduct || leadTitle,
        country: buyer.country || matchedCountry,
        phone: buyer.mobile ? (buyer.mobileCountry ? `+${buyer.mobileCountry} ${buyer.mobile}` : buyer.mobile) : null,
        buyer_name: buyer.name || null,
        external_id: String(pur_id || ofrid)
      };

      safeSendMessage({ action: 'LEAD_BOUGHT', leadData: enrichedData }, (boughtRes) => {
        safeStorageGet(['boughtCount', 'dailyLimit'], (cfg) => {
          const c = cfg || {};
          updateHud(c.boughtCount || 0, c.dailyLimit || 100, true);
        });
      });

      lastActionMsg = `⚡ Sniped Pre-Render: ${matchedProduct} (${matchedCountry})`;
    }

    if (event.data.type === 'BLISS_API_BUY_SUCCESS') {
      const { targetId, ofrid, pur_id, buyer } = event.data;
      const callback = pendingApiPurchases.get(targetId);
      if (callback) {
        pendingApiPurchases.delete(targetId);
        callback(true, { ofrid, pur_id, buyer });
      }
    }

    if (event.data.type === 'BLISS_API_BUY_FAILED') {
      const { targetId, reason } = event.data;
      const callback = pendingApiPurchases.get(targetId);
      if (callback) {
        pendingApiPurchases.delete(targetId);
        callback(false, { reason });
      }
    }
  });

  // Auto-refresh interval randomized between 12 and 15 seconds
  function getRandomInterval() {
    return Math.floor(Math.random() * (15 - 12 + 1)) + 12;
  }
  let refreshCountdown = getRandomInterval();

  // Prevent background blur or overlay freeze
  function clearAnyPageBlurAndUnlock() {
    try {
      if (document.documentElement.style.filter) document.documentElement.style.filter = 'none';
      if (document.documentElement.style.backdropFilter) document.documentElement.style.backdropFilter = 'none';
      if (document.body.style.filter) document.body.style.filter = 'none';
      if (document.body.style.backdropFilter) document.body.style.backdropFilter = 'none';

      const blurredElements = document.querySelectorAll('[style*="blur"], .blur_bg, .backdrop-blur');
      for (const el of blurredElements) {
        if (el.id !== 'bliss-autobuy-hud' && !el.closest('#bliss-autobuy-hud')) {
          el.style.filter = 'none';
          el.style.backdropFilter = 'none';
        }
      }

      const orphanedOverlays = document.querySelectorAll('.gl_overlay, .modal-backdrop:not(.show), div[class*="backdrop"]:not(.show)');
      for (const ov of orphanedOverlays) {
        ov.style.display = 'none';
        ov.style.pointerEvents = 'none';
      }
    } catch (e) {}
  }

  // Dual-World Click Trigger: executes in both Content Script and Page World (with jQuery)
  function triggerClick(el) {
    if (!el) return;
    try { el.focus(); } catch (e) {}

    const interactive = el.closest('button, a, input[type="button"], input[type="submit"], [role="button"]') || el;
    const targetId = 'bliss_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
    
    interactive.setAttribute('data-bliss-target', targetId);
    if (el !== interactive) el.setAttribute('data-bliss-target', targetId);

    // 1. Tell Injected Main World script to trigger jQuery and native click in page scope
    window.postMessage({ type: 'BLISS_PAGE_CLICK', targetId: targetId }, '*');

    // 2. Trigger synthetic pointer and mouse events in content script scope
    const targets = interactive === el ? [el] : [interactive, el];
    for (const target of targets) {
      try {
        const opts = { bubbles: true, cancelable: true, view: window, button: 0, buttons: 1, detail: 1 };
        target.dispatchEvent(new PointerEvent('pointerdown', opts));
        target.dispatchEvent(new MouseEvent('mousedown', opts));
        target.dispatchEvent(new PointerEvent('pointerup', opts));
        target.dispatchEvent(new MouseEvent('mouseup', opts));
        target.dispatchEvent(new MouseEvent('click', opts));
        if (typeof target.click === 'function') {
          target.click();
        }
      } catch (e) {}
    }
  }

  function fillInput(el, text) {
    if (!el) return;
    try { el.focus(); } catch (e) {}
    el.value = text;
    try {
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {}
  }

  // Non-intrusive floating HUD in bottom-right corner with live countdown
  function updateHud(boughtCount = 0, limit = 100, isRunning = true) {
    if (!statusBanner) {
      statusBanner = document.createElement('div');
      statusBanner.id = 'bliss-autobuy-hud';
      statusBanner.style.cssText = `
        position: fixed;
        bottom: 16px;
        right: 16px;
        background: #090d16;
        color: #f1f5f9;
        padding: 10px 14px;
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 12px;
        font-weight: 500;
        z-index: 999999;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        border: 1px solid #1e293b;
        display: flex;
        flex-direction: column;
        gap: 5px;
        user-select: none;
        max-width: 340px;
        pointer-events: auto;
      `;
      document.body.appendChild(statusBanner);
    }

    const dotColor = isRunning ? '#10b981' : '#f59e0b';
    const statusText = isRunning ? 'Auto-Buy ON' : 'Paused';
    const refreshText = isRunning ? `${refreshCountdown}s` : 'Paused';

    if (isHudMinimized) {
      statusBanner.innerHTML = `
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; cursor:pointer;" id="hud-expand-btn">
          <div style="display:flex; align-items:center; gap:6px;">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${dotColor};box-shadow:0 0 8px ${dotColor};"></span>
            <span style="font-weight:600;color:#fff;font-size:12px;">Bliss AI: ${boughtCount}/${limit} (⟳ ${refreshText})</span>
          </div>
          <span style="color:#94a3b8;font-size:11px;background:#1e293b;padding:1px 6px;border-radius:3px;">+</span>
        </div>
      `;
      const expandBtn = statusBanner.querySelector('#hud-expand-btn');
      if (expandBtn) {
        expandBtn.onclick = (e) => {
          e.stopPropagation();
          isHudMinimized = false;
          updateHud(boughtCount, limit, isRunning);
        };
      }
      return;
    }

    statusBanner.innerHTML = `
      <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
        <div style="display:flex; align-items:center; gap:6px;">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${dotColor};box-shadow:0 0 8px ${dotColor};"></span>
          <span style="font-weight:600;color:#fff;font-size:12px;">Bliss AI: ${statusText}</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
          <span style="background:#1e293b;padding:2px 6px;border-radius:4px;font-size:11px;color:#94a3b8;">${boughtCount}/${limit}</span>
          <button id="hud-minimize-btn" style="background:#1e293b;border:none;color:#94a3b8;cursor:pointer;padding:2px 5px;border-radius:3px;font-size:11px;" title="Minimize to small icon">−</button>
        </div>
      </div>
      <div style="font-size:11px;color:#94a3b8;display:flex;justify-content:space-between;gap:8px;border-top:1px solid #1e293b;padding-top:4px;">
        <span>On Screen: <strong style="color:#e2e8f0;">${detectedCardsCount}</strong></span>
        <span>Auto-Refresh: <strong style="color:#38bdf8;font-weight:600;">${refreshText}</strong></span>
        <span id="hud-rescan-btn" style="color:#10b981;text-decoration:underline;cursor:pointer;">Refresh Now</span>
      </div>
      <div style="font-size:11px;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${lastActionMsg}">
        ${lastActionMsg}
      </div>
    `;

    const minBtn = statusBanner.querySelector('#hud-minimize-btn');
    if (minBtn) {
      minBtn.onclick = (e) => {
        e.stopPropagation();
        isHudMinimized = true;
        updateHud(boughtCount, limit, isRunning);
      };
    }

    const rescanBtn = statusBanner.querySelector('#hud-rescan-btn');
    if (rescanBtn) {
      rescanBtn.onclick = (e) => {
        e.stopPropagation();
        window.location.reload();
      };
    }
  }

  // Traverses up to reliably locate the entire card container
  function findCardElement(btn) {
    if (btn.closest('#left_col, #filter_panel, .filter_section, .sidebar, #header, header, footer, #footer, nav, .nav, [class*="sidebar"], [class*="filter"]')) {
      return null;
    }

    const directMatch = btn.closest(
      '.bl_card, .bld_card, .blp_card, .glp_card, .listing-card, .listing-item, [id*="bl_"], [id*="lead"], [class*="bld_"], [class*="lead"], li.card, li'
    );
    if (directMatch && directMatch !== document.body && directMatch !== document.documentElement) {
      if (directMatch.closest('#left_col, #filter_panel, .filter_section, .sidebar, [class*="sidebar"], [class*="filter"]')) {
        return null;
      }
      return directMatch;
    }

    let current = btn.parentElement;
    let fallback = null;
    while (current && current !== document.body && current !== document.documentElement) {
      if (current.closest && current.closest('#left_col, #filter_panel, .filter_section, .sidebar, [class*="sidebar"], [class*="filter"]')) {
        return null;
      }
      const hasTitle = current.querySelector('h1, h2, h3, h4, .bld_pname, .prod_name, [class*="pname"], [class*="title"], [class*="prod_heading"]');
      const text = (current.innerText || '').trim();
      if (hasTitle && text.length > 40) {
        fallback = current;
        if (current.parentElement && current.parentElement.children.length === 1 && current.parentElement !== document.body) {
          return current.parentElement;
        }
        return fallback;
      }
      current = current.parentElement;
    }

    return fallback || btn.parentElement?.parentElement || btn;
  }

  // Find candidate BuyLead cards and action buttons on IndiaMART
  function findLeadCards() {
    const allClickables = Array.from(document.querySelectorAll(
      'button, a, input[type="button"], input[type="submit"], [role="button"], div[class*="btn"], span[class*="btn"], div[id*="btn"], [class*="contact"], [id*="contact"], [class*="buy"], [id*="buy"], [class*="quotation"], [class*="reply"], .bl_cbtn, .bld_btn'
    ));

    const candidateButtons = allClickables.filter(el => {
      try {
        if (el.offsetParent === null && window.getComputedStyle(el).display === 'none') return false;

        // Strictly ignore navigation, header, footer, and filter sidebar buttons
        if (el.closest('#left_col, #filter_panel, .filter_section, .sidebar, #header, header, footer, #footer, nav, .nav, [class*="sidebar"], [class*="filter"]')) {
          return false;
        }

        const rawTxt = (el.innerText || el.value || el.getAttribute('title') || el.getAttribute('aria-label') || '');
        const text = (typeof rawTxt === 'string' ? rawTxt : String(rawTxt || '')).trim().toLowerCase();
        if (text.length > 70 || text.length < 2) return false;

        // Skip already bought / revealed buttons
        if (text.includes('view contact') || text.includes('already contacted') || text.includes('contacted') || text.includes('quotation sent')) {
          return false;
        }

        return (
          text.includes('contact buyer') ||
          text.includes('contact now') ||
          text.includes('send quotation') ||
          text.includes('buy lead') ||
          text.includes('connect now') ||
          text.includes('reply now') ||
          text.includes('send reply')
        );
      } catch (e) {
        return false;
      }
    });

    const results = [];
    candidateButtons.forEach(btn => {
      const card = findCardElement(btn);
      if (card) {
        const leadData = extractLeadData(card);
        // Only accept if card has a genuine medicine/product title
        if (leadData.product_name && leadData.product_name.length >= 3) {
          const lower = leadData.product_name.toLowerCase();
          if (lower !== 'location' && lower !== 'suggested countries' && !lower.includes('filter')) {
            results.push({ card, button: btn, leadData });
          }
        }
      }
    });

    detectedCardsCount = results.length;
    return results;
  }

  // Extract structured lead information from card DOM
  function extractLeadData(card) {
    const fullText = (card.innerText || '').trim();

    // 1. Product Name extraction from top title/headline
    const titleEl = card.querySelector(
      'h1, h2, h3, h4, .bld_pname, .prod_name, .prod-name, [class*="pname"], [class*="title"], [class*="prod_heading"], .lead-title, a[href*="blp"], a[href*="buylead"], strong'
    );
    let product = titleEl ? titleEl.innerText.trim() : '';

    if (!product && fullText) {
      const firstLine = fullText.split('\n')[0].trim();
      if (firstLine.length < 80) {
        product = firstLine;
      }
    }

    // 2. Country / Location extraction
    const countryEl = card.querySelector(
      '.cntry_name, .country, [class*="country"], [class*="cntry"]'
    );
    const locationEl = card.querySelector(
      '.bld_loc, .loc_txt, .location, .city, .buyer-loc, [class*="loc"], [class*="city"]'
    );

    let countryText = countryEl ? (countryEl.innerText || countryEl.getAttribute('title') || countryEl.getAttribute('alt') || '').trim() : '';
    let locationText = locationEl ? locationEl.innerText.trim() : '';

    // Check subtitle line near time & flag (e.g. "12 mins ago | Reunion" or "[flag] Reunion")
    if (!countryText) {
      const timeOrFlag = card.querySelector('.bld_time, [class*="time"], [class*="flag"], i.flag, span.flag, img[src*="flag"], img[alt*="flag"]');
      if (timeOrFlag && timeOrFlag.parentElement) {
        const sublineText = (timeOrFlag.parentElement.innerText || '').replace(/\d+\s*(?:mins?|hours?|days?|secs?)\s*ago/gi, '').replace(/[|•·-]/g, '').trim();
        if (sublineText.length >= 2 && sublineText.length < 50) {
          countryText = sublineText;
        }
      }
    }

    if (!countryText) {
      const imgs = Array.from(card.querySelectorAll('img'));
      for (const img of imgs) {
        const alt = (img.getAttribute('alt') || img.getAttribute('title') || '').trim();
        const src = (img.getAttribute('src') || '').toLowerCase();
        if (src.includes('flag') || src.includes('country') || (alt.length > 2 && alt.length < 30)) {
          countryText = alt;
          break;
        }
      }
    }

    // 3. Quantity extraction
    let quantity = null;
    const qtyMatch = fullText.match(/(?:quantity|qty|requirement)[:\s]*([0-9,.]+)\s*([a-zA-Z]+)?/i);
    if (qtyMatch) {
      quantity = qtyMatch[1].replace(/,/g, '');
    }

    return {
      product_name: product,
      country: countryText,
      location_text: (locationText ? locationText + ' ' + countryText : fullText.slice(0, 500)),
      inquiry_text: fullText.slice(0, 1500),
      quantity: quantity
    };
  }

  // Fully Autonomous Modal Confirmation & Auto-Send Message Handler
  function handleAutonomousPurchase(card, button, productName, onVerified) {
    isPurchasingActive = true;
    let attempts = 0;
    const maxAttempts = 36; // 36 x 250ms = 9 seconds
    let confirmedSuccess = false;

    console.log('[Bliss Auto-Buyer] Monitoring DOM for modal confirmation and purchase completion...');

    const timer = setInterval(() => {
      attempts++;
      if (attempts > maxAttempts || confirmedSuccess) {
        clearInterval(timer);
        isPurchasingActive = false;
        if (!confirmedSuccess) {
          const openModals = document.querySelectorAll('.modal, .popup, .dialog, div[class*="popup"], div[class*="modal"]');
          for (const m of openModals) {
            const closeBtn = m.querySelector('button.close, .close, [class*="close"], [title*="Close"]');
            if (closeBtn) triggerClick(closeBtn);
          }
          clearAnyPageBlurAndUnlock();
        }
        return;
      }

      // 1. Dispatch page-level autonomous modal submit via Injected Main World
      window.postMessage({ type: 'BLISS_PAGE_SUBMIT_MODAL' }, '*');

      // 2. Check: Did the button change or did actual phone/contact details appear?
      const btnRawTxt = (button.innerText || button.value || '').trim().toLowerCase();
      const cardText = (card.innerText || '').trim();
      const phoneMatch = cardText.match(/(?:\+?91[\s-]?)?[6-9]\d{9}|\+?[1-9]\d{1,3}[\s-]?\(?\d{2,4}\)?[\s-]?\d{3,5}[\s-]?\d{3,5}/);
      const isContactRevealed = btnRawTxt.includes('view contact') ||
                                btnRawTxt.includes('contacted') ||
                                btnRawTxt.includes('quotation sent') ||
                                !card.contains(button) ||
                                (card.querySelector('[class*="mobile"], [class*="phone"], [class*="contact_no"], [class*="bld_mob"]') && phoneMatch && phoneMatch[0].length >= 8);

      if (isContactRevealed && !confirmedSuccess) {
        console.log('[Bliss Auto-Buyer] Verified: Lead purchase confirmed on card DOM! Phone:', phoneMatch ? phoneMatch[0] : 'Revealed');
        confirmedSuccess = true;
        isPurchasingActive = false;
        clearInterval(timer);
        clearAnyPageBlurAndUnlock();
        onVerified({ phone: phoneMatch ? phoneMatch[0].trim() : null });
        return;
      }

      // 3. Inspect active modals in Content Script
      const containers = Array.from(document.querySelectorAll(
        '#cb_modal, #cont_buyer_popup, #contact_buyer_popup, #gl_contact_buyer, #bld_modal, .modal, .popup, .dialog, .ui-dialog, div[class*="cb_"], div[id*="cb_"], div[class*="popup"], div[class*="modal"], .bld_modal'
      )).filter(el => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
      });

      for (const container of targets(containers)) {
        const modalText = (container.innerText || '').toLowerCase();

        // Check if modal displays success confirmation
        if (modalText.includes('quotation sent') || modalText.includes('successfully contacted') || modalText.includes('contact details') || modalText.includes('lead consumed')) {
          console.log('[Bliss Auto-Buyer] Verified: Success message detected inside modal dialog!');
          confirmedSuccess = true;
          isPurchasingActive = false;
          clearInterval(timer);

          setTimeout(() => {
            const closeBtn = container.querySelector('button.close, .close, [class*="close"], [title*="Close"]');
            if (closeBtn) triggerClick(closeBtn);
            clearAnyPageBlurAndUnlock();
          }, 800);

          onVerified({});
          return;
        }

        // A. Auto-select product radio button / product option
        const radios = Array.from(container.querySelectorAll('input[type="radio"], .cb_prod_list li, .prod_list li, [class*="prod_item"]'));
        if (radios.length > 0) {
          const first = radios[0];
          if (first.tagName === 'INPUT' && !radios.some(r => r.checked)) {
            first.checked = true;
            first.dispatchEvent(new Event('change', { bubbles: true }));
          }
          triggerClick(first);
        }

        // B. Auto-select dropdown if present
        const selects = Array.from(container.querySelectorAll('select'));
        for (const sel of selects) {
          if (sel.options.length > 1 && sel.selectedIndex <= 0) {
            sel.selectedIndex = 1;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }

        // C. Look for Message Textarea and fill export proposal if empty
        const textareas = Array.from(container.querySelectorAll('textarea, input[type="text"][name*="msg"], input[type="text"][placeholder*="message"]'))
          .filter(t => t.offsetParent !== null);

        for (const ta of textareas) {
          if (!ta.value || ta.value.trim().length < 5) {
            const med = productName || 'your pharmaceutical requirement';
            fillInput(ta, `Dear Buyer, Thank you for your inquiry for ${med}. We at Bliss Pharmex (India) are certified exporters of finished pharmaceutical formulations (Tablets, Capsules, Injections). We can supply this requirement with WHO-GMP certification, COA, export packaging, and prompt shipping. Please share your required quantity and destination port.`);
          }
        }

        // D. Auto-check any "Send Quotation" / "Share Contact" checkboxes
        const checkboxes = Array.from(container.querySelectorAll('input[type="checkbox"]'))
          .filter(cb => cb.offsetParent !== null && !cb.checked);
        for (const cb of checkboxes) {
          cb.checked = true;
          cb.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // E. Auto-click Confirm / Submit / Contact Now buttons
        const actionButtons = Array.from(container.querySelectorAll('button, a, input[type="button"], input[type="submit"], .btn, [role="button"]'))
          .filter(b => {
            try {
              const rawTxt = (b.innerText || b.value || b.getAttribute('value') || b.getAttribute('title') || b.getAttribute('aria-label') || '');
              const txt = (typeof rawTxt === 'string' ? rawTxt : String(rawTxt || '')).trim().toLowerCase();

              const rawId = (b.getAttribute && b.getAttribute('id')) || (typeof b.id === 'string' ? b.id : '');
              const idLower = String(rawId || '').toLowerCase();

              const matchesText = (
                txt === 'contact buyer now' ||
                txt === 'contact now' ||
                txt === 'submit' ||
                txt === 'send' ||
                txt === 'send quotation' ||
                txt === 'send reply' ||
                txt === 'proceed' ||
                txt === 'ok' ||
                txt === 'confirm' ||
                txt === 'yes' ||
                txt.includes('buy lead') ||
                idLower.includes('submit') ||
                idLower.includes('contact')
              );

              return Boolean(matchesText && !txt.includes('cancel') && !txt.includes('close'));
            } catch (e) {
              return false;
            }
          });

        for (const btn of actionButtons) {
          triggerClick(btn);
          break;
        }
      }
    }, 250);
  }

  function targets(containers) {
    return containers.length > 0 ? containers : [];
  }

  // Scan and evaluate candidate cards
  async function scanPage(forceRescan = false) {
    clearAnyPageBlurAndUnlock();

    safeStorageGet(['autoBuyEnabled', 'boughtCount', 'dailyLimit'], async (config) => {
      const cfg = config || {};
      const isRunning = cfg.autoBuyEnabled !== false;
      const boughtCount = cfg.boughtCount || 0;
      const dailyLimit = cfg.dailyLimit || 100;

      updateHud(boughtCount, dailyLimit, isRunning);

      if (!isRunning) return;

      const candidates = findLeadCards();

      for (const item of candidates) {
        const { card, button } = item;

        if (forceRescan) {
          processedCards.delete(card);
        }

        if (processedCards.has(card)) continue;
        processedCards.add(card);

        const leadData = extractLeadData(card);
        const cleanTitle = (leadData.product_name || '').toLowerCase().trim();
        const snipedInfo = snipedLeadsMap.get(cleanTitle);

        // Slim, non-blocking inline badge above the button
        let badge = card.querySelector('.bliss-eval-badge');
        if (!badge) {
          badge = document.createElement('div');
          badge.className = 'bliss-eval-badge';
          badge.style.cssText = 'padding:3px 7px;margin:3px 0;border-radius:4px;font-size:11px;font-weight:600;display:inline-block;pointer-events:none;line-height:1.3;';
          if (button.parentElement) {
            button.parentElement.insertBefore(badge, button);
          } else {
            card.prepend(badge);
          }
        }

        if (snipedInfo) {
          badge.innerText = `⚡ SNIPED PRE-RENDER (${snipedInfo.matchedCountry})`;
          badge.style.background = '#065f46';
          badge.style.color = '#34d399';
          badge.style.border = '1px solid #059669';
          button.innerText = 'Contacted (Sniped)';
          button.style.background = '#065f46';
          button.style.borderColor = '#047857';
          button.disabled = true;
          continue;
        }

        badge.innerText = '🔍 Bliss: Checking...';
        badge.style.background = '#0f172a';
        badge.style.color = '#94a3b8';
        badge.style.border = '1px solid #334155';

        safeSendMessage({ action: 'EVALUATE_LEAD', leadData }, async (response) => {
          if (!response) {
            badge.innerText = '⚠️ Cannot connect to server';
            badge.style.background = '#450a0a';
            badge.style.color = '#f87171';
            badge.style.border = '1px solid #7f1d1d';
            lastActionMsg = 'Error: Cannot reach http://localhost:8000';
            updateHud(boughtCount, dailyLimit, isRunning);
            return;
          }

          if (response.shouldBuy) {
            const countryBadge = response.matchedCountry ? response.matchedCountry : 'Export';
            const prodBadge = response.matchedProduct ? response.matchedProduct : 'Medicine';

            if (buyingInProgressCards.has(card)) return;
            buyingInProgressCards.add(card);

            badge.innerText = `⏳ MATCHED (${countryBadge} | ${prodBadge}) — Buying via API...`;
            badge.style.background = '#064e3b';
            badge.style.color = '#34d399';
            badge.style.border = '1px solid #059669';

            lastActionMsg = `Buying via API: ${response.matchedProduct} (${response.matchedCountry})`;
            updateHud(boughtCount, dailyLimit, isRunning);

            const targetId = 'bliss_buy_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
            button.setAttribute('data-bliss-target', targetId);

            let handled = false;

            const onApiDone = (success, result) => {
              if (handled) return;
              handled = true;

              if (success) {
                console.log('[Bliss Auto-Buyer] Direct API purchase SUCCEEDED!', result);
                badge.innerText = `✅ BOUGHT VIA API (${countryBadge} | ${prodBadge})`;
                badge.style.background = '#047857';
                badge.style.color = '#ffffff';

                button.innerText = 'Contacted (Auto-Bought)';
                button.style.background = '#065f46';
                button.style.borderColor = '#047857';
                button.disabled = true;

                const enrichedData = {
                  ...leadData,
                  matchedProduct: response.matchedProduct,
                  matchedCountry: response.matchedCountry,
                  phone: result.buyer?.mobile ? (result.buyer.mobileCountry ? `+${result.buyer.mobileCountry} ${result.buyer.mobile}` : result.buyer.mobile) : (leadData.phone || null),
                  buyer_name: result.buyer?.name || null,
                  country: result.buyer?.country || response.matchedCountry || leadData.country,
                  external_id: result.pur_id ? String(result.pur_id) : (result.ofrid ? String(result.ofrid) : null)
                };

                safeSendMessage({ action: 'LEAD_BOUGHT', leadData: enrichedData }, (boughtRes) => {
                  safeStorageGet(['boughtCount', 'dailyLimit'], (cfg) => {
                    const c = cfg || {};
                    updateHud(c.boughtCount || 0, c.dailyLimit || 100, true);
                  });
                });

                lastActionMsg = `Successfully bought via API: ${response.matchedProduct}`;
                updateHud(boughtCount, dailyLimit, isRunning);
              } else {
                console.warn('[Bliss Auto-Buyer] Direct API purchase fallback to UI click:', result?.reason);
                badge.innerText = `⏳ Fallback: Clicking UI button...`;
                triggerClick(button);

                handleAutonomousPurchase(card, button, response.matchedProduct || leadData.product_name, (verifiedData) => {
                  const enrichedData = {
                    ...leadData,
                    matchedProduct: response.matchedProduct,
                    matchedCountry: response.matchedCountry,
                    phone: verifiedData.phone || leadData.phone || null
                  };

                  safeSendMessage({ action: 'LEAD_BOUGHT', leadData: enrichedData }, (boughtRes) => {
                    safeStorageGet(['boughtCount', 'dailyLimit'], (cfg) => {
                      const c = cfg || {};
                      updateHud(c.boughtCount || 0, c.dailyLimit || 100, true);
                    });
                  });

                  badge.innerText = `✅ BOUGHT (${countryBadge} | ${prodBadge})`;
                  badge.style.background = '#047857';
                  badge.style.color = '#ffffff';
                  lastActionMsg = `Successfully bought: ${response.matchedProduct}`;
                });
              }
            };

            pendingApiPurchases.set(targetId, onApiDone);

            // 1. Dispatch Direct API Buy
            window.postMessage({
              type: 'BLISS_API_BUY_LEAD',
              targetId,
              leadTitle: response.matchedProduct || leadData.product_name,
              cardIndex: candidates.indexOf(item)
            }, '*');

            // 2. Safety Timeout: If API does not respond in 3.5s, fall back to UI Click
            setTimeout(() => {
              if (!handled) {
                console.warn('[Bliss Auto-Buyer] API buy timeout (3.5s). Triggering UI click fallback.');
                onApiDone(false, { reason: 'Timeout' });
              }
            }, 3500);
          } else {
            badge.innerText = `⛔ SKIPPED — ${response.reason}`;
            badge.style.background = '#1e293b';
            badge.style.color = '#94a3b8';
            badge.style.border = '1px solid #334155';

            lastActionMsg = `Skipped: ${response.reason}`;
            updateHud(boughtCount, dailyLimit, isRunning);
          }
        });
      }
    });
  }

  // Periodic polling every 3 seconds & DOM Mutation Observer for infinite scroll
  setInterval(() => scanPage(false), 3000);

  const observer = new MutationObserver(() => {
    scanPage(false);
  });
  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
  }

  // Initial scan on load
  setTimeout(() => scanPage(false), 1500);

  // Autonomous 12 to 15 Seconds Page Auto-Refresh Engine
  setInterval(() => {
    safeStorageGet(['autoBuyEnabled', 'autoRefreshEnabled', 'boughtCount', 'dailyLimit'], (cfg) => {
      const config = cfg || {};
      const isRunning = config.autoBuyEnabled !== false;
      const isRefreshOn = config.autoRefreshEnabled !== false;

      if (!isRunning || !isRefreshOn) {
        return;
      }

      // Check if purchase is in progress or a modal is currently open
      const hasActiveModal = document.querySelector(
        '#cb_modal, #cont_buyer_popup, #contact_buyer_popup, .modal.show, div[class*="cb_"]'
      );
      if (isPurchasingActive || hasActiveModal) {
        refreshCountdown = 8; // Postpone refresh until purchase finishes
        return;
      }

      refreshCountdown--;

      updateHud(config.boughtCount || 0, config.dailyLimit || 100, isRunning);

      if (refreshCountdown <= 0) {
        console.log('[Bliss Auto-Buyer] 12-15s timer elapsed. Auto-refreshing BuyLeads page to fetch latest leads...');
        refreshCountdown = getRandomInterval();
        window.location.reload();
      }
    });
  }, 1000);

})();

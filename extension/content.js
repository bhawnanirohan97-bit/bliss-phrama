// Bliss Pharmex - IndiaMART Lead Auto-Buyer Content Script (v1.3.1)
// Clean, Non-Intrusive HUD + Verified Buying + Zero Screen Blur / Zero Opacity Dimming

(function () {
  'use strict';

  console.log('%c[Bliss Auto-Buyer v1.3.1] Running smoothly on IndiaMART (Non-Intrusive Mode).', 'color: #10b981; font-weight: bold;');

  const processedCards = new WeakSet();
  const buyingInProgressCards = new WeakSet();
  let statusBanner = null;
  let isHudMinimized = false;
  let detectedCardsCount = 0;
  let lastActionMsg = 'Scanning for leads...';

  // Keep page interactive and ensure no background blur or scroll-locking stays on screen
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

      // If an orphaned overlay is blocking user interaction without an open modal, unlock it
      const orphanedOverlays = document.querySelectorAll('.gl_overlay, .modal-backdrop:not(.show), div[class*="backdrop"]:not(.show)');
      for (const ov of orphanedOverlays) {
        ov.style.display = 'none';
        ov.style.pointerEvents = 'none';
      }
    } catch (e) {}
  }

  // Reliable multi-event click trigger
  function triggerClick(el) {
    if (!el) return;
    try { el.focus(); } catch (e) {}

    const interactive = el.closest('button, a, input[type="button"], input[type="submit"], [role="button"]') || el;
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

  // Non-intrusive floating HUD in bottom-right corner with minimize/expand support
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
        max-width: 320px;
        pointer-events: auto;
      `;
      document.body.appendChild(statusBanner);
    }

    const dotColor = isRunning ? '#10b981' : '#f59e0b';
    const statusText = isRunning ? 'Auto-Buy ON' : 'Paused';

    if (isHudMinimized) {
      statusBanner.innerHTML = `
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; cursor:pointer;" id="hud-expand-btn">
          <div style="display:flex; align-items:center; gap:6px;">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${dotColor};box-shadow:0 0 8px ${dotColor};"></span>
            <span style="font-weight:600;color:#fff;font-size:12px;">Bliss AI: ${boughtCount}/${limit}</span>
          </div>
          <span style="color:#94a3b8;font-size:11px;background:#1e293b;padding:1px 6px;border-radius:3px;">+ Expand</span>
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
        <span id="hud-rescan-btn" style="color:#38bdf8;text-decoration:underline;cursor:pointer;">Re-scan</span>
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
        scanPage(true);
      };
    }
  }

  // Traverses up to reliably locate the entire card container
  function findCardElement(btn) {
    const directMatch = btn.closest(
      '.bl_card, .bld_card, .blp_card, .glp_card, .listing-card, .listing-item, [id*="bl_"], [id*="lead"], [class*="bld_"], [class*="lead"], li.card, li'
    );
    if (directMatch && directMatch !== document.body && directMatch !== document.documentElement) {
      return directMatch;
    }

    let current = btn.parentElement;
    let fallback = null;
    while (current && current !== document.body && current !== document.documentElement) {
      const hasTitle = current.querySelector('h1, h2, h3, h4, [class*="pname"], [class*="title"], [class*="prod"], [class*="desc"]');
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
      if (el.offsetParent === null && window.getComputedStyle(el).display === 'none') return false;

      const text = (el.innerText || el.value || el.getAttribute('title') || el.getAttribute('aria-label') || '').trim().toLowerCase();
      if (text.length > 70 || text.length < 2) return false;

      if (text.includes('view contact') || text.includes('buyer details') || text.includes('already contacted') || text.includes('contacted')) {
        return false;
      }

      return (
        text.includes('contact buyer') ||
        text.includes('contact now') ||
        text.includes('send quotation') ||
        text.includes('buy lead') ||
        text.includes('connect now') ||
        text.includes('reply now') ||
        text.includes('send reply') ||
        text === 'contact' ||
        text === 'reply'
      );
    });

    const results = [];
    candidateButtons.forEach(btn => {
      const card = findCardElement(btn);
      if (card) {
        results.push({ card, button: btn });
      }
    });

    detectedCardsCount = results.length;
    return results;
  }

  // Extract structured lead information from card DOM
  function extractLeadData(card) {
    const fullText = (card.innerText || '').trim();

    // 1. Product Name extraction
    const titleEl = card.querySelector(
      '.bld_pname, .prod_name, .prod-name, [class*="pname"], [class*="title"], h2, h3, h4, .lead-title, a[href*="blp"], a[href*="buylead"], strong'
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
      '.cntry_name, .country, [class*="country"], [class*="cntry"], .flag, img[alt*="flag"], img[src*="flag"], [class*="flag"]'
    );
    const locationEl = card.querySelector(
      '.bld_loc, .loc_txt, .location, .city, .buyer-loc, [class*="loc"], [class*="city"]'
    );

    let countryText = countryEl ? (countryEl.innerText || countryEl.getAttribute('title') || countryEl.getAttribute('alt') || '').trim() : '';
    let locationText = locationEl ? locationEl.innerText.trim() : '';

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
      location_text: (locationText ? locationText + ' ' + countryText : fullText.slice(0, 300)),
      inquiry_text: fullText.slice(0, 1500),
      quantity: quantity
    };
  }

  // Fully Autonomous Modal Confirmation & Auto-Send Message Handler
  function handleAutonomousPurchase(card, button, productName, onVerified) {
    let attempts = 0;
    const maxAttempts = 32; // 32 x 250ms = 8 seconds
    let confirmedSuccess = false;

    console.log('[Bliss Auto-Buyer] Monitoring DOM for modal confirmation and purchase completion...');

    const timer = setInterval(() => {
      attempts++;
      if (attempts > maxAttempts || confirmedSuccess) {
        clearInterval(timer);
        if (!confirmedSuccess) {
          // If modal is still open after 8s and unconfirmed, close it so the page is never locked/blurred
          const openModals = document.querySelectorAll('.modal, .popup, .dialog, div[class*="popup"], div[class*="modal"]');
          for (const m of openModals) {
            const closeBtn = m.querySelector('button.close, .close, [class*="close"], [title*="Close"]');
            if (closeBtn) triggerClick(closeBtn);
          }
          clearAnyPageBlurAndUnlock();
        }
        return;
      }

      // Check 1: Did the card text or button change to show buyer contact info or "Quotation Sent"?
      const cardText = (card.innerText || '').trim();
      const phoneMatch = cardText.match(/(?:\+?91[\s-]?)?[6-9]\d{9}|\+?[1-9]\d{1,3}[\s-]?\(?\d{2,4}\)?[\s-]?\d{3,5}[\s-]?\d{3,5}/);
      const hasContactRevealed = cardText.toLowerCase().includes('buyer details') ||
                                cardText.toLowerCase().includes('contact details') ||
                                cardText.toLowerCase().includes('quotation sent') ||
                                cardText.toLowerCase().includes('already contacted') ||
                                (phoneMatch && phoneMatch[0].length >= 8);

      if (hasContactRevealed && !confirmedSuccess) {
        console.log('[Bliss Auto-Buyer] Verified: Lead purchase confirmed on card DOM! Phone:', phoneMatch ? phoneMatch[0] : 'Revealed');
        confirmedSuccess = true;
        clearInterval(timer);
        clearAnyPageBlurAndUnlock();
        onVerified({ phone: phoneMatch ? phoneMatch[0].trim() : null });
        return;
      }

      // Check 2: Inspect active modals/popups
      const containers = Array.from(document.querySelectorAll(
        '.modal, .popup, .dialog, .ui-dialog, div[class*="popup"], div[class*="modal"], div[id*="popup"], div[id*="modal"], div[class*="dialog"], div[id*="contact"], div[class*="contact"], [id*="cb_"], [class*="cb_"], .bld_modal'
      )).filter(el => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
      });

      for (const container of containers) {
        const modalText = (container.innerText || '').toLowerCase();

        // Check if modal displays success confirmation
        if (modalText.includes('quotation sent') || modalText.includes('successfully contacted') || modalText.includes('contact details') || modalText.includes('lead consumed')) {
          console.log('[Bliss Auto-Buyer] Verified: Success message detected inside modal dialog!');
          confirmedSuccess = true;
          clearInterval(timer);

          setTimeout(() => {
            const closeBtn = container.querySelector('button.close, .close, [class*="close"], [title*="Close"]');
            if (closeBtn) triggerClick(closeBtn);
            clearAnyPageBlurAndUnlock();
          }, 800);

          onVerified({});
          return;
        }

        // A. Auto-select product radio button if present
        const radios = Array.from(container.querySelectorAll('input[type="radio"]'));
        if (radios.length > 0 && !radios.some(r => r.checked)) {
          const targetRadio = radios[0];
          targetRadio.checked = true;
          targetRadio.dispatchEvent(new Event('change', { bubbles: true }));
          triggerClick(targetRadio);
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
        const actionButtons = Array.from(container.querySelectorAll('button, a, input[type="button"], input[type="submit"], .btn'))
          .filter(b => {
            const txt = (b.innerText || b.value || '').trim().toLowerCase();
            return (
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
              b.id.includes('submit') ||
              b.id.includes('contact')
            ) && !txt.includes('cancel');
          });

        for (const btn of actionButtons) {
          triggerClick(btn);
          break;
        }
      }
    }, 250);
  }

  // Scan and evaluate candidate cards
  async function scanPage(forceRescan = false) {
    clearAnyPageBlurAndUnlock();

    chrome.storage.local.get(['autoBuyEnabled', 'boughtCount', 'dailyLimit'], async (config) => {
      const isRunning = config.autoBuyEnabled !== false;
      const boughtCount = config.boughtCount || 0;
      const dailyLimit = config.dailyLimit || 100;

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

        badge.innerText = '🔍 Bliss: Checking...';
        badge.style.background = '#0f172a';
        badge.style.color = '#94a3b8';
        badge.style.border = '1px solid #334155';

        chrome.runtime.sendMessage({ action: 'EVALUATE_LEAD', leadData }, async (response) => {
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

            badge.innerText = `⏳ MATCHED (${countryBadge} | ${prodBadge}) — Buying...`;
            badge.style.background = '#064e3b';
            badge.style.color = '#34d399';
            badge.style.border = '1px solid #059669';

            lastActionMsg = `Buying: ${response.matchedProduct} (${response.matchedCountry})`;
            updateHud(boughtCount, dailyLimit, isRunning);

            console.log('[Bliss Auto-Buyer] Matched lead approved by AI. Clicking contact button:', button);
            triggerClick(button);

            handleAutonomousPurchase(card, button, response.matchedProduct || leadData.product_name, (verifiedData) => {
              const enrichedData = {
                ...leadData,
                matchedProduct: response.matchedProduct,
                matchedCountry: response.matchedCountry,
                phone: verifiedData.phone || leadData.phone || null
              };

              chrome.runtime.sendMessage({ action: 'LEAD_BOUGHT', leadData: enrichedData }, (boughtRes) => {
                chrome.storage.local.get(['boughtCount', 'dailyLimit'], (cfg) => {
                  updateHud(cfg.boughtCount, cfg.dailyLimit, true);
                });
              });

              badge.innerText = `✅ BOUGHT (${countryBadge} | ${prodBadge})`;
              badge.style.background = '#047857';
              badge.style.color = '#ffffff';
              lastActionMsg = `Successfully bought: ${response.matchedProduct}`;
            });

            setTimeout(() => {
              if (badge.innerText.includes('Buying...')) {
                badge.innerText = `⚠️ MATCHED (${countryBadge}) — Needs click`;
                badge.style.background = '#78350f';
                badge.style.color = '#fde68a';
                badge.style.border = '1px solid #d97706';
                lastActionMsg = `Awaiting confirmation for: ${response.matchedProduct}`;
                clearAnyPageBlurAndUnlock();
              }
            }, 8500);

          } else {
            // NOTE: We NEVER change card.style.opacity! Website stays 100% interactive and clear.
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
})();

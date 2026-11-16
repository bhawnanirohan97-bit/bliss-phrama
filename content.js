// Bliss Pharmex - IndiaMART Lead Auto-Buyer Content Script (v1.2.0)
// Autonomous Buying for Target Export Countries & Target Medicines Only

(function () {
  'use strict';

  console.log('[Bliss Auto-Buyer v1.2.1] Initialized on IndiaMART.');

  const processedCards = new WeakSet();
  let statusBanner = null;
  let detectedCardsCount = 0;
  let lastActionMsg = 'Scanning for leads...';

  function triggerClick(el) {
    if (!el) return;
    try { el.focus(); } catch (e) {}
    try { el.click(); } catch (e) {}
    try {
      el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
      el.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
      el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    } catch (e) {}
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

  // Floating On-Screen HUD in bottom-right corner with live diagnostics
  function updateHud(boughtCount = 0, limit = 100, isRunning = true) {
    if (!statusBanner) {
      statusBanner = document.createElement('div');
      statusBanner.id = 'bliss-autobuy-hud';
      statusBanner.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #090d16;
        color: #f1f5f9;
        padding: 12px 18px;
        border-radius: 10px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 13px;
        font-weight: 500;
        z-index: 99999999;
        box-shadow: 0 8px 30px rgba(0,0,0,0.5);
        border: 1px solid #1e293b;
        display: flex;
        flex-direction: column;
        gap: 6px;
        cursor: pointer;
        user-select: none;
        max-width: 360px;
      `;
      document.body.appendChild(statusBanner);

      statusBanner.addEventListener('click', () => {
        scanPage(true);
      });
    }

    const dotColor = isRunning ? '#10b981' : '#f59e0b';
    const statusText = isRunning ? 'Active (Auto-Buy ON)' : 'Paused';

    statusBanner.innerHTML = `
      <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${dotColor};box-shadow:0 0 10px ${dotColor};"></span>
          <span style="font-weight:600;color:#fff;">Bliss AI: ${statusText}</span>
        </div>
        <span style="background:#1e293b;padding:2px 8px;border-radius:4px;font-size:11px;color:#94a3b8;">Bought: ${boughtCount}/${limit}</span>
      </div>
      <div style="font-size:11px;color:#94a3b8;display:flex;justify-content:space-between;gap:8px;border-top:1px solid #1e293b;padding-top:4px;">
        <span>Leads on Screen: <strong style="color:#e2e8f0;">${detectedCardsCount}</strong></span>
        <span style="color:#38bdf8;text-decoration:underline;">Click to Re-scan</span>
      </div>
      <div style="font-size:11px;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${lastActionMsg}">
        Status: ${lastActionMsg}
      </div>
    `;
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
    // 1. Broad selector to catch buttons whether they are <button>, <a>, <div>, or <span>
    const allClickables = Array.from(document.querySelectorAll(
      'button, a, input[type="button"], input[type="submit"], [role="button"], div[class*="btn"], span[class*="btn"], div[id*="btn"], [class*="contact"], [id*="contact"], [class*="buy"], [id*="buy"], [class*="quotation"], [class*="reply"]'
    ));

    const candidateButtons = allClickables.filter(el => {
      // Must be rendered and visible
      if (el.offsetParent === null && window.getComputedStyle(el).display === 'none') return false;

      const text = (el.innerText || el.value || el.getAttribute('title') || el.getAttribute('aria-label') || '').trim().toLowerCase();
      if (text.length > 70 || text.length < 2) return false;

      return (
        text.includes('contact buyer') ||
        text.includes('contact now') ||
        text.includes('view contact') ||
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
  function autoConfirmAndSendMessage(productName) {
    let attempts = 0;
    const maxAttempts = 35; // 35 x 250ms = 8.75 seconds
    let confirmedOk = false;
    let messageSent = false;

    console.log('[Bliss Auto-Buyer] Monitoring modal popups to auto-confirm & auto-send message...');

    const timer = setInterval(() => {
      attempts++;
      if (attempts > maxAttempts) {
        clearInterval(timer);
        return;
      }

      // Look for active modals or overlays
      const containers = Array.from(document.querySelectorAll(
        '.modal, .popup, .dialog, .ui-dialog, div[class*="popup"], div[class*="modal"], div[id*="popup"], div[id*="modal"], div[class*="dialog"], div[id*="contact"], div[class*="contact"]'
      )).filter(el => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
      });

      const targets = containers.length > 0 ? containers : [document.body];

      for (const container of targets) {
        // A. Auto-click "OK", "Confirm", "Yes", "Proceed", "Contact Now", "Buy Lead"
        if (!confirmedOk) {
          const okButtons = Array.from(container.querySelectorAll('button, a, input[type="button"], input[type="submit"], .btn'))
            .filter(b => {
              const txt = (b.innerText || b.value || '').trim().toLowerCase();
              return txt === 'ok' || txt === 'confirm' || txt === 'yes' || txt === 'proceed' || txt === 'contact now' || txt.includes('buy lead') || txt === 'agree' || b.id.includes('ok') || b.className.includes('confirm');
            });

          for (const btn of okButtons) {
            console.log('[Bliss Auto-Buyer] Auto-clicking Confirmation/OK:', btn.innerText || btn.value);
            triggerClick(btn);
            confirmedOk = true;
            break;
          }
        }

        // B. Look for Message Textarea and fill export proposal if empty
        const textareas = Array.from(container.querySelectorAll('textarea, input[type="text"][name*="msg"], input[type="text"][placeholder*="message"]'))
          .filter(t => t.offsetParent !== null);

        for (const ta of textareas) {
          if (!ta.value || ta.value.trim().length < 5) {
            const med = productName || 'your pharmaceutical requirement';
            fillInput(ta, `Dear Buyer, Thank you for your inquiry for ${med}. We at Bliss Pharmex (India) are certified exporters of finished pharmaceutical formulations (Tablets, Capsules, Injections). We can supply this requirement with WHO-GMP certification, COA, export packaging, and prompt shipping. Please share your required quantity and destination port.`);
          }
        }

        // C. Auto-check any "Send Quotation" / "Share Contact" checkboxes
        const checkboxes = Array.from(container.querySelectorAll('input[type="checkbox"]'))
          .filter(cb => cb.offsetParent !== null && !cb.checked);
        for (const cb of checkboxes) {
          cb.checked = true;
          cb.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // D. Auto-click "Send", "Send Message", "Send Reply", "Send Quotation", "Submit"
        if (!messageSent) {
          const sendButtons = Array.from(container.querySelectorAll('button, a, input[type="button"], input[type="submit"], .btn'))
            .filter(b => {
              const txt = (b.innerText || b.value || '').trim().toLowerCase();
              return (txt === 'send' || txt === 'send message' || txt === 'send reply' || txt === 'submit' || txt === 'send quotation' || txt.includes('send')) && !txt.includes('cancel');
            });

          for (const btn of sendButtons) {
            console.log('[Bliss Auto-Buyer] Auto-clicking Send Message:', btn.innerText || btn.value);
            triggerClick(btn);
            messageSent = true;
            break;
          }
        }

        // E. Auto-close success modal after sending
        if (messageSent) {
          const closeButtons = Array.from(container.querySelectorAll('button, a, .close, [class*="close"]'))
            .filter(b => {
              const txt = (b.innerText || b.title || '').trim().toLowerCase();
              return txt === 'close' || txt === 'got it' || txt === 'done' || txt === '×' || txt === 'x' || b.classList.contains('close');
            });

          for (const btn of closeButtons) {
            setTimeout(() => {
              triggerClick(btn);
              clearInterval(timer);
            }, 600);
            return;
          }
        }
      }
    }, 250);
  }

  // Scan and evaluate candidate cards
  async function scanPage(forceRescan = false) {
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

        // Put badge directly right next to or above the button so user can clearly see it!
        let badge = card.querySelector('.bliss-eval-badge');
        if (!badge) {
          badge = document.createElement('div');
          badge.className = 'bliss-eval-badge';
          badge.style.cssText = 'padding:6px 10px;margin:6px 0;border-radius:6px;font-size:12px;font-weight:600;display:block;line-height:1.4;';
          if (button.parentElement) {
            button.parentElement.insertBefore(badge, button);
          } else {
            card.prepend(badge);
          }
        }

        badge.innerText = '🔍 Bliss AI: Evaluating lead criteria...';
        badge.style.background = '#0f172a';
        badge.style.color = '#94a3b8';
        badge.style.border = '1px solid #334155';

        chrome.runtime.sendMessage({ action: 'EVALUATE_LEAD', leadData }, async (response) => {
          if (!response) {
            badge.innerText = '⚠️ Bliss AI: Cannot connect to http://localhost:8000';
            badge.style.background = '#450a0a';
            badge.style.color = '#f87171';
            badge.style.border = '1px solid #7f1d1d';
            lastActionMsg = 'Error: Cannot reach http://localhost:8000';
            updateHud(boughtCount, dailyLimit, isRunning);
            return;
          }

          if (response.shouldBuy) {
            const countryBadge = response.matchedCountry ? `Country: ${response.matchedCountry}` : 'Target Export';
            const prodBadge = response.matchedProduct ? `Product: ${response.matchedProduct}` : 'Target Medicine';
            badge.innerText = `✅ Bliss AI: MATCHED (${countryBadge} | ${prodBadge}) — AUTO-BUYING NOW!`;
            badge.style.background = '#064e3b';
            badge.style.color = '#34d399';
            badge.style.border = '1px solid #059669';
            card.style.border = '2px solid #10b981';
            card.style.boxShadow = '0 0 16px rgba(16,185,129,0.3)';

            lastActionMsg = `Buying lead: ${response.matchedProduct} (${response.matchedCountry})`;
            updateHud(boughtCount, dailyLimit, isRunning);

            // 1. Trigger purchase click
            console.log('[Bliss Auto-Buyer] Purchasing lead:', leadData);
            triggerClick(button);

            // 2. Start fully automated OK confirmation and Send Message handler
            autoConfirmAndSendMessage(response.matchedProduct || leadData.product_name);

            // 3. Notify background worker immediately to record lead in dashboard
            chrome.runtime.sendMessage({ action: 'LEAD_BOUGHT', leadData: { ...leadData, matchedProduct: response.matchedProduct, matchedCountry: response.matchedCountry } }, (boughtRes) => {
              chrome.storage.local.get(['boughtCount', 'dailyLimit'], (cfg) => {
                updateHud(cfg.boughtCount, cfg.dailyLimit, true);
              });
            });

            // 4. Capture any revealed phone/buyer name after 2.5 seconds
            setTimeout(() => {
              const updatedText = card.innerText || '';
              const phoneMatch = updatedText.match(/(?:\+?\d{1,4}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{3,5}[-.\s]?\d{3,5}/);
              const nameEl = card.querySelector('.buyer-name, .sender-name, [class*="name"]');
              const enrichedData = {
                ...leadData,
                phone: phoneMatch ? phoneMatch[0].trim() : null,
                buyer_name: nameEl ? nameEl.innerText.trim() : null,
              };
              chrome.runtime.sendMessage({ action: 'LEAD_BOUGHT', leadData: enrichedData });
              badge.innerText = `✅ Bliss AI: BOUGHT! (${countryBadge} | ${prodBadge}) — PROPOSAL SENT!`;
            }, 2500);
          } else {
            badge.innerText = `⛔ Bliss AI: SKIPPED — ${response.reason}`;
            badge.style.background = '#1e293b';
            badge.style.color = '#94a3b8';
            badge.style.border = '1px solid #334155';
            card.style.opacity = '0.65';

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

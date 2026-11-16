// Bliss Pharmex - IndiaMART Lead Auto-Buyer Content Script (v1.1.0)
// Autonomous Buying for Target Export Countries & Target Medicines Only

(function () {
  'use strict';

  console.log('[Bliss Auto-Buyer] Content script loaded on IndiaMART with strict country & target product evaluation.');

  // 1. Auto-approve native browser confirm and alert dialogs (e.g. "Buy this lead for 1 credit?")
  try {
    const script = document.createElement('script');
    script.textContent = `
      window.confirm = function() { console.log('[Bliss AI] Auto-approved native confirm dialog'); return true; };
      window.alert = function() { console.log('[Bliss AI] Auto-closed native alert'); return true; };
    `;
    (document.head || document.documentElement).appendChild(script);
    script.remove();
  } catch (e) {}

  const processedCards = new WeakSet();
  let statusBanner = null;

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

  // Create or update on-screen HUD badge in bottom right corner
  function updateHud(boughtCount = 0, limit = 100, isRunning = true, serverStatus = 'Connected') {
    if (!statusBanner) {
      statusBanner = document.createElement('div');
      statusBanner.id = 'bliss-autobuy-hud';
      statusBanner.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #0f172a;
        color: #f1f5f9;
        padding: 10px 16px;
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 13px;
        font-weight: 500;
        z-index: 9999999;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        border: 1px solid #334155;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        user-select: none;
      `;
      document.body.appendChild(statusBanner);

      statusBanner.addEventListener('click', () => {
        scanPage(true); // force re-scan on click
      });
    }

    const dotColor = isRunning ? '#10b981' : '#f59e0b';
    const statusText = isRunning ? 'Auto-Buyer Active' : 'Paused';
    statusBanner.innerHTML = `
      <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${dotColor};box-shadow:0 0 10px ${dotColor};"></span>
      <span><strong>Bliss AI:</strong> ${statusText}</span>
      <span style="background:#1e293b;padding:3px 8px;border-radius:4px;font-size:11px;color:#94a3b8;">Bought: ${boughtCount}/${limit}</span>
      <span style="font-size:11px;color:#38bdf8;text-decoration:underline;">Click to Re-scan</span>
    `;
  }

  // Helper to reliably find the entire card container
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
      const hasTitle = current.querySelector('h1, h2, h3, h4, [class*="pname"], [class*="title"], [class*="prod"]');
      const text = (current.innerText || '').trim();
      if (hasTitle && text.length > 50) {
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
    const candidateButtons = Array.from(document.querySelectorAll(
      'button, a, input[type="button"], input[type="submit"], [role="button"], div[class*="btn"], span[class*="btn"], div[id*="btn"], [class*="contact"], [id*="contact"], [class*="buy"], [id*="buy"]'
    )).filter(el => {
      if (el.offsetParent === null && window.getComputedStyle(el).display === 'none') return false;
      const text = (el.innerText || el.value || el.getAttribute('title') || '').trim().toLowerCase();
      if (text.length > 60 || text.length < 2) return false;
      return (
        text.includes('contact buyer') ||
        text.includes('contact now') ||
        text.includes('view contact') ||
        text.includes('send quotation') ||
        text.includes('buy lead') ||
        text.includes('connect now') ||
        text.includes('reply now') ||
        text === 'contact' ||
        text === 'reply' ||
        text.includes('send reply')
      );
    });

    const results = [];
    candidateButtons.forEach(btn => {
      const card = findCardElement(btn);
      if (card && !processedCards.has(card)) {
        results.push({ card, button: btn });
      }
    });

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
      inquiry_text: fullText.slice(0, 1200),
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

      // 1. Look for active modals or overlays
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

        // Visual pending evaluation badge
        let badge = card.querySelector('.bliss-eval-badge');
        if (!badge) {
          badge = document.createElement('div');
          badge.className = 'bliss-eval-badge';
          badge.style.cssText = 'padding:6px 10px;margin:8px 0;border-radius:6px;font-size:12px;font-weight:600;display:block;line-height:1.4;';
          card.prepend(badge);
        }

        badge.innerText = '🔍 Bliss AI: Evaluating against your target country & medicines...';
        badge.style.background = '#0f172a';
        badge.style.color = '#94a3b8';
        badge.style.border = '1px solid #334155';

        chrome.runtime.sendMessage({ action: 'EVALUATE_LEAD', leadData }, async (response) => {
          if (!response) {
            badge.innerText = '⚠️ Bliss AI: No response from local server (Make sure http://localhost:8000 is running)';
            badge.style.background = '#450a0a';
            badge.style.color = '#f87171';
            badge.style.border = '1px solid #7f1d1d';
            return;
          }

          if (response.shouldBuy) {
            const countryBadge = response.matchedCountry ? `Country: ${response.matchedCountry}` : 'Target Export';
            const prodBadge = response.matchedProduct ? `Product: ${response.matchedProduct}` : 'Target Medicine';
            badge.innerText = `✅ Bliss AI: MATCHED (${countryBadge} | ${prodBadge}) — AUTO-BUYING & SENDING PROPOSAL!`;
            badge.style.background = '#064e3b';
            badge.style.color = '#34d399';
            badge.style.border = '1px solid #059669';
            card.style.border = '2px solid #10b981';
            card.style.boxShadow = '0 0 16px rgba(16,185,129,0.3)';

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

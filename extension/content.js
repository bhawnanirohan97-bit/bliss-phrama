// Bliss Pharmex - IndiaMART Lead Auto-Buyer Content Script

(function () {
  'use strict';

  console.log('[Bliss Auto-Buyer] Content script loaded on IndiaMART with full Auto-Confirm & Auto-Send Message.');

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

  // Create or update on-screen HUD badge
  function updateHud(boughtCount = 0, limit = 10, isRunning = true) {
    if (!statusBanner) {
      statusBanner = document.createElement('div');
      statusBanner.id = 'bliss-autobuy-hud';
      statusBanner.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #111c2e;
        color: #e6edf7;
        padding: 10px 16px;
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 13px;
        font-weight: 500;
        z-index: 999999;
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        border: 1px solid #1e2c42;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
      `;
      document.body.appendChild(statusBanner);
    }

    const dotColor = isRunning ? '#34d399' : '#fbbf24';
    const statusText = isRunning ? 'Active (Auto-Buy & Auto-Send)' : 'Paused';
    statusBanner.innerHTML = `
      <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${dotColor};box-shadow:0 0 8px ${dotColor};"></span>
      <span><strong>Bliss AI Auto-Buyer:</strong> ${statusText}</span>
      <span style="background:#1e2c42;padding:2px 8px;border-radius:4px;font-size:11px;color:#93a5bf;">Bought: ${boughtCount}/${limit}</span>
    `;
  }

  // Find candidate BuyLead cards
  function findLeadCards() {
    const buttons = Array.from(document.querySelectorAll('button, a, input[type="button"]')).filter(el => {
      const text = (el.innerText || el.value || '').trim().toLowerCase();
      return text.includes('contact buyer') || text.includes('view contact') || text.includes('send quotation');
    });

    const cards = [];
    buttons.forEach(btn => {
      let card = btn.closest('.card, .blp_card, .glp_card, .listing-card, li, div[id*="lead"], div[class*="lead"]') || btn.parentElement.parentElement;
      if (card && !processedCards.has(card)) {
        cards.push({ card, button: btn });
      }
    });

    return cards;
  }

  // Extract structured lead information from card DOM
  function extractLeadData(card) {
    const text = card.innerText || '';

    const titleEl = card.querySelector('h2, h3, h4, .lead-title, .prod_name, strong, a[href*="blp"]');
    const product = titleEl ? titleEl.innerText.trim() : '';

    let quantity = null;
    const qtyMatch = text.match(/(?:quantity|qty|requirement)[:\s]*([0-9,.]+)\s*([a-zA-Z]+)?/i);
    if (qtyMatch) {
      quantity = qtyMatch[1].replace(/,/g, '');
    }

    const locationEl = card.querySelector('.location, .city, .country, .buyer-loc, [class*="loc"]');
    const locationText = locationEl ? locationEl.innerText.trim() : text;

    return {
      product_name: product,
      inquiry_text: text.slice(0, 500),
      quantity: quantity,
      location_text: locationText.slice(0, 150)
    };
  }

  // Fully Autonomous Modal Confirmation & Auto-Send Message Handler
  function autoConfirmAndSendMessage(productName) {
    let attempts = 0;
    const maxAttempts = 30; // 30 x 250ms = 7.5 seconds
    let confirmedOk = false;
    let messageSent = false;

    console.log('[Bliss Auto-Buyer] Monitoring popups to auto-confirm & auto-send message...');

    const timer = setInterval(() => {
      attempts++;
      if (attempts > maxAttempts) {
        clearInterval(timer);
        return;
      }

      // 1. Look for active modals or overlays
      const containers = Array.from(document.querySelectorAll('.modal, .popup, .dialog, .ui-dialog, div[class*="popup"], div[class*="modal"], div[id*="popup"], div[id*="modal"], div[class*="dialog"], div[id*="contact"], div[class*="contact"]'))
        .filter(el => {
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
            btn.click();
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
            ta.value = `Dear Buyer, Thank you for your inquiry for ${med}. We at Bliss Pharmex (India) are certified exporters of finished pharmaceutical formulations (Tablets, Capsules, Injections). We can supply this requirement with WHO-GMP certification, COA, export packaging, and prompt shipping. Please share your required quantity and destination port.`;
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            ta.dispatchEvent(new Event('change', { bubbles: true }));
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
            btn.click();
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
              btn.click();
              clearInterval(timer);
            }, 600);
            return;
          }
        }
      }
    }, 250);
  }

  // Scan and evaluate new cards
  async function scanPage() {
    chrome.storage.local.get(['autoBuyEnabled', 'boughtCount', 'dailyLimit'], async (config) => {
      updateHud(config.boughtCount || 0, config.dailyLimit || 10, config.autoBuyEnabled !== false);

      if (config.autoBuyEnabled === false) return;

      const candidates = findLeadCards();

      for (const item of candidates) {
        const { card, button } = item;
        processedCards.add(card);

        const leadData = extractLeadData(card);

        // Visual pending badge
        const badge = document.createElement('div');
        badge.className = 'bliss-eval-badge';
        badge.style.cssText = 'padding:4px 8px;margin:6px 0;border-radius:4px;font-size:11px;font-weight:600;display:inline-block;';
        badge.innerText = '🔍 Bliss AI: Evaluating...';
        badge.style.background = '#1e2c42';
        badge.style.color = '#93a5bf';
        card.prepend(badge);

        chrome.runtime.sendMessage({ action: 'EVALUATE_LEAD', leadData }, async (response) => {
          if (!response) {
            badge.innerText = '⚠️ Bliss AI: No response from local server';
            badge.style.background = '#7f1d1d';
            badge.style.color = '#fca5a5';
            return;
          }

          if (response.shouldBuy) {
            badge.innerText = '✅ Bliss AI: MATCHED TARGET EXPORT — BUYING & AUTO-SENDING!';
            badge.style.background = '#065f46';
            badge.style.color = '#34d399';
            card.style.border = '2px solid #34d399';
            card.style.boxShadow = '0 0 12px rgba(52,211,153,0.3)';

            // 1. Trigger purchase click
            console.log('[Bliss Auto-Buyer] Purchasing lead:', leadData);
            button.click();

            // 2. Start fully automated OK confirmation and Send Message handler
            autoConfirmAndSendMessage(leadData.product_name || leadData.product);

            // 3. Notify background worker immediately to record lead in dashboard
            chrome.runtime.sendMessage({ action: 'LEAD_BOUGHT', leadData }, (boughtRes) => {
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
              badge.innerText = '✅ Bliss AI: BOUGHT & MESSAGE SENT AUTOMATICALLY!';
            }, 2500);
          } else {
            badge.innerText = `⛔ Bliss AI: ${response.reason}`;
            badge.style.background = '#27272a';
            badge.style.color = '#a1a1aa';
            card.style.opacity = '0.65';
          }
        });
      }
    });
  }

  // Periodic polling & DOM Mutation Observer
  setInterval(scanPage, 4000);

  const observer = new MutationObserver(() => {
    scanPage();
  });

  observer.observe(document.body, { childList: true, subtree: true });

  // Initial scan
  scanPage();
})();

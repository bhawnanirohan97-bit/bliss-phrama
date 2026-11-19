// Bliss Pharmex - Main World Injector (Runs at document_start in IndiaMART window context)
// Pre-Render Zero-Latency Sniper v3.0: Intercepts leads before DOM/React render & snipes immediately
(function () {
  'use strict';

  console.log('%c[Bliss Main World] Pre-Render Zero-Latency Sniper v3.0 Active.', 'color: #10b981; font-weight: bold;');

  const alreadyBoughtOffers = new Set();

  try {
    // 1. Auto-approve native browser confirm dialogs (e.g., "Consume 1 BuyLead?")
    window.confirm = function (msg) {
      console.log('[Bliss Main World] Auto-approved native confirm dialog:', msg);
      return true;
    };

    // 2. Auto-close native alert dialogs
    window.alert = function (msg) {
      console.log('[Bliss Main World] Auto-closed native alert dialog:', msg);
      return true;
    };
  } catch (err) {
    console.warn('[Bliss Main World] Dialog handler warning:', err);
  }

  // Retrieve cached rules from localStorage for 0ms in-memory evaluation
  function getCachedRules() {
    try {
      const stored = localStorage.getItem('bliss_ai_rules');
      if (stored) {
        return JSON.parse(stored);
      }
    } catch (e) {}
    // Fallback default rules
    return {
      enabled: true,
      reject_domestic: true,
      countries: ['US', 'GB', 'AU', 'PH', 'KR', 'JP', 'CN'],
      products: []
    };
  }

  // Extract glusrId
  function getGlusrId() {
    return document.getElementById('glusr_id')?.value ||
           window._glusr_id ||
           window.glusr_id ||
           '249901953';
  }

  // Evaluate lead in 0.1ms synchronously in-memory
  function evaluateLeadInMemory(lead, rules) {
    if (!lead || !rules || rules.enabled === false) {
      return { eligible: false, reason: 'AI disabled or invalid lead data' };
    }

    // A. Domestic Indian Lead Rejection
    const countryIso = (lead.FK_GL_COUNTRY_ISO || '').trim().toUpperCase();
    const countryName = (lead.GLUSR_COUNTRY || '').trim().toLowerCase();
    if (rules.reject_domestic !== false) {
      if (countryIso === 'IN' || countryName === 'india') {
        return { eligible: false, reason: 'Domestic Indian lead rejected' };
      }
    }

    // B. Target Export Countries Whitelist
    if (Array.isArray(rules.countries) && rules.countries.length > 0) {
      const allowed = rules.countries.map(c => String(c).trim().toUpperCase());
      if (!countryIso || !allowed.includes(countryIso)) {
        return { eligible: false, reason: `Country '${countryIso || countryName}' is not in allowed target export countries` };
      }
    }

    // C. Approved 499 Medicine Products Check
    const title = (lead.ETO_OFR_TITLE || '').trim().toLowerCase();
    const desc = (lead.ETO_OFR_DESC || '').trim().toLowerCase();
    let matchedProduct = null;

    if (Array.isArray(rules.products) && rules.products.length > 0) {
      for (const prod of rules.products) {
        const p = String(prod).trim().toLowerCase();
        if (p.length >= 2 && (title.includes(p) || desc.includes(p))) {
          matchedProduct = prod;
          break;
        }
      }
      if (!matchedProduct) {
        return { eligible: false, reason: `Product '${lead.ETO_OFR_TITLE}' did not match approved catalog` };
      }
    }

    return {
      eligible: true,
      matchedCountry: countryIso || lead.GLUSR_COUNTRY || 'Export',
      matchedProduct: matchedProduct || lead.ETO_OFR_TITLE
    };
  }

  // Execute instant purchase API call
  function snipeLeadImmediately(lead, index, source = 'PRE_RENDER', targetId = null) {
    const ofrid = String(lead.ETO_OFR_ID || '');
    if (!ofrid || alreadyBoughtOffers.has(ofrid)) return;
    alreadyBoughtOffers.add(ofrid);

    const rules = getCachedRules();
    const evalResult = evaluateLeadInMemory(lead, rules);
    if (!evalResult.eligible) {
      console.log(`[Bliss Sniper ⚡] Lead skipped: "${lead.ETO_OFR_TITLE}" [${lead.FK_GL_COUNTRY_ISO}] -> ${evalResult.reason}`);
      if (targetId) {
        window.postMessage({ type: 'BLISS_API_BUY_FAILED', targetId, reason: evalResult.reason }, '*');
      }
      return;
    }

    console.log(
      `%c[Bliss Sniper ⚡ ${source}] MATCHED! Sniping lead BEFORE render: ${evalResult.matchedProduct} (${evalResult.matchedCountry})`,
      'color: #10b981; font-weight: bold; font-size: 13px;'
    );

    const glusrId = getGlusrId();
    const mcatId = lead.FK_GLCAT_MCAT_ID || lead.PRIME_MCAT_ID || '';
    const gridParams = lead.GRID_PARAMETERS || '37#1 1#0#A#0';

    const isRecentPage = window.location.href.includes('pref=recent');
    const landingPage = isRecentPage ? 'Recent' : 'Export';
    const tsearch_text = isRecentPage ? 'recent_buy_leads' : 'export_leads_relevant_DLB';
    const bl_page_location = isRecentPage ? 'page=recent#city=#mcatid=#locpref=3' : 'page=relevant#city=#mcatid=#locpref=3';

    const payload = {
      glusrId: String(glusrId),
      ofrid: ofrid,
      purchasemod: 'WEB',
      count: 2,
      tsearch_text: tsearch_text,
      serial: index + 1,
      responseTextArea: 0,
      bl_page_location: bl_page_location,
      matched_mcat_id: String(mcatId),
      order_value_flag: '',
      is_bulk_order: '',
      ofrtitle: lead.ETO_OFR_TITLE || '',
      mapped_mcat_id: String(mcatId),
      GRID_PARAMETERS: gridParams,
      ptime: new Date().toISOString().replace('T', ' ').slice(0, 19),
      pref: window.location.href,
      grid_lead_pos: index + 1,
      search_top_mcat_id: '',
      search_mcat_priority_level: '',
      searchTracking: '',
      NIClick: 2,
      landingPage: landingPage
    };

    fetch('/blreact/contactBuyNow', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'x-requested-with': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(result => {
      console.log('[Bliss Sniper ⚡] Purchase Response:', result);
      if (result && (result.Status === 'Success' || result.Flag === '1' || result.pur_id)) {
        const buyer = (result.Data && result.Data[0]) ? result.Data[0] : {};
        const successData = {
          type: 'BLISS_SNIPER_SUCCESS',
          ofrid,
          pur_id: result.pur_id,
          matchedProduct: evalResult.matchedProduct,
          matchedCountry: evalResult.matchedCountry,
          leadTitle: lead.ETO_OFR_TITLE,
          buyer: {
            name: buyer.GLUSR_NAME || '',
            mobile: buyer.GLUSR_USR_PH_MOBILE || '',
            mobileCountry: buyer.GLUSR_USR_MOBILE_COUNTRY || '49',
            country: buyer.GLUSR_COUNTRY || '',
            countryIso: buyer.FK_GL_COUNTRY_ISO || ''
          }
        };

        window.postMessage(successData, '*');

        if (targetId) {
          window.postMessage({
            type: 'BLISS_API_BUY_SUCCESS',
            targetId,
            ofrid,
            pur_id: result.pur_id,
            buyer: successData.buyer
          }, '*');
        }
      } else {
        console.warn('[Bliss Sniper ⚡] Purchase response unhandled status:', result);
        if (targetId) {
          window.postMessage({
            type: 'BLISS_API_BUY_FAILED',
            targetId,
            reason: result?.Status || 'API purchase unsuccessful'
          }, '*');
        }
      }
    })
    .catch(err => {
      console.error('[Bliss Sniper ⚡] Pre-render purchase failed:', err);
      if (targetId) {
        window.postMessage({ type: 'BLISS_API_BUY_FAILED', targetId, reason: err.message }, '*');
      }
    });
  }

  // 1. Trap window.__BL_DATA__ at document_start (BEFORE HTML parser reaches React DOM)
  let _blDataVal = undefined;
  try {
    Object.defineProperty(window, '__BL_DATA__', {
      configurable: true,
      enumerable: true,
      get: function () {
        return _blDataVal;
      },
      set: function (newVal) {
        _blDataVal = newVal;
        console.log('%c[Bliss Pre-Render Sniper] Intercepted window.__BL_DATA__ at document_start!', 'color: #38bdf8; font-weight: bold;');
        if (newVal && newVal.response && Array.isArray(newVal.response.DisplayList)) {
          newVal.response.DisplayList.forEach((lead, idx) => {
            snipeLeadImmediately(lead, idx, 'PRE_RENDER');
          });
        }
      }
    });
  } catch (e) {
    console.warn('[Bliss Pre-Render] Could not trap __BL_DATA__ property:', e);
  }

  // 2. Intercept background AJAX feed (/blreact/getBLDisplayData) for zero-latency infinite scroll sniping
  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    const response = await originalFetch.apply(this, args);
    try {
      const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url ? args[0].url : '');
      if (url.includes('/blreact/getBLDisplayData')) {
        const clone = response.clone();
        clone.json().then(data => {
          if (data && Array.isArray(data.DisplayList)) {
            console.log('[Bliss Sniper ⚡] Intercepted background leads feed:', data.DisplayList.length, 'leads');
            data.DisplayList.forEach((lead, idx) => {
              snipeLeadImmediately(lead, idx, 'BACKGROUND_FEED');
            });
          }
        }).catch(() => {});
      }
    } catch (e) {}
    return response;
  };

  // 3. Listen for requests from content script
  window.addEventListener('message', function (event) {
    if (!event.data || typeof event.data !== 'object') return;

    // Cache update from content script
    if (event.data.type === 'BLISS_UPDATE_RULES_CACHE') {
      try {
        localStorage.setItem('bliss_ai_rules', JSON.stringify(event.data.rules));
        console.log('[Bliss Main World] Synchronized in-memory rules cache:', event.data.rules.products?.length, 'products');
      } catch (e) {}
      return;
    }

    // Direct API Purchase request from DOM scanner fallback
    if (event.data.type === 'BLISS_API_BUY_LEAD') {
      const { targetId, leadTitle, cardIndex } = event.data;
      let offerItem = null;
      if (window.__BL_DATA__ && window.__BL_DATA__.response && Array.isArray(window.__BL_DATA__.response.DisplayList)) {
        const list = window.__BL_DATA__.response.DisplayList;
        offerItem = list.find(item => {
          const t = (item.ETO_OFR_TITLE || '').trim().toLowerCase();
          return t && leadTitle && (t.includes(leadTitle.toLowerCase()) || leadTitle.toLowerCase().includes(t));
        }) || (cardIndex !== undefined ? list[cardIndex] : null);
      }
      if (offerItem) {
        snipeLeadImmediately(offerItem, cardIndex || 0, 'FALLBACK_TRIGGER', targetId);
      } else {
        window.postMessage({ type: 'BLISS_API_BUY_FAILED', targetId, reason: 'Offer ID not found' }, '*');
      }
      return;
    }

    // Trigger click on IndiaMART card button in page's real JS context (including jQuery)
    if (event.data.type === 'BLISS_PAGE_CLICK') {
      const targetId = event.data.targetId;
      if (!targetId) return;

      const el = document.querySelector(`[data-bliss-target="${targetId}"]`);
      if (el) {
        console.log('[Bliss Main World] Triggering page-level click on button:', el);
        try {
          if (window.jQuery && typeof window.jQuery === 'function') {
            window.jQuery(el).trigger('mousedown');
            window.jQuery(el).trigger('mouseup');
            window.jQuery(el).trigger('click');
          }
        } catch (e) {}

        try {
          el.focus();
          el.click();
        } catch (e) {}
      }
    }

    // Autonomous modal handling
    if (event.data.type === 'BLISS_PAGE_SUBMIT_MODAL') {
      const modalContainers = Array.from(document.querySelectorAll(
        '#cb_modal, #cont_buyer_popup, #contact_buyer_popup, #gl_contact_buyer, #bld_modal, .modal, .popup, div[class*="cb_"], div[id*="cb_"], .bld_modal'
      )).filter(m => {
        try {
          const s = window.getComputedStyle(m);
          return s.display !== 'none' && s.visibility !== 'hidden' && s.opacity !== '0';
        } catch (e) {
          return false;
        }
      });

      const targets = modalContainers.length > 0 ? modalContainers : [document.body];

      for (const container of targets) {
        const radios = container.querySelectorAll('input[type="radio"], .cb_prod_list li, .prod_list li, [class*="prod_item"]');
        if (radios.length > 0) {
          const first = radios[0];
          try {
            if (first.tagName === 'INPUT') first.checked = true;
            if (window.jQuery) window.jQuery(first).trigger('click');
            first.click();
          } catch (e) {}
        }

        const submitBtns = Array.from(container.querySelectorAll(
          '#btn_contact_now, #btn_submit, #cb_submit, input[value*="Contact"], input[value*="Submit"], button[type="submit"], .btn_submit, .cb_btn, button, input[type="button"], a.btn'
        )).filter(b => {
          const txt = (b.innerText || b.value || '').trim().toLowerCase();
          return (
            txt.includes('contact') ||
            txt.includes('submit') ||
            txt.includes('send') ||
            txt.includes('proceed') ||
            txt.includes('confirm') ||
            txt.includes('buy lead')
          ) && !txt.includes('cancel') && !txt.includes('close');
        });

        for (const btn of submitBtns) {
          console.log('[Bliss Main World] Auto-clicking modal confirmation button:', btn);
          try {
            if (window.jQuery) window.jQuery(btn).trigger('click');
            btn.click();
          } catch (e) {}
          break;
        }
      }
    }
  });

  console.log('[Bliss Main World] Pre-Render Zero-Latency Sniper v3.0 fully armed.');
})();

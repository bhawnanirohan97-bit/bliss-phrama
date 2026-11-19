// Bliss Pharmex - Main World Injector (Runs directly in IndiaMART window context)
(function () {
  'use strict';

  console.log('[Bliss Main World] Initialized in IndiaMART context with Direct API Engine v2.0.');

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

  // Helper to extract glusrId from page
  function getGlusrId() {
    return document.getElementById('glusr_id')?.value ||
           window._glusr_id ||
           window.glusr_id ||
           '249901953';
  }

  // Helper to find lead in window.__BL_DATA__
  function findLeadInBlData(leadTitle, cardIndex) {
    try {
      if (window.__BL_DATA__ && window.__BL_DATA__.response && Array.isArray(window.__BL_DATA__.response.DisplayList)) {
        const list = window.__BL_DATA__.response.DisplayList;
        if (leadTitle) {
          const search = leadTitle.trim().toLowerCase();
          const match = list.find(item => {
            const t = (item.ETO_OFR_TITLE || '').trim().toLowerCase();
            return t && (t.includes(search) || search.includes(t));
          });
          if (match) return match;
        }
        if (cardIndex !== undefined && list[cardIndex]) {
          return list[cardIndex];
        }
      }
    } catch (e) {
      console.warn('[Bliss Main World] Could not read window.__BL_DATA__:', e);
    }
    return null;
  }

  // Direct API Purchase via https://seller.indiamart.com/blreact/contactBuyNow
  async function executeDirectApiBuy(eventData) {
    const { targetId, leadTitle, cardIndex, ofrid: incomingOfrid, mcatId: incomingMcatId } = eventData;
    console.log('[Bliss Main World] Attempting Direct /blreact/contactBuyNow API purchase for:', leadTitle);

    const glusrId = getGlusrId();
    const offerItem = findLeadInBlData(leadTitle, cardIndex);

    const ofrid = incomingOfrid || offerItem?.ETO_OFR_ID || null;
    const mcatId = incomingMcatId || offerItem?.FK_GLCAT_MCAT_ID || offerItem?.PRIME_MCAT_ID || '';
    const gridParams = offerItem?.GRID_PARAMETERS || '37#1 1#0#A#0';
    const ofrtitle = offerItem?.ETO_OFR_TITLE || leadTitle || '';

    if (!ofrid) {
      console.warn('[Bliss Main World] No offer ID found for lead. Triggering UI click fallback.');
      window.postMessage({ type: 'BLISS_API_BUY_FAILED', targetId, reason: 'Offer ID not found in page data' }, '*');
      return;
    }

    const payload = {
      glusrId: String(glusrId),
      ofrid: String(ofrid),
      purchasemod: 'WEB',
      count: 2,
      tsearch_text: 'export_leads_relevant_DLB',
      serial: (cardIndex !== undefined ? cardIndex + 1 : 1),
      responseTextArea: 0,
      bl_page_location: 'page=relevant#city=#mcatid=#locpref=3',
      matched_mcat_id: String(mcatId),
      order_value_flag: '',
      is_bulk_order: '',
      ofrtitle: ofrtitle,
      mapped_mcat_id: String(mcatId),
      GRID_PARAMETERS: gridParams,
      ptime: new Date().toISOString().replace('T', ' ').slice(0, 19),
      pref: window.location.href,
      grid_lead_pos: (cardIndex !== undefined ? cardIndex + 1 : 1),
      search_top_mcat_id: '',
      search_mcat_priority_level: '',
      searchTracking: '',
      NIClick: 2,
      landingPage: 'Export'
    };

    console.log('[Bliss Main World] Executing /blreact/contactBuyNow with payload:', payload);

    try {
      const resp = await fetch('/blreact/contactBuyNow', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'x-requested-with': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });

      if (!resp.ok) {
        throw new Error(`HTTP error ${resp.status}`);
      }

      const result = await resp.json();
      console.log('[Bliss Main World] /blreact/contactBuyNow Response:', result);

      if (result && (result.Status === 'Success' || result.Flag === '1' || result.pur_id)) {
        const buyer = (result.Data && result.Data[0]) ? result.Data[0] : {};
        window.postMessage({
          type: 'BLISS_API_BUY_SUCCESS',
          targetId,
          ofrid,
          pur_id: result.pur_id,
          buyer: {
            name: buyer.GLUSR_NAME || '',
            mobile: buyer.GLUSR_USR_PH_MOBILE || '',
            mobileCountry: buyer.GLUSR_USR_MOBILE_COUNTRY || '49',
            country: buyer.GLUSR_COUNTRY || '',
            countryIso: buyer.FK_GL_COUNTRY_ISO || ''
          }
        }, '*');
      } else {
        console.warn('[Bliss Main World] API returned unsuccessful status:', result);
        window.postMessage({
          type: 'BLISS_API_BUY_FAILED',
          targetId,
          reason: result?.Status || result?.issue_rectify || 'API unhandled status'
        }, '*');
      }
    } catch (err) {
      console.error('[Bliss Main World] Direct API purchase failed, falling back to UI click:', err);
      window.postMessage({ type: 'BLISS_API_BUY_FAILED', targetId, reason: err.message }, '*');
    }
  }

  // 3. Listen for autonomous execution requests from Bliss content script
  window.addEventListener('message', function (event) {
    if (!event.data || typeof event.data !== 'object') return;

    // DIRECT API PURCHASE
    if (event.data.type === 'BLISS_API_BUY_LEAD') {
      executeDirectApiBuy(event.data);
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

    // Fully autonomous modal handling directly in IndiaMART's JS scope
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
        // A. Auto-select product radio button / product option
        const radios = container.querySelectorAll('input[type="radio"], .cb_prod_list li, .prod_list li, [class*="prod_item"]');
        if (radios.length > 0) {
          const first = radios[0];
          try {
            if (first.tagName === 'INPUT') first.checked = true;
            if (window.jQuery) window.jQuery(first).trigger('click');
            first.click();
          } catch (e) {}
        }

        // B. Auto-click Submit / Contact Buyer Now button inside modal
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

  console.log('[Bliss Main World] Autonomous purchase hooks ready with Direct API Engine.');
})();

// Bliss Pharmex - Main World Injector (Runs directly in IndiaMART window context)
(function () {
  'use strict';

  console.log('[Bliss Main World] Initialized in IndiaMART context.');

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

  // 3. Listen for autonomous execution requests from Bliss content script
  window.addEventListener('message', function (event) {
    if (!event.data || typeof event.data !== 'object') return;

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

  console.log('[Bliss Main World] Autonomous purchase hooks ready.');
})();

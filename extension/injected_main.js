// Bliss Pharmex - Main World Injector (Runs directly in IndiaMART window context)
(function () {
  'use strict';
  try {
    // Auto-approve native browser confirm dialogs (e.g., "Consume 1 BuyLead?")
    window.confirm = function (msg) {
      console.log('[Bliss Main World] Auto-approved native confirm dialog:', msg);
      return true;
    };

    // Auto-close native alert dialogs
    window.alert = function (msg) {
      console.log('[Bliss Main World] Auto-closed native alert dialog:', msg);
      return true;
    };
    
    console.log('[Bliss Main World] Auto-dialog handlers successfully installed.');
  } catch (err) {
    console.warn('[Bliss Main World] Failed to install dialog handlers:', err);
  }
})();

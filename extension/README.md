# Bliss Pharmex - IndiaMART AI Lead Auto-Buyer (Chrome Extension)

This Chrome Extension runs on your IndiaMART Seller Portal (`seller.indiamart.com`) and automatically buys BuyLeads matching your export criteria while you are offline or away from your desk.

---

### How It Works

1. **Monitors IndiaMART in Real-Time:** Scans new BuyLeads on your IndiaMART seller tab.
2. **Consults Your Local AI:** Sends the lead content (drug name, strength, quantity, location) to your local AI engine at `http://127.0.0.1:8000/api/ai-bot/evaluate`.
3. **Applies Your Strict Export Rules:**
   - **Domestic Rejection:** Immediately ignores inquiries from India (by country, Indian cities, phone code, or PCD franchise terms).
   - **Target Countries:** Only accepts leads destined for your selected countries (**USA, UK, Australia, Philippines, South Korea, Japan, China**, etc.).
   - **Product Catalogue:** Verifies the inquiry against your 923 pharmaceutical formulations.
4. **Auto-Clicks "Contact Buyer":** If the AI approves the lead and you are within your daily buy limit, it automatically clicks the button to purchase the lead.
5. **Safety Cap:** Automatically stops when your configured daily buy limit is reached.

---

### Step-by-Step: How to Install in Google Chrome (30 Seconds)

1. **Ensure your local Laravel app is running:**
   ```powershell
   cd C:\Users\ronit\.gemini\antigravity\scratch\bliss-pharma\api
   php artisan serve
   ```
   *(Verify you can open http://127.0.0.1:8000 in your browser)*

2. **Open Google Chrome Extensions page:**
   * In Chrome's address bar, type:
     ```
     chrome://extensions
     ```
     and press **Enter**.

3. **Enable "Developer mode":**
   * Turn ON the toggle in the top-right corner that says **"Developer mode"**.

4. **Load the Extension:**
   * Click the **"Load unpacked"** button in the top-left corner.
   * Browse to and select this folder:
     ```
     C:\Users\ronit\.gemini\antigravity\scratch\bliss-pharma\extension
     ```
   * Click **Select Folder**.

5. **Pin and Open the Extension:**
   * Click the puzzle icon in Chrome’s toolbar and pin **"Bliss Pharmex - IndiaMART AI Lead Auto-Buyer"**.
   * Open the extension popup to set your daily buy limit (default: 10 leads/day).

---

### How to Run It on IndiaMART

1. Log into your IndiaMART seller account:
   👉 **[https://seller.indiamart.com/blp/](https://seller.indiamart.com/blp/)** (or your BuyLead / Lead Manager page).
2. You will see a small dark badge in the bottom-right corner:
   `🟢 Bliss AI Auto-Buyer: Active | Bought: 0/10`
3. Leave that browser tab open. As new leads appear or refresh:
   * Domestic/unwanted leads will be tagged with: `⛔ Bliss AI: [Reason]` and dimmed.
   * Target export leads will be highlighted in green and **automatically purchased**.

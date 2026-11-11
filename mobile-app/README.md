# Bliss Pharmex - Mobile AI Bot Controller App

This mobile app allows you to control the **Bliss Pharmex AI Lead Catcher** directly from your smartphone.

---

### Method 1: Instant Install on Phone (No Coding / 10 Seconds)
The web controller has been configured as an **Installable Progressive Web App (PWA)**:

1. Connect your phone to the same Wi-Fi as your laptop (or use your Railway cloud URL).
2. Open Chrome or Safari on your phone and go to:
   👉 **`http://192.168.31.82:8000/mobile`** (or your cloud URL)
3. Tap the browser menu (`⋮` in Chrome on Android, or Share `⎋` in Safari on iPhone).
4. Tap **"Install App"** or **"Add to Home Screen"**.
5. The **Bliss AI Lead Bot** icon will appear on your phone's home screen.
6. When opened, it runs full-screen as a native app without browser bars!

---

### Method 2: React Native / Expo Native App

To run or build this React Native mobile app on your machine:

1. Install dependencies:
   ```bash
   cd mobile-app
   npm install
   ```

2. Start the development server:
   ```bash
   npx expo start
   ```

3. Scan the QR code with:
   - **Android:** Expo Go app from Google Play Store.
   - **iPhone:** Camera app (opens in Expo Go).

4. Build a standalone Android APK:
   ```bash
   npx eas-cli build -p android --profile preview
   ```

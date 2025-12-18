# Screenshot Requirements for HighLevel Marketplace Submission

## 🚨 Critical Whitelabel Compliance Rules

### ❌ NEVER Include:
1. **"HighLevel"** text or branding
2. **"HighLevel"** logo or color scheme
3. **gohighlevel.com** URLs in browser address bar
4. Any **GHL-specific UI elements** (if screenshot shows the CRM interface)
5. References to **"GoHighLevel"**, **"GHL"**, or **"HighLevel"**
6. HighLevel's purple/blue brand colors as primary colors

### ✅ ALWAYS Include:
1. **PayTR branding** (your logo, colors, name)
2. **Generic CRM terminology** ("Dashboard", "Payments", "Settings", etc.)
3. **Your app's unique features** (PayTR integration, Turkish payment flow)
4. **Professional, clean UI** with good contrast and readability
5. **Real-looking data** (not Lorem Ipsum or obviously fake)

---

## 📸 Required Screenshots (3-5 recommended)

### Screenshot 1: OAuth Installation Success
**What to show:**
- Success message: "Integration successfully installed!"
- PayTR logo prominently displayed
- Clear call-to-action button
- **NO** HighLevel branding

**Recommended approach:**
- Take screenshot of `/oauth/success` page (already whitelabel-compliant)
- Crop out browser URL bar if it shows the domain
- Ensure good lighting and high resolution (1920x1080 or 1280x720)

**File:** `public/images/screenshot-1-installation-success.png`

---

### Screenshot 2: PayTR Setup Configuration
**What to show:**
- PayTR merchant credentials form
- Fields: Merchant ID, Merchant Key, Merchant Salt
- Test/Live mode toggle
- Professional form design
- **NO** HighLevel references

**Recommended approach:**
- Take screenshot of `/paytr/setup?iframe=1` page
- Show the setup form with placeholder values (not real credentials!)
- Use browser DevTools to hide URL bar before screenshot
- Ensure form looks professional and complete

**File:** `public/images/screenshot-2-paytr-setup.png`

---

### Screenshot 3: Payment Page (iframe)
**What to show:**
- PayTR payment iframe in action
- Credit card form from PayTR
- Amount, description, and payment details
- Professional layout
- **NO** HighLevel branding

**Recommended approach:**
- Test payment flow with test credentials
- Take screenshot when PayTR iframe loads
- Show the payment form with sample transaction
- Crop to show only the payment interface (not full browser)

**File:** `public/images/screenshot-3-payment-iframe.png`

---

### Screenshot 4 (Optional): Transaction Success
**What to show:**
- Payment confirmation page
- Transaction ID and details
- Success icon and message
- Return to CRM button

**Recommended approach:**
- Complete a test payment
- Screenshot the success page at `/payments/success`
- Ensure whitelabel compliance

**File:** `public/images/screenshot-4-payment-success.png`

---

### Screenshot 5 (Optional): Features Overview
**What to show:**
- List of PayTR features:
  - Credit/Debit card payments
  - Installment plans
  - Turkish Lira (TRY) support
  - Recurring subscriptions
  - Card storage
- Professional feature list design

**Recommended approach:**
- Create a custom features showcase page
- Or screenshot landing page feature section
- Highlight PayTR-specific capabilities

**File:** `public/images/screenshot-5-features.png`

---

## 🛠️ How to Create Screenshots

### Method 1: Browser DevTools (Recommended)

```bash
# 1. Start your app
php artisan serve

# 2. Open Chrome/Firefox DevTools
# Press F12 → Device Toolbar (Ctrl+Shift+M)
# Set viewport: 1280x720 or 1920x1080

# 3. Navigate to pages:
- http://localhost:8000/oauth/success
- http://localhost:8000/paytr/setup?iframe=1
- http://localhost:8000/payments/page (requires test data)

# 4. Hide URL bar:
# Right-click → Inspect Element → Delete address bar element
# OR use browser's "Fullscreen Screenshot" feature

# 5. Take screenshot:
# - Chrome: DevTools → 3 dots menu → Capture screenshot
# - Firefox: Right-click → Take Screenshot → Save full page
# - OR use OS screenshot tool (Cmd+Shift+4 on Mac, Win+Shift+S on Windows)
```

### Method 2: Automated Screenshot Tool

```bash
# Install Puppeteer (Node.js screenshot tool)
npm install puppeteer

# Create screenshot script
node create-screenshots.js
```

**create-screenshots.js:**
```javascript
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();

  // Set viewport
  await page.setViewport({ width: 1280, height: 720 });

  // Screenshot 1: Installation Success
  await page.goto('http://localhost:8000/oauth/success');
  await page.screenshot({
    path: 'public/images/screenshot-1-installation-success.png',
    fullPage: false
  });

  // Screenshot 2: PayTR Setup
  await page.goto('http://localhost:8000/paytr/setup?iframe=1');
  await page.screenshot({
    path: 'public/images/screenshot-2-paytr-setup.png',
    fullPage: true
  });

  await browser.close();
})();
```

---

## 📐 Technical Specifications

### Image Requirements:
- **Format**: PNG or JPG (PNG preferred for better quality)
- **Resolution**: Minimum 1280x720, Recommended 1920x1080
- **Aspect Ratio**: 16:9 (widescreen)
- **File Size**: Max 2MB per image (compress if needed)
- **Color Space**: sRGB
- **DPI**: 72 (web standard)

### Optimization:
```bash
# Use ImageOptim (Mac) or TinyPNG (Web) to compress
# Target: Under 500KB per screenshot for faster loading

# Example with ImageMagick:
convert screenshot.png -quality 85 -resize 1920x1080 screenshot-optimized.png
```

---

## ✅ Pre-Submission Checklist

Before updating marketplace.json:

- [ ] All screenshots are **whitelabel-compliant** (no HighLevel references)
- [ ] Images are **high-resolution** (min 1280x720)
- [ ] Screenshots show **real app functionality** (not mock-ups)
- [ ] All images are **optimized** (under 1MB each)
- [ ] File names are **descriptive** and numbered
- [ ] Screenshots are **hosted publicly** (accessible URLs)
- [ ] URLs in marketplace.json are **correct and working**
- [ ] Test each screenshot URL in browser (should load image directly)

---

## 📝 Update marketplace.json

After creating screenshots:

```json
"screenshots": [
  "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/images/screenshot-1-installation-success.png",
  "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/images/screenshot-2-paytr-setup.png",
  "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/images/screenshot-3-payment-iframe.png",
  "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/images/screenshot-4-payment-success.png"
]
```

Test URLs:
```bash
curl -I https://yerelodeme-payment-app-master-a645wy.laravel.cloud/images/screenshot-1-installation-success.png
# Should return: HTTP/1.1 200 OK
# Content-Type: image/png
```

---

## 🎨 Design Tips

### Color Palette (Whitelabel-Friendly):
- **Primary**: PayTR brand color or neutral blue (#3498db)
- **Success**: Green (#27ae60)
- **Error**: Red (#e74c3c)
- **Background**: White or light gray (#f8f9fa)
- **Text**: Dark gray (#2c3e50)

### Font Recommendations:
- **Headings**: System fonts (Arial, Helvetica, sans-serif)
- **Body**: Readable sans-serif fonts
- **Avoid**: HighLevel's custom fonts

### Layout Principles:
- **Clarity**: Clear visual hierarchy
- **Simplicity**: Not too cluttered
- **Professionalism**: Corporate-friendly design
- **Focus**: Highlight PayTR functionality

---

## 🚫 Common Mistakes to Avoid

1. **Showing HighLevel Logo**: Even in screenshots of the CRM, blur/crop it out
2. **Including URL Bar**: Browser address bars can reveal gohighlevel.com
3. **Using Real Credentials**: Never show actual merchant keys/passwords
4. **Poor Quality**: Blurry or low-resolution images look unprofessional
5. **Fake Data**: Don't use "Lorem Ipsum" - use realistic sample data
6. **Inconsistent Branding**: All screenshots should have consistent design
7. **Dark Mode Only**: Provide screenshots that work for all users
8. **Mobile Screenshots**: Unless specifically mobile app, use desktop view

---

## 📞 Need Help?

If you're unsure whether a screenshot is whitelabel-compliant:
1. Ask yourself: "Does this contain 'HighLevel' anywhere?"
2. Check for HighLevel's color scheme (purple/blue combo)
3. Look for gohighlevel.com URLs
4. Verify no GHL-specific UI elements are visible

**When in doubt, remove it or blur it out!**

---

## 📁 File Organization

```
public/images/
├── paytr-logo.png (your app icon)
├── screenshot-1-installation-success.png
├── screenshot-2-paytr-setup.png
├── screenshot-3-payment-iframe.png
├── screenshot-4-payment-success.png (optional)
└── screenshot-5-features.png (optional)
```

Ensure all files are:
- Committed to Git
- Deployed to production server
- Publicly accessible (test URLs in browser)
- Referenced correctly in marketplace.json

---

## ✨ Example: Good vs Bad Screenshots

### ❌ BAD Example:
```
[Screenshot showing:]
- HighLevel logo in top-left
- URL bar showing "app.gohighlevel.com"
- Text: "Connect your HighLevel account"
- HighLevel's purple brand color
```

### ✅ GOOD Example:
```
[Screenshot showing:]
- PayTR logo prominently displayed
- Generic "CRM Integration" terminology
- URL bar hidden or cropped
- Neutral/PayTR brand colors
- Clear PayTR functionality showcase
```

---

**Last Updated**: 2025-12-18
**Version**: 1.0
**Status**: Ready for screenshot creation

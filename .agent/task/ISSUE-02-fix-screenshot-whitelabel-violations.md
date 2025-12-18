# ISSUE-02: Fix Screenshot Whitelabel Violations

## Priority
**Critical**

## Category
**Compliance / Content**

## Issue Description
The app preview images contain mentions of "HighLevel" which is a breach of whitelabel policy. Since this is a whitelabel app, any HighLevel references across the app (including screenshots, UI, error messages, documentation) constitute a policy violation that blocks marketplace approval.

**Current State:**
- Screenshot files reference HighLevel branding
- Screenshots may show HighLevel UI elements, logos, or text
- Violates marketplace whitelabel requirements

**Impact of Whitelabel Breach:**
- Immediate marketplace rejection
- Cannot be approved until all references are removed
- Damages professional reputation with marketplace team

## User Impact
**Severity: Critical (Blocking Issue)**
- **Marketplace Approval**: Prevents app from being published
- **Business Impact**: Cannot acquire new customers until resolved
- **Brand Trust**: Whitelabel violations suggest unprofessional development
- **Compliance Risk**: Violates marketplace terms of service

## Acceptance Criteria
1. Review all existing app screenshots for HighLevel references
2. Remove or regenerate any screenshots containing:
   - "HighLevel" branding, logos, or text
   - HighLevel UI elements that identify the platform
   - HighLevel domain names or URLs
   - References to "GHL" or "Go High Level"
3. Create new whitelabel-compliant screenshots showing:
   - PayTR integration interface
   - Payment configuration screens
   - Transaction flow examples
   - Setup/onboarding screens
4. Ensure screenshots are professional quality (recommended 1280x800 or higher)
5. Update marketplace.json screenshots URLs
6. Host new screenshots at proper public URLs
7. Verify all screenshots load correctly and are whitelabel-compliant

## Screenshot Requirements
**Technical Specs:**
- Resolution: 1280x800px or 1920x1080px (recommended)
- Format: PNG or JPEG
- File size: < 2MB per image
- Count: 2-5 screenshots showing key features
- Content: Must not reference HighLevel in any way

**Recommended Screenshots:**
1. **PayTR Configuration Screen** - Shows merchant ID setup (sanitized)
2. **Payment Page Preview** - PayTR iframe payment interface
3. **Transaction Dashboard** - Payment history and status (generic branding)
4. **Setup Success Screen** - Successful integration confirmation
5. **Payment Methods** - Card storage and payment options

## Technical Notes
**Files to Update:**
- `public/images/screenshot-1.png` (currently missing)
- `public/images/screenshot-2.png` (currently missing)
- `marketplace.json` - Update screenshot URLs array

**Screenshot Guidelines:**
- Use generic CRM language instead of "HighLevel"
- Replace any visible "HighLevel" text with "Your CRM" or "Platform"
- Use neutral color schemes (avoid HighLevel's brand colors if obvious)
- Focus on PayTR-specific features and UI
- Sanitize any merchant/customer data shown
- Consider adding Turkish language screenshots for local market appeal

## Implementation Steps
1. Create screenshots directory if missing
2. Generate/capture 3-5 whitelabel-compliant screenshots
3. Optimize images for web (compress without quality loss)
4. Upload to `public/images/` directory
5. Update marketplace.json with correct URLs
6. Deploy to production server
7. Verify screenshot URLs are publicly accessible
8. Test marketplace preview rendering

## Whitelabel Compliance Checklist
- [ ] No "HighLevel" text visible in screenshots
- [ ] No HighLevel logos or branding
- [ ] No app.gohighlevel.com URLs visible
- [ ] No HighLevel-specific UI elements that identify the platform
- [ ] All branding is either neutral or PayTR-focused
- [ ] Screenshots accurately represent the app's functionality
- [ ] File names don't reference HighLevel

## Estimated Effort
**M (4-6 hours)**
- 2 hours: Design and capture whitelabel screenshots
- 1 hour: Image editing and optimization
- 1 hour: Upload and configure hosting
- 1 hour: Testing and verification
- 1 hour: Documentation and deployment

## Dependencies
- Requires access to production environment for screenshot capture
- May need design tool (Figma, Photoshop) for editing
- Requires public hosting for images (currently using Laravel Cloud)

## Related Issues
- ISSUE-03 (Error message whitelabel violations)
- See `whitelabel-compliance-checklist.md` for comprehensive audit

## Risk Assessment
**High Risk if Not Fixed:**
- Continued marketplace rejection
- Cannot publish app or acquire customers
- May damage relationship with HighLevel marketplace team

## Testing Checklist
- [ ] All screenshots load from public URLs
- [ ] Screenshots display correctly in marketplace preview
- [ ] No HighLevel references visible in any screenshot
- [ ] Images are high quality and professional
- [ ] Screenshots accurately represent app features
- [ ] File sizes are optimized for web

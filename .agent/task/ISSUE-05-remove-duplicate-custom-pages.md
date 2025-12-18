# ISSUE-05: Remove Duplicate Custom Pages

## Priority
**High**

## Category
**Technical / Configuration**

## Issue Description
The marketplace.json configuration contains 2 duplicate custom pages (Getting Started and Settings) that are identical. The marketplace reviewer noted:

> "There is 2 custom pages for this APP one is getting started and another one is settings and both of them are same. Please remove one of them"

**Current State:**
- Multiple custom pages defined in marketplace.json settings
- Pages appear to serve the same purpose or show identical content
- Causes confusion for users and reviewers
- Wastes development resources maintaining duplicate pages

## User Impact
**Severity: Medium-High**
- **User Confusion**: Two pages with same content creates poor UX
- **Navigation Issues**: Unclear which page to use for configuration
- **Marketplace Standards**: Fails marketplace quality guidelines
- **Maintenance Burden**: Duplicate code requires double updates

## Acceptance Criteria
1. Identify which custom pages exist in marketplace.json
2. Determine which page should be kept (Settings vs Getting Started)
3. Remove duplicate page from marketplace.json configuration
4. Ensure remaining page serves all necessary functions
5. Update any internal links/redirects that reference removed page
6. Verify single page contains all required setup/configuration options
7. Test that marketplace displays only one custom page
8. Update documentation to reflect single custom page

## Investigation Required

### 1. Review marketplace.json Configuration
**File:** `marketplace.json` (lines 29-37)

Current configuration shows:
```json
"settings": {
  "pages": [
    {
      "title": "Settings",
      "path": "/settings",
      "iframe": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?iframe=1"
    }
  ]
}
```

**Action:** Check if there's another page definition we're missing (could be in different config file or API registration)

### 2. Identify All Custom Pages
Possible locations:
- marketplace.json settings.pages array
- Dynamic page registration in OAuthController
- HighLevelService provider registration
- PayTR setup controller routes

### 3. Routes to Review
**File:** `routes/web.php`

Check for:
- `/paytr/setup` route
- `/settings` route
- Any "getting started" or onboarding routes
- Multiple setup/configuration endpoints

## Recommended Solution

### Option A: Keep "Settings" Page Only
**Rationale:**
- "Settings" is more intuitive for configuration
- Matches standard marketplace patterns
- Users expect to find config in "Settings"

**Implementation:**
1. Keep Settings page in marketplace.json
2. Remove any "Getting Started" page references
3. Ensure Settings page includes:
   - PayTR credential configuration
   - Integration status
   - Help/documentation links
   - Basic troubleshooting

### Option B: Keep "Getting Started" Page Only
**Rationale:**
- Better for first-time setup flow
- Guides users through initial configuration
- More user-friendly for onboarding

**Implementation:**
1. Remove Settings page
2. Rename "Getting Started" to include settings functionality
3. Ensure page covers both initial setup and ongoing config

### Recommended: Option A (Settings Only)
Settings is more standard and meets both initial setup and ongoing configuration needs.

## Technical Implementation

### 1. Update marketplace.json
```json
{
  "settings": {
    "pages": [
      {
        "title": "Settings",
        "path": "/settings",
        "iframe": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?iframe=1",
        "description": "Configure your PayTR credentials and integration settings"
      }
    ]
  }
}
```

### 2. Review and Consolidate Routes
**File:** `routes/web.php`

Ensure single endpoint handles all configuration:
```php
// Keep this
Route::get('/paytr/setup', [PayTRSetupController::class, 'show'])->name('paytr.setup');

// Remove any duplicate routes like:
// Route::get('/getting-started', ...);
// Route::get('/onboarding', ...);
```

### 3. Consolidate Controller Actions
**File:** `app/Http/Controllers/PayTRSetupController.php`

Ensure single view handles:
- Initial PayTR credential setup
- Viewing current configuration
- Updating PayTR credentials
- Testing connection
- Help documentation

### 4. Update View Template
**File:** `resources/views/paytr/setup.blade.php`

Ensure it includes:
- Clear page title: "PayTR Settings"
- Credential input fields (Merchant ID, Key, Salt)
- Save/Update functionality
- Connection test feature
- Current integration status
- Help/documentation section

## Testing Checklist
- [ ] Only one custom page appears in marketplace listing
- [ ] Custom page loads correctly in iframe
- [ ] All configuration options available in single page
- [ ] No broken links to removed page
- [ ] OAuth flow redirects to correct page
- [ ] Settings page handles both new setup and updates
- [ ] Help documentation is accessible
- [ ] Integration status displays correctly

## Files to Review/Update
1. `marketplace.json` - Remove duplicate page config
2. `routes/web.php` - Consolidate setup routes
3. `app/Http/Controllers/PayTRSetupController.php` - Single setup controller
4. `resources/views/paytr/setup.blade.php` - Consolidated setup view
5. `app/Http/Controllers/OAuthController.php` - Update redirects (line 153)
6. Any documentation referencing multiple pages

## Estimated Effort
**S (2-4 hours)**
- 1 hour: Investigate current page configurations
- 1 hour: Remove duplicate page and update config
- 1 hour: Consolidate routes and controllers if needed
- 1 hour: Testing and verification

## Dependencies
- None (can be implemented independently)

## Related Issues
- None directly, but improves overall app quality

## Risk Assessment
**Low Risk:**
- Simple configuration change
- No breaking changes to existing functionality
- Easy to test and verify

## Implementation Steps
1. Audit marketplace.json and identify all custom pages
2. Review corresponding routes and controllers
3. Choose which page to keep (recommend "Settings")
4. Remove duplicate page from marketplace.json
5. Consolidate any duplicate routes/controllers
6. Update OAuth redirects if needed
7. Test marketplace preview
8. Verify single page appears in installed app
9. Update documentation

## Success Criteria
- [ ] Only one custom page in marketplace.json
- [ ] Single page provides all configuration functionality
- [ ] No duplicate content or confusion
- [ ] Marketplace reviewer approves single page setup
- [ ] Users can complete setup using one page

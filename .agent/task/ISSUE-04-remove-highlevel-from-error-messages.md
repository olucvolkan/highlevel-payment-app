# ISSUE-04: Remove HighLevel References from Error Messages

## Priority
**Critical**

## Category
**Compliance / Technical**

## Issue Description
Error messages displayed to users contain "HighLevel" references, which violates whitelabel policy. The marketplace reviewer specifically noted:

> "It also has mention of HighLevel which is a breach of whitelabel. Please fix it."

**Example from rejection feedback:**
```
Error Details: Failed to obtain Location token: Token exchange failed:
Client error: `POST https://services.leadconnectorhq.com/oauth/locationToken`...
```

The error message mentions:
- "HighLevel" in the message text
- "services.leadconnectorhq.com" (HighLevel's domain)
- References to "Location token" (HighLevel-specific terminology)

## User Impact
**Severity: Critical (Blocking Issue)**
- **Marketplace Approval**: Blocks app approval and publication
- **Whitelabel Violation**: Exposes platform details to end users
- **Professional Image**: Reveals integration complexity to users unnecessarily
- **User Confusion**: Technical error details confuse non-technical users

## Acceptance Criteria
1. All user-facing error messages are whitelabel-compliant
2. No mentions of "HighLevel", "GHL", "Go High Level" in UI
3. No HighLevel domain names (leadconnectorhq.com, gohighlevel.com) visible to users
4. Error messages are user-friendly and actionable
5. Detailed technical errors logged server-side only
6. Error pages (OAuth error, payment error) are whitelabel-compliant
7. Console/network errors don't expose HighLevel details
8. Success messages also checked for whitelabel compliance

## Files Requiring Updates

### 1. Error View Templates
- `resources/views/oauth/error.blade.php` - Line 145, 151, 158
- `resources/views/payments/error.blade.php` - Check for HighLevel references
- Any other error/success view templates

### 2. Controllers
- `app/Http/Controllers/OAuthController.php` - Lines 32, 43, 56, 90, 165
- `app/Http/Controllers/PaymentController.php` - Check all error responses
- `app/Http/Controllers/WebhookController.php` - Webhook error responses

### 3. Services
- `app/Services/HighLevelService.php` - API error handling
- `app/Services/PaymentService.php` - Payment error messages

### 4. API Responses
- `routes/api.php` - All JSON error responses
- Webhook failure responses
- Payment query error responses

## Current vs Proposed Error Messages

### OAuth Errors

**Current (Non-Compliant):**
```
"We encountered an issue while setting up your PayTR integration with HighLevel."

"Failed to obtain Location token: Token exchange failed: Client error:
`POST https://services.leadconnectorhq.com/oauth/locationToken`..."

"HighLevel integration completed! Now configure your PayTR credentials..."
```

**Proposed (Whitelabel-Compliant):**
```
"We encountered an issue while setting up your PayTR integration."

"Setup Error: Unable to complete integration setup. Please try again or contact support."

"Integration completed! Now configure your PayTR credentials to start accepting payments."
```

### Payment Errors

**Current:**
- May contain HighLevel API error messages
- May expose internal service details

**Proposed:**
```
"Payment processing error. Please try again."
"Payment method setup failed. Contact support for assistance."
"Transaction could not be completed. Please verify your payment details."
```

## Technical Implementation

### 1. Update Error View Template
**File:** `resources/views/oauth/error.blade.php`

```blade
<!-- Line 145: Current -->
We encountered an issue while setting up your PayTR integration with HighLevel.

<!-- Line 145: Fixed -->
We encountered an issue while setting up your PayTR integration.
```

### 2. Update Controller Error Messages
**File:** `app/Http/Controllers/OAuthController.php`

```php
// Line 43: Current
->with('error', 'Token exchange failed: ' . $tokenResponse['error']);

// Line 43: Fixed
->with('error', 'Setup failed. Please try again or contact support.');

// Line 90: Current
->with('error', 'Failed to obtain Location token: ' . $exchangeResult['error']);

// Line 90: Fixed
->with('error', 'Integration setup encountered an error. Please contact support.');

// Line 154: Current
'HighLevel integration completed! Now configure your PayTR credentials...'

// Line 154: Fixed
'Integration completed! Now configure your PayTR credentials to start accepting payments.'
```

### 3. Implement Error Sanitization Helper
Create utility to sanitize errors before displaying to users:

```php
// app/Helpers/ErrorSanitizer.php
class ErrorSanitizer
{
    public static function sanitize(string $error): string
    {
        // Remove HighLevel references
        $error = str_replace(['HighLevel', 'GHL', 'Go High Level'], 'platform', $error);

        // Remove domain names
        $error = preg_replace('/https?:\/\/[^\s]+leadconnectorhq\.com[^\s]*/', '[service]', $error);
        $error = preg_replace('/https?:\/\/[^\s]+gohighlevel\.com[^\s]*/', '[platform]', $error);

        // Remove technical details for user-facing messages
        if (app()->environment('production')) {
            $error = 'An error occurred during setup. Please contact support.';
        }

        return $error;
    }
}
```

### 4. Two-Tier Error Logging
**User-Facing (Simple & Generic):**
```php
return redirect()->route('oauth.error')
    ->with('error', 'Integration setup failed. Please try again or contact support.');
```

**Server-Side Logging (Detailed):**
```php
Log::error('OAuth token exchange failed', [
    'location_id' => $locationId,
    'error' => $tokenResponse['error'],
    'response' => $tokenResponse,
    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
]);
```

## Error Message Guidelines

### User-Facing Messages Should:
- Be concise and non-technical
- Provide actionable next steps
- Never mention "HighLevel" or related terms
- Not expose API endpoints or technical details
- Offer support contact if resolution isn't obvious
- Use neutral language (e.g., "platform" instead of "HighLevel")

### Server Logs Should:
- Include all technical details
- Contain full error messages and stack traces
- Include request/response data
- Use HighLevel-specific terminology for debugging
- Help developers diagnose issues quickly

## Testing Requirements

### Manual Testing
- [ ] Test each error scenario and verify user-facing message
- [ ] Search codebase for "HighLevel" in user-facing strings
- [ ] Search for "leadconnectorhq.com" in templates
- [ ] Search for "gohighlevel.com" in templates
- [ ] Verify browser console shows no HighLevel references
- [ ] Check network tab for exposed API endpoints

### Automated Testing
```php
// Test error messages are whitelabel-compliant
public function test_error_messages_are_whitelabel_compliant()
{
    $response = $this->get('/oauth/callback?code=invalid');

    $response->assertDontSee('HighLevel');
    $response->assertDontSee('leadconnectorhq.com');
    $response->assertDontSee('gohighlevel.com');
}
```

## Estimated Effort
**M (6-8 hours)**
- 2 hours: Audit all error messages and views
- 2 hours: Update templates and controllers
- 1 hour: Implement error sanitization helper
- 1 hour: Update API error responses
- 2 hours: Testing and verification

## Dependencies
- Should be implemented alongside ISSUE-03 (OAuth bug fix)
- Related to whitelabel compliance checklist

## Related Issues
- ISSUE-03 (OAuth location token bug - error messages need fixing)
- See `whitelabel-compliance-checklist.md` for comprehensive audit

## Search Commands for Audit
```bash
# Find all "HighLevel" references in user-facing files
grep -r "HighLevel" resources/views/
grep -r "HighLevel" app/Http/Controllers/

# Find domain references
grep -r "leadconnectorhq.com" resources/
grep -r "gohighlevel.com" resources/

# Find in JavaScript files
grep -r "HighLevel" public/
grep -r "HighLevel" resources/js/
```

## Success Criteria
- [ ] No "HighLevel" visible in any user-facing UI
- [ ] Error messages are generic and helpful
- [ ] Detailed errors logged server-side only
- [ ] OAuth error page is whitelabel-compliant
- [ ] Payment error page is whitelabel-compliant
- [ ] Console/network doesn't expose HighLevel details
- [ ] Marketplace reviewer approves whitelabel compliance

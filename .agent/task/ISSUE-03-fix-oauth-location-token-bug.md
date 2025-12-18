# ISSUE-03: Fix OAuth Location Token Exchange Bug

## Priority
**Critical**

## Category
**Technical / Bug Fix**

## Issue Description
The app installation flow is broken. When a user installs the app from their HighLevel account, the OAuth callback fails with a 400 Bad Request error during location token exchange.

**Error Message:**
```
We encountered an issue while setting up your PayTR integration with HighLevel.

Error Details: Failed to obtain Location token: Token exchange failed:
Client error: `POST https://services.leadconnectorhq.com/oauth/locationToken`
resulted in a `400 Bad Request` response:
{"message":"Location not found","error":"Bad Request","statusCode":400,"traceId":"97aacb8a-9279-481b-9b1c-5816a8365e90"}
```

**Root Cause Analysis:**
The error indicates the location token exchange endpoint is receiving invalid data or the location_id being sent doesn't exist in HighLevel's system. Possible causes:
1. location_id is not being extracted correctly from OAuth callback
2. Token exchange request is using wrong location_id
3. Company token is being sent instead of being exchanged for location token
4. API request format doesn't match HighLevel's expected payload
5. OAuth scopes are insufficient for location token exchange

## User Impact
**Severity: Critical (Blocking Issue)**
- **Installation Failure**: Users cannot install the app at all
- **Zero Adoption**: No one can use the app in production
- **Support Burden**: Every install attempt generates a support ticket
- **Revenue Impact**: Blocks all customer acquisition
- **User Frustration**: Failed installation creates negative first impression

## Acceptance Criteria
1. OAuth callback successfully exchanges authorization code for tokens
2. Location token is correctly obtained from HighLevel API
3. Location ID is properly extracted from OAuth callback parameters
4. Error handling provides meaningful feedback without exposing HighLevel
5. Installation flow completes successfully from marketplace
6. User is redirected to appropriate success page after installation
7. No 400 Bad Request errors during token exchange
8. All tokens are properly stored in database
9. Subsequent API calls use correct location-scoped token

## Technical Investigation Required
**Debug Steps:**
1. Log all incoming OAuth callback parameters
2. Verify location_id extraction logic in `OAuthController::extractLocationId()`
3. Check HighLevelService::exchangeCompanyTokenForLocation() implementation
4. Review API request payload sent to `/oauth/locationToken`
5. Verify OAuth scopes include location token exchange permissions
6. Test with actual HighLevel marketplace installation (not manual OAuth)
7. Compare request format with HighLevel API documentation
8. Check if location exists in test HighLevel account

**Files to Review:**
- `app/Http/Controllers/OAuthController.php` (lines 72-100, 224-259)
- `app/Services/HighLevelService.php` (exchangeCompanyTokenForLocation method)
- `app/Models/HLAccount.php` (needsLocationTokenExchange method)

## Technical Notes
**Current Implementation Analysis:**

From `OAuthController.php` line 79:
```php
$exchangeResult = $this->highLevelService->exchangeCompanyTokenForLocation($account, $locationId);
```

**Potential Issues:**
1. **Location ID Validation**: location_id from OAuth callback may be company_id instead
2. **API Endpoint**: Verify endpoint URL is correct (services.leadconnectorhq.com vs backend.leadconnectorhq.com)
3. **Request Format**: Check if payload matches HighLevel's expected format
4. **Token Type**: Verify we're sending Company token, not attempting to exchange Location token
5. **Scope Requirements**: Confirm OAuth scopes allow location token exchange

**Expected Fix Areas:**
```php
// OAuthController.php - Improve location_id extraction
protected function extractLocationId(Request $request, array $tokenResponse): ?string
{
    // Priority order:
    // 1. locationId from token response (most reliable)
    // 2. location_id from query parameter
    // 3. Validate that ID actually exists before using
}

// HighLevelService.php - Fix token exchange request
public function exchangeCompanyTokenForLocation($account, $locationId)
{
    // Ensure payload matches HighLevel API spec
    // Add comprehensive error logging
    // Validate response before returning
}
```

## Implementation Steps
1. **Add Debug Logging** (Day 1 - 2 hours)
   - Log all OAuth callback parameters
   - Log token exchange request/response
   - Log location_id extraction attempts
   - Deploy to staging for testing

2. **Test with Real Installation** (Day 1 - 2 hours)
   - Install app from HighLevel marketplace
   - Capture all logs and API traces
   - Identify exact point of failure
   - Document actual vs expected API behavior

3. **Fix Token Exchange Logic** (Day 2 - 4 hours)
   - Correct location_id extraction if needed
   - Fix API request payload format
   - Update error handling
   - Add validation before token exchange

4. **Comprehensive Testing** (Day 2 - 3 hours)
   - Test fresh installation from marketplace
   - Test with multiple HighLevel locations
   - Test token refresh flow
   - Verify subsequent API calls work

5. **Update Error Messages** (Related to ISSUE-04)
   - Remove "HighLevel" from error messages
   - Provide actionable user guidance
   - Log detailed errors server-side only

## Error Handling Improvements
**User-Facing (Whitelabel-Compliant):**
```
Setup Error: Unable to complete integration.
Please try again or contact support if the issue persists.
```

**Server-Side Logging (Detailed):**
```
OAuth token exchange failed
- Location ID: loc_xxx
- Error: 400 Bad Request
- Response: {"message":"Location not found",...}
- Request payload: {...}
- Company token: [redacted]
```

## Estimated Effort
**L (2-3 days / 16-24 hours)**
- 4 hours: Debug logging and investigation
- 4 hours: Testing with real marketplace installation
- 8 hours: Fix implementation and testing
- 4 hours: Error handling improvements
- 2 hours: Documentation and deployment
- 2 hours: Verification and smoke testing

## Dependencies
- Requires access to HighLevel marketplace for testing
- May need HighLevel support if API documentation is unclear
- Depends on ISSUE-04 for whitelabel-compliant error messages

## Related Issues
- ISSUE-04 (Error message whitelabel violations)
- See `.agent/SOPs/oauth-flow.md` for OAuth flow documentation

## Testing Checklist
- [ ] OAuth callback receives correct parameters
- [ ] Location ID is extracted successfully
- [ ] Token exchange request uses correct format
- [ ] 200 OK response from location token endpoint
- [ ] Location token stored in database
- [ ] Subsequent API calls succeed with location token
- [ ] Error handling provides user-friendly messages
- [ ] No "HighLevel" references in user-facing errors
- [ ] Installation completes successfully from marketplace
- [ ] Provider registration succeeds after token exchange

## Rollback Plan
- Keep current code in separate branch
- Test fix thoroughly in staging before production
- Monitor error rates after deployment
- Have database rollback scripts ready if needed

## Success Metrics
- 0 token exchange failures in production
- 100% successful installations from marketplace
- Reduced support tickets for installation issues
- Positive user feedback on installation experience

# .agent Documentation System

> **Purpose**: Optimized context management for Claude Code using the 10x methodology
> **Created**: 2025-11-22
> **Project**: HighLevel PayTR Payment Integration

## Quick Navigation

### 🏗️ System Documentation (`/system/`)
Core architecture and implementation details:
- **[database-schema.md](system/database-schema.md)** - Database tables and relationships
- **[api-endpoints.md](system/api-endpoints.md)** - All API routes with examples
- **[paytr-integration.md](system/paytr-integration.md)** - PayTR implementation details
- **[highlevel-integration.md](system/highlevel-integration.md)** - OAuth and webhook flows
- **[architecture.md](system/architecture.md)** - Design patterns and service layer

### 📋 Standard Operating Procedures (`/SOPs/`)
Step-by-step guides for common operations:
- **[paytr-hash-generation.md](SOPs/paytr-hash-generation.md)** - HMAC-SHA256 signature process
- **[oauth-flow.md](SOPs/oauth-flow.md)** - HighLevel OAuth token exchange
- **[payment-callback-handling.md](SOPs/payment-callback-handling.md)** - Callback verification steps

### 📝 Task Tracking (`/task/`)
Current and future development tasks:
- Currently empty - will be populated as new tasks arise

## Documentation Philosophy

### What's HERE vs. Root Docs

**`.agent/` (This Folder)**:
- ✅ Actual implemented code documentation
- ✅ Quick reference for existing features
- ✅ Database schema as-is
- ✅ API endpoints that work right now
- ✅ Step-by-step procedures (SOPs)

**Root Documentation**:
- `CLAUDE.md` - Future plans, requirements, design decisions
- `README.md` - User-facing project overview
- `PROJECT_STATUS.md` - Development progress and test status
- `LOCAL_TESTING_GUIDE.md` - Local development setup

**Rule**: `.agent/` documents the PRESENT (what's built), root docs describe the FUTURE (what's planned) and PROCESS (how to run it).

## When to Use This

### For Claude Code:
1. **Starting a new task** → Read `/system/architecture.md` first
2. **Working with database** → Check `/system/database-schema.md`
3. **Testing endpoints** → See `/system/api-endpoints.md`
4. **Debugging PayTR** → Reference `/system/paytr-integration.md`
5. **Need a procedure** → Look in `/SOPs/`

### For Developers:
1. **Understanding codebase** → Start with `/system/architecture.md`
2. **API integration** → Use `/system/api-endpoints.md`
3. **Payment flow issues** → Check `/SOPs/payment-callback-handling.md`

## Structure Benefits

1. **Reduced Context**: Each file is focused and concise
2. **No Duplication**: References `CLAUDE.md` instead of repeating
3. **Easy Updates**: Single source of truth for each topic
4. **Quick Search**: Organized by system vs. procedures

## Maintenance

- Update `system/` docs when code changes
- Add to `SOPs/` when new patterns emerge
- Use `task/` for work-in-progress tracking
- Keep this readme updated as index

## Project Context

**Tech Stack**: Laravel 12, PHP 8.3, PostgreSQL (Supabase)
**Integration**: HighLevel CRM + PayTR Payment Gateway
**Status**: MVP Complete (PayTR only, Iyzico planned)
**Test Coverage**: 67/67 tests passing (100%)

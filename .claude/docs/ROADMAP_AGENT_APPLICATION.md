# Roadmap: Agent Application

**Document:** `.claude/docs/ROADMAP_AGENT_APPLICATION.md`
**Related:** `.claude/docs/ARCHITECTURE_STATE.md` (current system architecture)
**Created:** 2026-01-21
**Status:** In Progress
**Last Updated:** 2026-01-22

---

## Executive Summary

Build the agent-facing portion of PropData where real estate agents can search properties, create collections, share via public links, and generate PDF spec sheets.

**Architecture Approach:** Subdomain routing to separate concerns
- `admin.propdata.test` → Internal admin (scraping, data management)
- `agents.propdata.test` → Agent application (search, collections, sharing)
- `propdata.test` → Public pages (shared collections, future marketing)

**Key Decisions:**
- Subdomain prefix: `agents.` (not `app.`)
- New user registration defaults to `agent` role
- Admins created via seeder only
- Test data seeders for both admin and agent users

---

## Phase 1: Subdomain Infrastructure ✅ COMPLETE

**Goal:** Create the foundation for subdomain-based routing without breaking anything.

**Laravel Features Used:**
- `Route::domain()` - Groups routes by subdomain
- Custom middleware - Role-based access control
- Custom LoginResponse - Redirect users to correct subdomain after auth

### Step 1.1: Domain Configuration ✅
- [x] Create `config/domains.php`
- [x] Update `.env` with domain values
- [x] Update `.env.example` with domain values

**Files Created:**
- `config/domains.php`

### Step 1.2: User Role System ✅
- [x] Create `UserRole` enum with `Admin` and `Agent` cases
- [x] Create migration to add `role` column to users table (default: 'agent')
- [x] Update `User` model with role cast and helper methods (`isAdmin()`, `isAgent()`)
- [x] **Added:** `homeUrl(bool $secure): string` method to User model for centralized redirect logic
- [x] **Added:** Explicit `role => UserRole::Agent` default to UserFactory
- [x] Run migration

**Files Created:**
- `app/Enums/UserRole.php`
- `database/migrations/2026_01_22_185333_add_role_to_users_table.php`

**Files Modified:**
- `app/Models/User.php`
- `database/factories/UserFactory.php`

### Step 1.3: Role Middleware ✅
- [x] Create `EnsureUserRole` middleware
  - Accepts role parameter (e.g., `role:admin`)
  - Checks authenticated user's role
  - Redirects to appropriate subdomain using `User::homeUrl()` if unauthorized
  - Handles unauthenticated users gracefully
- [x] Register middleware alias in `bootstrap/app.php`
- [x] **Added:** Comprehensive permission tests

**Files Created:**
- `app/Http/Middleware/EnsureUserRole.php`
- `tests/Feature/Middleware/EnsureUserRoleTest.php`

**Files Modified:**
- `bootstrap/app.php`

### Step 1.4: Auth Response Customization ✅
- [x] Create custom `LoginResponse` class using `User::homeUrl()`
- [x] **Added:** Create custom `RegisterResponse` class using `User::homeUrl()`
- [x] **Added:** Create custom `VerifyEmailResponse` class using `User::homeUrl()`
- [x] Register all responses in `FortifyServiceProvider`

**Implementation Note:** All three auth responses (login, register, email verification) use the centralized `User::homeUrl()` method for redirect logic, ensuring consistency. This was an addition to the original plan.

**Files Created:**
- `app/Http/Responses/LoginResponse.php`
- `app/Http/Responses/RegisterResponse.php`
- `app/Http/Responses/VerifyEmailResponse.php`

**Files Modified:**
- `app/Providers/FortifyServiceProvider.php`

### Step 1.5: User Seeder ✅
- [x] Create `UserSeeder` with admin and agent users
- [x] Register in `DatabaseSeeder`

**Files Created/Modified:**
- `database/seeders/UserSeeder.php`
- `database/seeders/DatabaseSeeder.php`

**Phase 1 Verification:** ✅ All checks passed

---

## Phase 2: Migrate Existing Functionality to Admin Subdomain ✅ COMPLETE

**Goal:** Move all current functionality to `admin.propdata.test` and verify it works identically.

**Critical Checkpoint:** ✅ PASSED - All functionality verified via Playwright browser testing.

### Step 2.1: Route Restructuring ✅
- [x] Wrap all existing authenticated routes in `Route::domain(config('domains.admin'))`
- [x] Add `role:admin` middleware to admin route group
- [x] Auth routes accessible on all subdomains (admin, agents)
- [x] **Changed:** Update ALL route names with `admin.` prefix for consistency (more comprehensive than originally planned)

**Route Naming Changes:**
| Original | New |
|----------|-----|
| `platforms.index` | `admin.platforms.index` |
| `platforms.show` | `admin.platforms.show` |
| `listings.index` | `admin.listings.index` |
| `listings.show` | `admin.listings.show` |
| `publishers.index` | `admin.publishers.index` |
| `publishers.show` | `admin.publishers.show` |
| `dedup.review` | `admin.dedup.review` |
| `runs.show` | `admin.runs.show` |

**Files Modified:**
- `routes/web.php` (major refactor)

### Step 2.2: Move Livewire Components to Admin Namespace ✅
- [x] Create `app/Livewire/Admin/` directory
- [x] Move all admin components to `Admin/` namespace
- [x] Update namespace declarations in all moved files
- [x] Update `#[Layout(...)]` attributes

**Components Moved:**
- `Dashboard.php` → `Admin/Dashboard.php`
- `Listings/Index.php` → `Admin/Listings/Index.php`
- `Listings/Show.php` → `Admin/Listings/Show.php`
- `Properties/Index.php` → `Admin/Properties/Index.php`
- `Properties/Show.php` → `Admin/Properties/Show.php`
- `Platforms/Index.php` → `Admin/Platforms/Index.php`
- `Platforms/Show.php` → `Admin/Platforms/Show.php`
- `Publishers/Index.php` → `Admin/Publishers/Index.php`
- `Publishers/Show.php` → `Admin/Publishers/Show.php`
- `ScrapeRuns/Show.php` → `Admin/ScrapeRuns/Show.php`
- `Dedup/ReviewCandidates.php` → `Admin/Dedup/ReviewCandidates.php`

### Step 2.3: Update Views ✅
- [x] Move Livewire views to `resources/views/livewire/admin/`
- [x] Update all hardcoded route references to use `admin.` prefix
- [x] Ensure layout still works for admin context

### Step 2.4: Layout Updates ✅
- [x] **Changed:** Single sidebar with conditional rendering (not separate files as originally planned)
- [x] Admin users see: Dashboard, Platforms, Listings, Properties, Publishers
- [x] Agent users see: Properties (only)
- [x] Sidebar uses `$isAdmin` variable for conditional rendering
- [x] Settings route in user menu uses role-appropriate route
- [x] **Changed:** Settings layout uses `auth()->user()?->isAdmin()` instead of host sniffing

**Design Decision:** Instead of creating a separate `agent-sidebar.blade.php` as originally planned, we use conditional rendering in the existing sidebar. This keeps styling consistent and reduces code duplication.

**Files Modified:**
- `resources/views/components/layouts/app/sidebar.blade.php`
- `resources/views/components/settings/layout.blade.php`

### Step 2.5: Verification Testing ✅
Tested via Playwright browser automation:

- [x] **Auth flows:** Login, logout, register all work correctly
- [x] **Admin pages:** Dashboard, Platforms, Listings, Properties, Publishers all functional
- [x] **Settings:** Profile settings load with correct navigation
- [x] **Agent Subdomain:** Login redirects correctly, shows agent navigation

**Phase 2 Exit Criteria:** ✅
- [x] All functionality verified
- [x] No regression from before subdomain migration
- [x] All 505 tests pass: `php artisan test`
- [x] Code formatted: `vendor/bin/pint --dirty`

---

## Phase 3: Agent UI Prototype (UI-First Approach) 🚧 IN PROGRESS

**Goal:** Build experimental UI/UX for agent property search to understand what data and features are actually needed before committing to database schemas.

**Rationale:** By building the UI first with real property data, we can:
- Discover what information agents actually need to see
- Understand what collection features make sense
- Iterate quickly on UX without migration concerns
- Inform the final database schema design

**Change from Original Plan:** Originally Phase 3 was "Collection Data Model". We've restructured to build UI first, then create database models informed by UI learnings.

**Prerequisite:** Phase 2 complete and verified. ✅

**Status:** In Progress

### Step 3.1: Property Search UI (Primary Focus)
- [ ] Replace placeholder with full property search component
- [ ] Use existing `Property` model data (93 properties available)
- [ ] Implement filters:
  - Location (state, city, colonia)
  - Price range (min/max) - via linked listings
  - Operation type (rent/sale) - via linked listings
  - Property type (apartment, house, commercial, office, warehouse, land)
  - Bedrooms, bathrooms, parking
  - Size range
- [ ] Display results as mobile-first card grid
- [ ] Each card shows: image, price, location, key features
- [ ] Pagination with Livewire

**Data Available:**
- 93 properties in database
- Property types: apartments (34), commercial (19), houses (16), offices (11), warehouses (8), land (5)
- Images stored in `listings.raw_data.images` (10-24 per listing)
- Price/operation via `listings.operations` JSON

**Files:**
- `app/Livewire/Agents/Properties/Index.php` (replace placeholder)
- `resources/views/livewire/agents/properties/index.blade.php` (new)

### Step 3.2: Property Detail View
- [ ] Create property detail page for agents
- [ ] Display:
  - Hero image with gallery (lightbox)
  - Price prominently displayed
  - Key stats (beds, baths, parking, size)
  - Full description
  - Amenities list
  - Location info
  - Publisher info (if available)
- [ ] Mobile-responsive design

**Files:**
- `app/Livewire/Agents/Properties/Show.php` (new)
- `resources/views/livewire/agents/properties/show.blade.php` (new)

### Step 3.3: Collection UI Mockup (Disabled/Visual Only)
- [ ] Add "Collections" link to agent sidebar (navigates to mockup)
- [ ] Create collections index page with static/mock data
- [ ] Create collection detail page with static/mock data
- [ ] "Add to Collection" button on property cards (shows toast: "Coming soon")
- [ ] Visual design exploration for:
  - How collections are displayed
  - What client info fields make sense
  - How properties are organized within a collection
  - Share/public link UI

**Note:** These features are UI-only - no database operations. The goal is to iterate on what feels right before building the backend.

**Files:**
- `app/Livewire/Agents/Collections/Index.php` (mock data)
- `app/Livewire/Agents/Collections/Show.php` (mock data)
- `resources/views/livewire/agents/collections/index.blade.php` (new)
- `resources/views/livewire/agents/collections/show.blade.php` (new)

### Step 3.4: Navigation & Layout Polish
- [ ] Update agent sidebar with Collections link
- [ ] Ensure consistent navigation between pages
- [ ] Mobile hamburger menu works correctly

**Files:**
- `resources/views/components/layouts/app/sidebar.blade.php` (modify)

**Phase 3 Verification:**
- [ ] Agent can search and filter properties with real data
- [ ] Property detail page shows all available information
- [ ] Collection UI mockups are navigable (even if non-functional)
- [ ] Mobile responsive design works
- [ ] Feedback gathered on what's missing or needs adjustment

---

## Phase 4: Collection Data Model (Informed by UI Prototype)

**Goal:** Create the database structure for agent collections based on learnings from Phase 3 UI prototype.

**Prerequisite:** Phase 3 UI complete and feedback incorporated.

**Status:** Pending (schema will be refined based on Phase 3 learnings)

### Step 4.1: Collections Table
- [ ] Create migration for `collections` table
- [ ] Fields TBD based on UI prototype feedback

### Step 4.2: Collection-Property Pivot Table
- [ ] Create migration for `collection_property` table
- [ ] Additional fields TBD based on UI prototype

### Step 4.3: Collection Model & Factory
- [ ] Create `Collection` model with relationships
- [ ] Create `CollectionFactory` for testing

### Step 4.4: Wire Up UI to Real Data
- [ ] Replace mock data in collection components with real queries
- [ ] Implement CRUD operations
- [ ] Add/remove properties from collections
- [ ] Write feature tests

**Phase 4 Verification:**
- [ ] Collections CRUD works completely
- [ ] All UI features now backed by real data
- [ ] Tests pass

---

## Phase 5: PDF Generation

**Goal:** Generate professional property spec sheets.

**Prerequisite:** Phase 4 complete.

### Step 5.1: Install PDF Package
- [ ] Run `composer require spatie/laravel-pdf`
- [ ] Verify Chromium/Puppeteer dependencies

### Step 5.2: PDF Template
- [ ] Create property spec sheet Blade template
- [ ] Professional layout with images, stats, description

### Step 5.3: PDF Controller & Routes
- [ ] Create `PropertyPdfController`
- [ ] Add routes for PDF generation

### Step 5.4: Wire Up UI Buttons
- [ ] Connect PDF download buttons in property/collection views

### Step 5.5: PDF Tests
- [ ] Test PDF generation and content

---

## Phase 6: Polish & Documentation

**Goal:** Final cleanup and documentation.

### Step 6.1: Local Development Documentation
- [ ] Create `docs/LOCAL_DEVELOPMENT.md`
- [ ] Document Herd subdomain setup
- [ ] Document seeder usage

### Step 6.2: Final Test Suite
- [ ] All tests pass
- [ ] Code formatted
- [ ] Manual smoke test

### Step 6.3: Browser Tests (Optional)
- [ ] Pest v4 browser tests for critical flows

---

## Implementation Notes

### Design Decisions Made During Implementation

1. **Single Sidebar with Conditional Rendering**: Instead of creating separate `admin-sidebar.blade.php` and `agent-sidebar.blade.php` files, we use a single sidebar with `@if($isAdmin)` conditional blocks. This keeps styling consistent and reduces code duplication.

2. **Centralized Redirect Logic**: The `User::homeUrl(bool $secure)` method centralizes all redirect URL logic. All auth responses (login, register, verify email) and the role middleware use this single source of truth.

3. **All Admin Routes Use `admin.*` Prefix**: For consistency and to avoid conflicts with future agent routes, all admin routes now use the `admin.` prefix (e.g., `admin.platforms.index`, `admin.listings.show`).

4. **Explicit Role in UserFactory**: The UserFactory now explicitly sets `role => UserRole::Agent` as the default, making test behavior predictable and matching the expected behavior for new user registrations.

5. **Settings Layout Uses User Role**: The settings layout determines the route prefix based on `auth()->user()?->isAdmin()` instead of inspecting the host/subdomain. This is more reliable and explicit.

6. **UI-First Approach for Collections**: Phase 3 was restructured to build UI prototypes first before creating database schemas. This allows us to iterate on UX and discover actual requirements before committing to migrations.

### Files Created (Phase 1 & 2)

**Configuration:**
- `config/domains.php` ✅

**Enums:**
- `app/Enums/UserRole.php` ✅

**Middleware:**
- `app/Http/Middleware/EnsureUserRole.php` ✅

**Responses:**
- `app/Http/Responses/LoginResponse.php` ✅
- `app/Http/Responses/RegisterResponse.php` ✅
- `app/Http/Responses/VerifyEmailResponse.php` ✅

**Migrations:**
- `database/migrations/2026_01_22_185333_add_role_to_users_table.php` ✅

**Seeders:**
- `database/seeders/UserSeeder.php` ✅

**Tests:**
- `tests/Feature/Middleware/EnsureUserRoleTest.php` ✅

### Files Modified (Phase 1 & 2)

- `.env` ✅
- `.env.example` ✅
- `bootstrap/app.php` ✅
- `routes/web.php` ✅
- `app/Models/User.php` ✅
- `app/Providers/FortifyServiceProvider.php` ✅
- `database/seeders/DatabaseSeeder.php` ✅
- `database/factories/UserFactory.php` ✅
- `resources/views/components/layouts/app/sidebar.blade.php` ✅
- `resources/views/components/settings/layout.blade.php` ✅
- All admin Livewire components (moved to `Admin/` namespace) ✅
- All admin views (moved to `admin/` directory) ✅
- `tests/Feature/Auth/RegistrationTest.php` ✅
- `tests/Feature/Auth/EmailVerificationTest.php` ✅

---

## Risk Mitigation

**Git Strategy:**
- Do not Commit after each completed step, stage all changes and ask for review on the staged files, then commit ✅
- Use descriptive commit messages ✅
- Feature branches for each phase ✅
- Merge to main after each phase completion ✅

**Branches Used:**
- `feature/agent-application` - Phase 1 & 2 (merged to main)
- `feature/agent-ui-prototype` - Phase 3 (current)

---

## Progress Log

| Date | Phase | Step | Status | Notes |
|------|-------|------|--------|-------|
| 2026-01-21 | - | - | Complete | Initial plan created and approved |
| 2026-01-21 | 1 | 1.1-1.5 | Complete | Domain config, UserRole enum, middleware, LoginResponse |
| 2026-01-21 | 2 | 2.1-2.3 | Complete | Routes restructured, components moved to Admin namespace |
| 2026-01-22 | 2 | 2.4 | Complete | Sidebar updated with role-based navigation |
| 2026-01-22 | 2 | 2.5 | Complete | All pages verified via Playwright browser testing |
| 2026-01-22 | 1 | - | Complete | Added RegisterResponse, VerifyEmailResponse, User::homeUrl() |
| 2026-01-22 | 2 | - | Complete | Fixed route naming consistency (admin.* prefix) |
| 2026-01-22 | - | - | Complete | All 505 tests passing |
| 2026-01-22 | - | - | Complete | Merged feature/agent-application to main |
| 2026-01-22 | 3 | - | Started | New branch: feature/agent-ui-prototype |
| 2026-01-22 | - | - | Changed | Restructured plan: UI-first approach before database models |

---

## Next Steps

**Immediate (Phase 3 - UI Prototype):**
1. Build agent property search UI with real property data
2. Implement search filters (location, price, type, features)
3. Build property detail view for agents
4. Create collection UI mockups (visual only, no database)
5. Iterate on UX based on how it feels to use

**After Phase 3:**
- Finalize collection data model based on UI learnings (Phase 4)
- Wire up collection UI to real database
- Build PDF generation (Phase 5)

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-21 | Initial plan created |
| 1.1 | 2026-01-21 | Plan approved, saved to project |
| 2.0 | 2026-01-22 | Phase 1 & 2 marked complete with implementation notes |
| 2.1 | 2026-01-22 | Documented design decisions that differed from original plan |
| 2.2 | 2026-01-22 | Restructured to UI-first approach - Phase 3 is now UI prototype |

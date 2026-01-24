# Roadmap: Agent Application

**Document:** `.claude/docs/ROADMAP_AGENT_APPLICATION.md`
**Related:** `.claude/docs/ARCHITECTURE_STATE.md` (current system architecture)
**Created:** 2026-01-21
**Status:** ✅ COMPLETE
**Last Updated:** 2026-01-23
**Next Phase:** `.claude/docs/ROADMAP_AGENT_SHARING.md`

---

## Executive Summary

Build the agent-facing portion of PropData where real estate agents can search properties, create collections, share via public links, and generate PDF spec sheets.

**Architecture Approach:** Subdomain routing to separate concerns
- `admin.propdata.test` → Internal admin (scraping, data management)
- `agents.propdata.test` → Agent application (search, collections, sharing)
- `propdata.test` → Public pages (shared collections, landing page)

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

## Phase 3: Agent Property Search & Collections UI ✅ COMPLETE

**Goal:** Build the agent property search and collection management UI.

**Change from Original Plan:** Originally this phase was "UI-First Approach" with mockups. Instead, we built fully functional features directly since the collection data model was straightforward.

**Completed:** 2026-01-23

### Step 3.1: Property Search UI ✅
- [x] Full property search component with real data (320 properties)
- [x] Implemented filters:
  - Operation type (Todas/Venta/Renta) - pill buttons
  - Zone autocomplete (grouped by city)
  - Property type (Todas/Casa/Depto/Terreno/Local) - pill buttons
  - Price quick picks ($2M, $2M-$4M, $4M-$8M, $8M-$15M, $15M+)
  - Bedrooms quick picks (1, 2, 3, 4+)
  - **Added:** Advanced filter modal with min/max price, bathrooms, parking, size range
- [x] Mobile-first responsive card grid
- [x] Pagination with Livewire
- [x] **Changed:** Layout uses minimal header instead of sidebar (cleaner agent experience)

**Files Created:**
- `app/Livewire/Agents/Properties/Index.php`
- `resources/views/livewire/agents/properties/index.blade.php`
- `resources/views/components/layouts/agent.blade.php` (minimal header layout)

### Step 3.2: Property Detail View ✅
- [x] Hero image with lightbox gallery (Alpine.js)
- [x] Price prominently displayed with operation type badge
- [x] Key stats (beds, baths, parking, size)
- [x] Full description
- [x] Amenities/features list
- [x] Location info with colonia/city
- [x] **Added:** Multiple listing sources shown (when property has multiple listings)
- [x] **Added:** "Add to collection" button integrated
- [x] Mobile-responsive design

**Files Created:**
- `app/Livewire/Agents/Properties/Show.php`
- `resources/views/livewire/agents/properties/show.blade.php`
- `resources/views/livewire/agents/properties/partials/` (gallery, features, description, listings)

### Step 3.3: Collection Panel (Inline) ✅
- [x] **Changed:** Instead of separate collections pages, built inline collection panel
- [x] Collection panel slides in from right (Flux modal flyout)
- [x] Shows selected properties with thumbnails
- [x] Inline name editing with save button
- [x] Property count display
- [x] Action bar with sharing options
- [x] "Add to collection" buttons on property cards (functional, not "coming soon")

**Design Decision:** The inline collection panel provides faster workflow than navigating to separate pages. Agents can build collections while browsing without losing context.

### Step 3.4: Collections Management Page ✅
- [x] List all user's collections with status badges
- [x] Filter by status (all/active/shared)
- [x] Shows property count, view count, last viewed date
- [x] Delete collection with confirmation modal
- [x] Click to view collection detail

**Files Created:**
- `app/Livewire/Agents/Collections/Index.php`
- `resources/views/livewire/agents/collections/index.blade.php`

### Step 3.5: Collection Detail View ✅
- [x] Editable collection name
- [x] Property grid with drag-to-reorder (wire:sort)
- [x] Remove individual properties
- [x] **Added:** Client management (select existing or create new)
- [x] Share via WhatsApp (opens wa.me with pre-filled message)
- [x] Copy share link
- [x] **Added:** PDF download
- [x] Status badge (active/shared)
- [x] View count and last viewed display

**Files Created:**
- `app/Livewire/Agents/Collections/Show.php`
- `resources/views/livewire/agents/collections/show.blade.php`

### Step 3.6: Navigation & Layout ✅
- [x] **Changed:** Agent app uses minimal header layout (not sidebar)
- [x] Header shows: logo, collection button with count, profile dropdown
- [x] Profile dropdown has: Mis Colecciones, Configuracion, Cerrar sesion
- [x] Collection panel accessible from any page via header button
- [x] Mobile hamburger menu works correctly

**Phase 3 Verification:** ✅
- [x] Agent can search and filter properties with real data
- [x] Property detail page shows all available information
- [x] Collections are fully functional (not just mockups)
- [x] Mobile responsive design works
- [x] 72 collection-related tests passing

---

## Phase 4: Collection Data Model ✅ COMPLETE

**Goal:** Create the database structure for agent collections.

**Note:** This was implemented alongside Phase 3, not after. The data model evolved as features were built.

**Completed:** 2026-01-23

### Step 4.1: Collections Table ✅
- [x] Create migration for `collections` table
- [x] Fields: `user_id`, `name`, `description`, `share_token` (auto-generated), `is_public`, `shared_at`, `expires_at`
- [x] **Added:** `client_id` foreign key for client association
- [x] **Added:** Legacy `client_name`, `client_whatsapp` fields (for backward compatibility)

**Files Created:**
- `database/migrations/2026_01_23_044441_create_collections_table.php`
- `database/migrations/2026_01_23_050139_add_client_fields_to_collections_table.php`
- `database/migrations/2026_01_23_203120_add_client_id_and_shared_at_to_collections_table.php`

### Step 4.2: Collection-Property Pivot Table ✅
- [x] Create migration for `collection_property` table
- [x] Fields: `collection_id`, `property_id`, `position` (for ordering)
- [x] Timestamps included

**Files Created:**
- `database/migrations/2026_01_23_044442_create_collection_property_table.php`

### Step 4.3: Client Model ✅
- [x] **Added:** Client model for reusable client contacts
- [x] Fields: `user_id`, `name`, `whatsapp`
- [x] Belongs to User, has many Collections

**Files Created:**
- `app/Models/Client.php`
- `database/factories/ClientFactory.php`
- `database/migrations/2026_01_23_203107_create_clients_table.php`

### Step 4.4: Collection View Tracking ✅
- [x] **Added:** CollectionView model for analytics
- [x] Fields: `collection_id`, `ip_address`, `user_agent`, `viewed_at`
- [x] Tracks one view per IP per day (avoids spam)
- [x] Accessors: `view_count`, `last_viewed_at` on Collection model

**Files Created:**
- `app/Models/CollectionView.php`
- `database/migrations/2026_01_24_000218_create_collection_views_table.php`

### Step 4.5: Collection Model ✅
- [x] Create `Collection` model with relationships
- [x] Auto-generates `share_token` on creation
- [x] Relationships: belongsTo User, belongsTo Client, belongsToMany Properties, hasMany Views
- [x] **Simplified:** Status from 4 states (draft/active/ready/shared) to 2 (active/shared)
- [x] `markAsShared()` method sets `shared_at` and `is_public` idempotently
- [x] `isAccessible()` checks public and not expired
- [x] `getShareUrl()`, `getWhatsAppShareUrl()` helpers
- [x] Create `CollectionFactory` for testing

**Files Created:**
- `app/Models/Collection.php`
- `database/factories/CollectionFactory.php`

### Step 4.6: Public Collection View ✅
- [x] Public route at `/c/{share_token}`
- [x] Shows collection properties without auth
- [x] Tracks views automatically
- [x] Returns 404 for private or expired collections

**Files Created:**
- `app/Livewire/Public/Collections/Show.php`
- `resources/views/livewire/public/collections/show.blade.php`
- `resources/views/components/layouts/public.blade.php`

**Phase 4 Verification:** ✅
- [x] Collections CRUD works completely
- [x] All UI features backed by real data
- [x] 72 collection tests pass

---

## Phase 5: PDF Generation ✅ COMPLETE

**Goal:** Generate professional property spec sheets for collections.

**Change from Original Plan:** Used `barryvdh/laravel-dompdf` instead of `spatie/laravel-pdf` (simpler, no Chromium dependency).

**Completed:** 2026-01-23

### Step 5.1: Install PDF Package ✅
- [x] Run `composer require barryvdh/laravel-dompdf`
- [x] No additional dependencies required (pure PHP)

### Step 5.2: PDF Template ✅
- [x] Create collection PDF Blade template
- [x] Shows: collection name, agent info, property grid
- [x] Each property: image, price, location, key features
- [x] Clean professional layout

**Files Created:**
- `resources/views/pdf/collection.blade.php`

### Step 5.3: PDF Download Action ✅
- [x] Add `downloadPdf()` method to Collections/Show component
- [x] Returns StreamedResponse with PDF
- [x] Filename: `{collection-name-slugified}.pdf`

### Step 5.4: Wire Up UI Button ✅
- [x] PDF download button in collection detail header
- [x] Uses Flux button with document-arrow-down icon

### Step 5.5: PDF Tests ✅
- [x] Test PDF generation returns 200
- [x] Test correct content-type header

**Phase 5 Verification:** ✅
- [x] PDF downloads correctly from collection detail
- [x] PDF contains collection properties
- [x] Test passes

---

## Phase 6: Polish & Documentation ✅ DEFERRED

**Goal:** Final cleanup and documentation.

**Status:** Deferred to ongoing maintenance. Core functionality is complete and tested. Documentation will be created as needed.

### Completed:
- [x] All tests pass (577 tests)
- [x] Code formatted with Pint
- [x] Code review fixes applied (N+1, DRY, accessibility)

### Deferred:
- [ ] `docs/LOCAL_DEVELOPMENT.md` - Create when onboarding new developers
- [ ] Pest v4 browser tests - Add for critical flows as needed

---

## Implementation Notes

### Design Decisions Made During Implementation

1. **Minimal Header Layout for Agents**: Instead of using the sidebar layout like admin, agents get a cleaner minimal header with just logo, collection button, and profile dropdown. This provides more screen space for browsing properties.

2. **Inline Collection Panel**: Rather than navigating to separate collection pages while browsing, the collection panel slides in as a flyout modal. This lets agents build collections without losing their search context.

3. **Simplified Collection Status**: Originally planned 4 states (draft → active → ready → shared). Simplified to 2 states:
   - `active` - Collection exists but hasn't been shared yet
   - `shared` - Collection has been shared (shared_at is set)
   The `markAsShared()` method handles the transition idempotently.

4. **View Tracking**: Added analytics not in original plan. Tracks unique views per IP per day to show agents how their shared collections are performing.

5. **Client Management**: Added Client model for reusable contacts. Agents can select existing clients or create new ones when sharing collections.

6. **barryvdh/laravel-dompdf over spatie/laravel-pdf**: Chose dompdf for simplicity - no Chromium dependency, works out of the box.

7. **Session-Based Active Collection**: The "current collection" being edited persists in session. This allows agents to add properties from detail pages and return to the same collection.

8. **Public Landing Page**: Added a marketing landing page at the root domain with dark mode support and animated background effects.

### Files Created (All Phases)

**Configuration:**
- `config/domains.php` ✅

**Enums:**
- `app/Enums/UserRole.php` ✅

**Models:**
- `app/Models/Collection.php` ✅
- `app/Models/CollectionView.php` ✅
- `app/Models/Client.php` ✅

**Middleware:**
- `app/Http/Middleware/EnsureUserRole.php` ✅

**Responses:**
- `app/Http/Responses/LoginResponse.php` ✅
- `app/Http/Responses/RegisterResponse.php` ✅
- `app/Http/Responses/VerifyEmailResponse.php` ✅

**Livewire Components:**
- `app/Livewire/Agents/Properties/Index.php` ✅
- `app/Livewire/Agents/Properties/Show.php` ✅
- `app/Livewire/Agents/Collections/Index.php` ✅
- `app/Livewire/Agents/Collections/Show.php` ✅
- `app/Livewire/Public/Collections/Show.php` ✅
- `app/Livewire/Landing.php` ✅

**Layouts:**
- `resources/views/components/layouts/agent.blade.php` ✅
- `resources/views/components/layouts/public.blade.php` ✅
- `resources/views/components/layouts/landing.blade.php` ✅

**Views:**
- `resources/views/livewire/agents/properties/index.blade.php` ✅
- `resources/views/livewire/agents/properties/show.blade.php` ✅
- `resources/views/livewire/agents/properties/partials/*.blade.php` ✅
- `resources/views/livewire/agents/collections/index.blade.php` ✅
- `resources/views/livewire/agents/collections/show.blade.php` ✅
- `resources/views/livewire/public/collections/show.blade.php` ✅
- `resources/views/livewire/landing.blade.php` ✅
- `resources/views/pdf/collection.blade.php` ✅

**Migrations:**
- `database/migrations/2026_01_22_185333_add_role_to_users_table.php` ✅
- `database/migrations/2026_01_23_044441_create_collections_table.php` ✅
- `database/migrations/2026_01_23_044442_create_collection_property_table.php` ✅
- `database/migrations/2026_01_23_050139_add_client_fields_to_collections_table.php` ✅
- `database/migrations/2026_01_23_203107_create_clients_table.php` ✅
- `database/migrations/2026_01_23_203120_add_client_id_and_shared_at_to_collections_table.php` ✅
- `database/migrations/2026_01_24_000218_create_collection_views_table.php` ✅

**Factories:**
- `database/factories/CollectionFactory.php` ✅
- `database/factories/ClientFactory.php` ✅

**Seeders:**
- `database/seeders/UserSeeder.php` ✅

**Tests:**
- `tests/Feature/Middleware/EnsureUserRoleTest.php` ✅
- `tests/Feature/Agents/CollectionsTest.php` ✅

**Traits:**
- `app/Livewire/Concerns/ShowsWhatsAppTip.php` ✅

---

## Progress Log

| Date | Phase | Status | Notes |
|------|-------|--------|-------|
| 2026-01-21 | 1 | Complete | Domain config, UserRole enum, middleware, auth responses |
| 2026-01-21 | 2 | Complete | Routes restructured, components moved to Admin namespace |
| 2026-01-22 | 2 | Complete | Sidebar updated, all pages verified via Playwright |
| 2026-01-22 | 2 | Complete | Merged feature/agent-application to main |
| 2026-01-22 | 3 | Started | Property search UI with filters |
| 2026-01-22 | 3 | Complete | Property detail view with gallery |
| 2026-01-22 | 4 | Complete | Collection data model created |
| 2026-01-23 | 3 | Complete | Collection panel, management pages |
| 2026-01-23 | 4 | Complete | Client model, view tracking |
| 2026-01-23 | 5 | Complete | PDF export with dompdf |
| 2026-01-23 | - | Complete | Landing page with dark mode |
| 2026-01-23 | - | Complete | Collection panel UI redesign |
| 2026-01-23 | - | Complete | Code review fixes (N+1, DRY, reduced motion) |

---

## Completion Summary (2026-01-23)

This roadmap is now **COMPLETE**. The agent application core functionality has been fully implemented and tested.

**Delivered Features:**
- ✅ Agent property search with filters
- ✅ Property detail view with gallery
- ✅ Collection management (create, edit, delete)
- ✅ Add/remove properties from collections
- ✅ Share via WhatsApp
- ✅ Copy share link
- ✅ Public collection view
- ✅ View tracking analytics
- ✅ PDF export
- ✅ Client management
- ✅ Landing page with dark mode

**Test Coverage:**
- 577 total tests passing
- 72 collection-specific tests

**Future Work:**
Continued development of agent features is tracked in the next roadmap:
→ `.claude/docs/ROADMAP_AGENT_SHARING.md` - Agent sharing tools and preferences

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-21 | Initial plan created |
| 1.1 | 2026-01-21 | Plan approved, saved to project |
| 2.0 | 2026-01-22 | Phase 1 & 2 marked complete with implementation notes |
| 2.1 | 2026-01-22 | Documented design decisions that differed from original plan |
| 2.2 | 2026-01-22 | Restructured to UI-first approach - Phase 3 is now UI prototype |
| 3.0 | 2026-01-23 | Phase 3, 4, 5 marked complete. Documented actual implementation vs plan. |
| 4.0 | 2026-01-23 | Roadmap marked COMPLETE. Future work moves to ROADMAP_AGENT_SHARING.md |

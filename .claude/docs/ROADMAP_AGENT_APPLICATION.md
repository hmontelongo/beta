# Roadmap: Agent Application

**Document:** `.claude/docs/ROADMAP_AGENT_APPLICATION.md`
**Related:** `.claude/docs/ARCHITECTURE_STATE.md` (current system architecture)
**Created:** 2026-01-21
**Status:** Approved
**Last Updated:** 2026-01-21

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

## Phase 1: Subdomain Infrastructure

**Goal:** Create the foundation for subdomain-based routing without breaking anything.

**Laravel Features Used:**
- `Route::domain()` - Groups routes by subdomain
- Custom middleware - Role-based access control
- Custom LoginResponse - Redirect users to correct subdomain after auth

### Step 1.1: Domain Configuration
- [ ] Create `config/domains.php`
  ```php
  return [
      'admin' => env('ADMIN_DOMAIN', 'admin.propdata.test'),
      'agents' => env('AGENTS_DOMAIN', 'agents.propdata.test'),
      'public' => env('PUBLIC_DOMAIN', 'propdata.test'),
  ];
  ```
- [ ] Update `.env` with domain values
- [ ] Update `.env.example` with domain values

**Files:**
- `config/domains.php` (new)
- `.env` (modify)
- `.env.example` (modify)

### Step 1.2: User Role System
- [ ] Create `UserRole` enum with `Admin` and `Agent` cases
- [ ] Create migration to add `role` column to users table (default: 'agent')
- [ ] Update `User` model with role cast and helper methods (`isAdmin()`, `isAgent()`)
- [ ] Run migration

**Files:**
- `app/Enums/UserRole.php` (new)
- `database/migrations/xxxx_add_role_to_users_table.php` (new)
- `app/Models/User.php` (modify)

### Step 1.3: Role Middleware
- [ ] Create `EnsureUserRole` middleware
  - Accepts role parameter (e.g., `role:admin`)
  - Checks authenticated user's role
  - Redirects to appropriate subdomain if unauthorized
  - Handles unauthenticated users gracefully
- [ ] Register middleware alias in `bootstrap/app.php`

**Files:**
- `app/Http/Middleware/EnsureUserRole.php` (new)
- `bootstrap/app.php` (modify)

### Step 1.4: Login Response Customization
- [ ] Create custom `LoginResponse` class
  - Check user role after successful login
  - Redirect admin → `admin.{domain}/platforms`
  - Redirect agent → `agents.{domain}/properties`
- [ ] Register in `FortifyServiceProvider`

**Files:**
- `app/Http/Responses/LoginResponse.php` (new)
- `app/Providers/FortifyServiceProvider.php` (modify)

### Step 1.5: User Seeder
- [ ] Create `UserSeeder` with:
  - Admin user (role: admin)
  - 2-3 sample agent users (role: agent)
- [ ] Register in `DatabaseSeeder`

**Files:**
- `database/seeders/UserSeeder.php` (new)
- `database/seeders/DatabaseSeeder.php` (modify)

**Phase 1 Verification:**
- [ ] `php artisan migrate` runs successfully
- [ ] `php artisan db:seed --class=UserSeeder` creates users
- [ ] Config values accessible via `config('domains.admin')` etc.
- [ ] Middleware registered (check `php artisan route:list`)

---

## Phase 2: Migrate Existing Functionality to Admin Subdomain

**Goal:** Move all current functionality to `admin.propdata.test` and verify it works identically.

**Critical Checkpoint:** We do NOT proceed to Phase 3 until everything works. If stuck, we revert.

### Step 2.1: Route Restructuring
- [ ] Wrap all existing authenticated routes in `Route::domain(config('domains.admin'))`
- [ ] Add `role.admin` middleware to admin route group
- [ ] Keep auth routes (login, register, etc.) accessible on all subdomains OR create per-subdomain auth
- [ ] Update route names with `admin.` prefix where needed

**Files:**
- `routes/web.php` (major refactor)

### Step 2.2: Move Livewire Components to Admin Namespace
- [ ] Create `app/Livewire/Admin/` directory
- [ ] Move `Dashboard.php` → `Admin/Dashboard.php`
- [ ] Move `Listings/` → `Admin/Listings/`
- [ ] Move `Properties/` → `Admin/Properties/`
- [ ] Move `Platforms/` → `Admin/Platforms/`
- [ ] Move `Publishers/` → `Admin/Publishers/`
- [ ] Move `ScrapeRuns/` → `Admin/ScrapeRuns/`
- [ ] Move `Dedup/` → `Admin/Dedup/`
- [ ] Update namespace declarations in all moved files
- [ ] Update `#[Layout(...)]` attributes if needed

**Files:**
- All files in `app/Livewire/` (move ~15 files)

### Step 2.3: Update Views
- [ ] Move Livewire views to match new structure
  - `resources/views/livewire/` → `resources/views/livewire/admin/`
- [ ] Update any hardcoded component references
- [ ] Ensure layout still works for admin context

**Files:**
- All files in `resources/views/livewire/` (move)

### Step 2.4: Layout Updates
- [ ] Update sidebar to work with admin subdomain context
- [ ] Ensure navigation links use correct routes
- [ ] Test responsive behavior

**Files:**
- `resources/views/components/layouts/app/sidebar.blade.php` (modify)

### Step 2.5: Verification Testing
Test every page on `admin.propdata.test`:

- [ ] **Auth flows:**
  - [ ] Login page loads
  - [ ] Login works, redirects to `/platforms`
  - [ ] Logout works
  - [ ] Register page loads (if enabled)
  - [ ] Password reset flow works

- [ ] **Dashboard:**
  - [ ] Dashboard loads at `/dashboard`
  - [ ] Stats display correctly
  - [ ] No console errors

- [ ] **Platforms:**
  - [ ] List page loads
  - [ ] Detail page loads
  - [ ] Start scrape action works
  - [ ] Search queries display

- [ ] **Listings:**
  - [ ] List page loads with filters
  - [ ] Pagination works
  - [ ] Detail page loads
  - [ ] Images display

- [ ] **Properties:**
  - [ ] List page loads with filters
  - [ ] Sorting works
  - [ ] Detail page loads
  - [ ] All tabs/sections work

- [ ] **Publishers:**
  - [ ] List page loads
  - [ ] Detail page loads

- [ ] **Dedup Review:**
  - [ ] Review page loads
  - [ ] Approve/reject actions work

- [ ] **Settings:**
  - [ ] Profile settings load
  - [ ] Password change works
  - [ ] Appearance settings work

- [ ] **Scrape Runs:**
  - [ ] Run detail page loads
  - [ ] Real-time updates work

**Phase 2 Exit Criteria:**
- [ ] ALL checkboxes above are checked
- [ ] No functionality regression from before subdomain migration
- [ ] Existing tests pass: `php artisan test`
- [ ] Code formatted: `vendor/bin/pint --dirty`

**If Phase 2 fails:** Document what broke, attempt fix. If unrecoverable, `git checkout` to revert and reassess approach.

---

## Phase 3: Collection Data Model

**Goal:** Create the database structure for agent collections.

**Prerequisite:** Phase 2 complete and verified.

### Step 3.1: Collections Table
- [ ] Create migration for `collections` table:
  - `id`, `user_id`, `name`, `public_id` (UUID), `is_public`
  - `client_name`, `client_phone`, `client_email`, `notes` (optional CRM fields)
  - `timestamps`
  - Index on `[user_id, created_at]`
- [ ] Run migration

**Files:**
- `database/migrations/xxxx_create_collections_table.php` (new)

### Step 3.2: Collection-Property Pivot Table
- [ ] Create migration for `collection_property` table:
  - `id`, `collection_id`, `property_id`
  - `agent_notes` (why this property for this client)
  - `sort_order` (for drag-to-reorder)
  - `timestamps`
  - Unique constraint on `[collection_id, property_id]`
- [ ] Run migration

**Files:**
- `database/migrations/xxxx_create_collection_property_table.php` (new)

### Step 3.3: Collection Model
- [ ] Create `Collection` model with:
  - Fillable fields
  - `is_public` cast to boolean
  - Auto-generate `public_id` UUID on creation
  - `user()` relationship (belongsTo)
  - `properties()` relationship (belongsToMany with pivot)
  - `getPublicUrlAttribute()` accessor
- [ ] Create `CollectionFactory` for testing

**Files:**
- `app/Models/Collection.php` (new)
- `database/factories/CollectionFactory.php` (new)

### Step 3.4: Update Related Models
- [ ] Add `collections()` relationship to `User` model

**Files:**
- `app/Models/User.php` (modify)

### Step 3.5: Collection Model Tests
- [ ] Write unit tests for Collection model:
  - UUID auto-generation
  - User relationship
  - Properties relationship with pivot data
  - Public URL generation

**Files:**
- `tests/Unit/Models/CollectionTest.php` (new)

**Phase 3 Verification:**
- [ ] Migrations run without error
- [ ] `Collection::factory()->create()` works in tinker
- [ ] Relationships work correctly
- [ ] Unit tests pass

---

## Phase 4: Agent UI Components

**Goal:** Build the agent-facing application at `agents.propdata.test`.

**Prerequisite:** Phase 3 complete.

### Step 4.1: Agent Route Structure
- [ ] Create agent subdomain route group in `routes/web.php`
- [ ] Add placeholder routes:
  - `GET /` → redirect to `/properties`
  - `GET /properties` → Property search
  - `GET /properties/{property}` → Property detail
  - `GET /collections` → Collection list
  - `GET /collections/{collection}` → Collection detail
  - `POST /collections` → Create collection (Livewire action)
- [ ] Add `role.agent` middleware

**Files:**
- `routes/web.php` (modify)

### Step 4.2: Agent Layout
- [ ] Create agent-specific sidebar with simpler navigation:
  - Properties (search)
  - Collections
  - Settings
- [ ] Update main layout to detect subdomain and show appropriate sidebar
- [ ] Ensure consistent styling with admin (same Flux UI patterns)

**Files:**
- `resources/views/components/layouts/app/agent-sidebar.blade.php` (new)
- `resources/views/components/layouts/app.blade.php` (modify)

### Step 4.3: Property Search Component
- [ ] Create `app/Livewire/Agents/Properties/Search.php`
- [ ] Implement filters:
  - Location (state, city, colonia)
  - Price range (min/max)
  - Operation type (rent/sale)
  - Property type
  - Bedrooms, bathrooms, parking
  - Size range
  - Amenities
- [ ] Display results as card grid (mobile-first)
- [ ] Each card shows: image, price, location, key features
- [ ] "Add to Collection" button on each card
- [ ] Quick collection selector dropdown
- [ ] Pagination

**Files:**
- `app/Livewire/Agents/Properties/Search.php` (new)
- `resources/views/livewire/agents/properties/search.blade.php` (new)

### Step 4.4: Property Detail Component
- [ ] Create `app/Livewire/Agents/Properties/Show.php`
- [ ] Display:
  - Hero image with gallery (lightbox)
  - Price prominently displayed
  - Key stats (beds, baths, parking, size)
  - Full description
  - Amenities list
  - Location with map (if coordinates available)
  - Publisher info
- [ ] Actions:
  - "Add to Collection" button
  - "Download PDF" button (wired up in Phase 5)

**Files:**
- `app/Livewire/Agents/Properties/Show.php` (new)
- `resources/views/livewire/agents/properties/show.blade.php` (new)

### Step 4.5: Collections Index Component
- [ ] Create `app/Livewire/Agents/Collections/Index.php`
- [ ] Display grid of agent's collections
- [ ] Each card shows: name, property count, client name (if set), created date
- [ ] Actions: view, share toggle, delete
- [ ] "Create Collection" button → opens modal

**Files:**
- `app/Livewire/Agents/Collections/Index.php` (new)
- `resources/views/livewire/agents/collections/index.blade.php` (new)

### Step 4.6: Collection Detail/Management Component
- [ ] Create `app/Livewire/Agents/Collections/Show.php`
- [ ] Editable fields: name, client info, notes
- [ ] Property list with:
  - Drag-to-reorder (sort_order)
  - Per-property agent notes
  - Remove from collection action
- [ ] Share controls:
  - Toggle public/private
  - Copy public link button
- [ ] "Download All PDFs" button (wired up in Phase 5)

**Files:**
- `app/Livewire/Agents/Collections/Show.php` (new)
- `resources/views/livewire/agents/collections/show.blade.php` (new)

### Step 4.7: Create Collection Modal
- [ ] Create `app/Livewire/Agents/Collections/Create.php`
- [ ] Form fields: name (required), client name/phone/email (optional), notes
- [ ] Save action creates collection and redirects to it

**Files:**
- `app/Livewire/Agents/Collections/Create.php` (new)
- `resources/views/livewire/agents/collections/create.blade.php` (new)

### Step 4.8: Public Collection View
- [ ] Create `app/Livewire/Agents/Collections/PublicView.php`
- [ ] Route: `GET /c/{collection:public_id}` on public subdomain
- [ ] No authentication required
- [ ] Display: collection name, property cards (read-only)
- [ ] Platform branding
- [ ] Optional CTA: "Find an agent" or "Sign up"

**Files:**
- `app/Livewire/Agents/Collections/PublicView.php` (new)
- `resources/views/livewire/agents/collections/public-view.blade.php` (new)

### Step 4.9: Feature Tests
- [ ] Test property search filters
- [ ] Test collection CRUD operations
- [ ] Test add/remove properties from collection
- [ ] Test public collection access
- [ ] Test role-based access (agent can't access admin routes)

**Files:**
- `tests/Feature/Agents/PropertySearchTest.php` (new)
- `tests/Feature/Agents/CollectionsTest.php` (new)
- `tests/Feature/Agents/PublicCollectionTest.php` (new)

**Phase 4 Verification:**
- [ ] Agent can log in and land on property search
- [ ] Property search returns results with working filters
- [ ] Property detail shows all information
- [ ] Collections CRUD works completely
- [ ] Public links work without authentication
- [ ] All new tests pass

---

## Phase 5: PDF Generation

**Goal:** Generate professional property spec sheets.

**Prerequisite:** Phase 4 complete.

### Step 5.1: Install PDF Package
- [ ] Run `composer require spatie/laravel-pdf`
- [ ] Verify Chromium/Puppeteer dependencies (may need `npm install puppeteer`)

**Files:**
- `composer.json` (modify)
- `composer.lock` (modify)

### Step 5.2: PDF Template
- [ ] Create property spec sheet Blade template
- [ ] Layout:
  ```
  ┌─────────────────────────────────────┐
  │ [Hero Image - full width]           │
  ├─────────────────────────────────────┤
  │ $65,000/mes                    RENT │
  │ Casa en Valle Real, Zapopan         │
  ├─────────────────────────────────────┤
  │ 5 Beds │ 4 Baths │ 6 Parking │450m² │
  ├─────────────────────────────────────┤
  │ Description (first 500 chars)...    │
  ├─────────────────────────────────────┤
  │ Amenities: Pool, Gym, Security...   │
  ├─────────────────────────────────────┤
  │ Location: Calle..., Colonia..., ... │
  ├─────────────────────────────────────┤
  │ [Image Grid - 4-6 smaller images]   │
  ├─────────────────────────────────────┤
  │ Agent: Name │ Phone │ Platform Logo │
  └─────────────────────────────────────┘
  ```
- [ ] Use inline Tailwind CSS (PDF generator compiles it)

**Files:**
- `resources/views/pdf/property-spec-sheet.blade.php` (new)

### Step 5.3: PDF Controller
- [ ] Create `PropertyPdfController`
- [ ] `single(Property $property)` - Generate single property PDF
- [ ] `collection(Collection $collection)` - Generate multi-page PDF
- [ ] Helper method to extract images from property listings

**Files:**
- `app/Http/Controllers/PropertyPdfController.php` (new)

### Step 5.4: PDF Routes
- [ ] Add routes for PDF generation:
  - `GET /pdf/property/{property}` → single PDF
  - `GET /pdf/collection/{collection}` → collection PDF
- [ ] Add to both admin and agents subdomain (or shared)

**Files:**
- `routes/web.php` (modify)

### Step 5.5: Wire Up UI Buttons
- [ ] Connect "Download PDF" button in property detail
- [ ] Connect "Download All PDFs" button in collection detail
- [ ] Add loading states during PDF generation

**Files:**
- `app/Livewire/Agents/Properties/Show.php` (modify)
- `app/Livewire/Agents/Collections/Show.php` (modify)

### Step 5.6: PDF Tests
- [ ] Test single property PDF generation
- [ ] Test collection PDF generation
- [ ] Test PDF contains expected content

**Files:**
- `tests/Feature/Pdf/PropertyPdfTest.php` (new)

**Phase 5 Verification:**
- [ ] Single property PDF downloads correctly
- [ ] PDF layout looks professional
- [ ] Collection PDF contains all properties
- [ ] Tests pass

---

## Phase 6: Polish & Documentation

**Goal:** Final cleanup and documentation.

### Step 6.1: Local Development Documentation
- [ ] Create `docs/LOCAL_DEVELOPMENT.md`
- [ ] Document Herd subdomain setup
- [ ] Document seeder usage
- [ ] Document testing approach

**Files:**
- `docs/LOCAL_DEVELOPMENT.md` (new)

### Step 6.2: Run Full Test Suite
- [ ] Run `php artisan test` - all tests pass
- [ ] Run `vendor/bin/pint` - code formatted
- [ ] Manual smoke test of all features

### Step 6.3: Browser Tests (Optional)
- [ ] Write Pest v4 browser tests for critical flows:
  - Agent login → search → add to collection → share
  - Public collection viewing

**Files:**
- `tests/Browser/AgentWorkflowTest.php` (new, optional)

**Phase 6 Verification:**
- [ ] All tests pass
- [ ] Documentation complete
- [ ] No console errors in browser
- [ ] Mobile responsive

---

## File Summary

### New Files (~35 files)

**Configuration:**
- `config/domains.php`

**Enums:**
- `app/Enums/UserRole.php`

**Middleware:**
- `app/Http/Middleware/EnsureUserRole.php`

**Responses:**
- `app/Http/Responses/LoginResponse.php`

**Controllers:**
- `app/Http/Controllers/PropertyPdfController.php`

**Models:**
- `app/Models/Collection.php`

**Migrations:**
- `database/migrations/xxxx_add_role_to_users_table.php`
- `database/migrations/xxxx_create_collections_table.php`
- `database/migrations/xxxx_create_collection_property_table.php`

**Factories:**
- `database/factories/CollectionFactory.php`

**Seeders:**
- `database/seeders/UserSeeder.php`

**Livewire Components (Agents):**
- `app/Livewire/Agents/Properties/Search.php`
- `app/Livewire/Agents/Properties/Show.php`
- `app/Livewire/Agents/Collections/Index.php`
- `app/Livewire/Agents/Collections/Show.php`
- `app/Livewire/Agents/Collections/Create.php`
- `app/Livewire/Agents/Collections/PublicView.php`

**Views (Agents):**
- `resources/views/livewire/agents/properties/search.blade.php`
- `resources/views/livewire/agents/properties/show.blade.php`
- `resources/views/livewire/agents/collections/index.blade.php`
- `resources/views/livewire/agents/collections/show.blade.php`
- `resources/views/livewire/agents/collections/create.blade.php`
- `resources/views/livewire/agents/collections/public-view.blade.php`

**Layouts:**
- `resources/views/components/layouts/app/agent-sidebar.blade.php`

**PDF:**
- `resources/views/pdf/property-spec-sheet.blade.php`

**Tests:**
- `tests/Unit/Models/CollectionTest.php`
- `tests/Unit/Enums/UserRoleTest.php`
- `tests/Feature/Auth/RoleBasedRedirectTest.php`
- `tests/Feature/Middleware/EnsureUserRoleTest.php`
- `tests/Feature/Agents/PropertySearchTest.php`
- `tests/Feature/Agents/CollectionsTest.php`
- `tests/Feature/Agents/PublicCollectionTest.php`
- `tests/Feature/Pdf/PropertyPdfTest.php`

**Documentation:**
- `docs/LOCAL_DEVELOPMENT.md`

### Modified Files (~20 files)

- `.env`
- `.env.example`
- `bootstrap/app.php`
- `routes/web.php`
- `app/Models/User.php`
- `app/Providers/FortifyServiceProvider.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/components/layouts/app/sidebar.blade.php`
- All existing Livewire components (move to Admin/ namespace, ~10 files)

---

## Risk Mitigation

**Git Strategy:**
- Commit after each completed step
- Use descriptive commit messages
- Create feature branch: `feature/agent-application`
- If Phase 2 fails badly, `git checkout main` to revert

**Rollback Points:**
- After Phase 1: Infrastructure in place, no breaking changes yet
- After Phase 2: **Critical checkpoint** - existing functionality verified
- After Phase 3: Data model exists, can proceed or pause
- After Phase 4: Agent UI complete, PDF can be deferred
- After Phase 5: Full feature complete

---

## Progress Log

_Use this section to track progress and notes during implementation._

| Date | Phase | Step | Status | Notes |
|------|-------|------|--------|-------|
| 2026-01-22 | 1 | All | Complete | All Phase 1 steps implemented and tested |

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-21 | Initial plan created |
| 1.1 | 2026-01-21 | Plan approved, saved to project |

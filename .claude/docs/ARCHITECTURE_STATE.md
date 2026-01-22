# PropData Architecture State

Real estate data aggregation platform for the Mexican market.

## Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Livewire 4, Flux UI Pro v2, Tailwind CSS v4
- **Database**: MySQL
- **Auth**: Laravel Fortify (with 2FA support)
- **Testing**: Pest v4
- **External APIs**: ZenRows (scraping), Google Geocoding, Claude AI (Anthropic)

## Data Pipeline Flow

```
Scrape Trigger (UI or scheduled)
    │
    ▼
ScrapeOrchestrator.startRun()
    │
    ├── DiscoverSearchJob (queue: discovery)
    │       └── DiscoverPageJob (per page)
    │               └── Creates DiscoveredListing records
    │
    └── ScrapeListingJob (queue: scraping)
            └── Creates Listing records
                    │
                    ▼
            ┌───────────────────────────────┐
            │   Scheduled Pipeline Jobs     │
            │   (every minute via console)  │
            └───────────────────────────────┘
                    │
    ┌───────────────┼───────────────┐
    ▼               ▼               ▼
ProcessGeocoding  ProcessDedup  ProcessPropertyCreation
BatchJob          BatchJob      BatchJob
    │               │               │
    ▼               ▼               ▼
GeocodeListingJob DeduplicationService CreatePropertyFromListingsJob
(Google API)      CandidateMatcherService (Claude AI)
    │               │               │
    │               ▼               ▼
    │         ListingGroup      Property
    │         (grouping)        (canonical record)
    └───────────────────────────────┘
```

## Models & Relationships

### Platform
Source website (inmuebles24, vivanuncios, mercadolibre, easybroker)
- `hasMany` SearchQuery, Listing, ScrapeRun, DiscoveredListing, ScrapeJob

### SearchQuery
Saved search URL for a platform with scheduling configuration
- `belongsTo` Platform
- `hasMany` ScrapeRun
- `hasOne` latestRun, activeRun

### ScrapeRun
Single execution of a search query scrape
- `belongsTo` Platform, SearchQuery
- `hasMany` ScrapeJob, DiscoveredListing
- Status: Pending → Discovering → Scraping → Completed/Failed/Stopped

### DiscoveredListing
URL found during discovery phase (intermediate record)
- `belongsTo` Platform, ScrapeRun
- `hasOne` Listing
- Status: pending → queued → scraped/failed

### Listing
Scraped listing data, core entity in pipeline
- `belongsTo` Platform, Property (nullable), ListingGroup (nullable), Publisher (nullable), DiscoveredListing
- `hasMany` ListingImage, ListingPhone
- `dedup_status`: Pending → Processing → Grouped → Completed/Failed
- `geocode_status`: null → success/failed

### ListingGroup
Deduplication grouping - links related listings before property creation
- `belongsTo` Property (nullable)
- `hasMany` Listing
- Status: PendingReview → PendingAi → ProcessingAi → Completed/Rejected

### Property
Canonical unified record representing a physical property
- `hasMany` Listing, ListingGroup, PropertyConflict, PropertyVerification
- `belongsToMany` Publisher
- Contains AI-unified data from multiple listings

### Publisher
Agent or agency that posted listings
- `hasMany` Listing
- `belongsToMany` Property
- `belongsTo` parent (self-referential for agency→agent)
- Type: Unknown, Individual, Agency, Developer

### DedupCandidate
Potential match between two listings for deduplication
- `belongsTo` listingA, listingB (both Listing)
- Stores matching scores: distance, coordinate, address, features, overall

### ApiUsageLog
Tracks API usage and costs for Claude and ZenRows
- Service: Claude, ZenRows
- Tracks tokens, credits, cost in cents

## Services

| Service | Purpose |
|---------|---------|
| `ScrapeOrchestrator` | Manages scrape runs, dispatches jobs, tracks stats |
| `ScraperService` | HTTP client to call ZenRows API |
| `ZenRowsClient` | ZenRows API wrapper with CSS extraction |
| `DeduplicationService` | Processes listings for deduplication |
| `CandidateMatcherService` | Finds potential duplicate matches |
| `PropertyCreationService` | Creates properties from listing groups via Claude AI |
| `PublisherExtractionService` | Extracts/links publishers from listing data |
| `GeocodingService` | Google Geocoding API wrapper (forward + reverse geocode fallback) |
| `ClaudeClient` | Anthropic Claude API client |
| `ApiUsageTracker` | Tracks API usage and costs |
| `JobCancellationService` | Handles job cancellation/cleanup |

## Jobs

| Job | Queue | Purpose |
|-----|-------|---------|
| `DiscoverSearchJob` | discovery | Initiates search page discovery |
| `DiscoverPageJob` | discovery | Scrapes one search results page |
| `ScrapeListingJob` | scraping | Scrapes individual listing |
| `ProcessGeocodingBatchJob` | geocoding | Dispatches geocoding for pending listings |
| `GeocodeListingJob` | geocoding | Geocodes single listing |
| `ProcessDeduplicationBatchJob` | dedup | Processes pending dedup listings |
| `DeduplicateListingJob` | dedup | Deduplicates single listing |
| `ProcessPropertyCreationBatchJob` | property-creation | Dispatches property creation for ready groups |
| `CreatePropertyFromListingsJob` | property-creation | Creates property via AI from listing group |
| `RescrapeListingJob` | scraping | Re-scrapes existing listing |

## Scheduled Tasks (routes/console.php)

| Schedule | Command/Job |
|----------|-------------|
| Every 5 min | `listings:reset-stale` - Reset stuck processing jobs |
| Every 5 min | `scrape:run-scheduled` - Run due scheduled scrapes |
| Every minute | `ProcessGeocodingBatchJob` |
| Every minute | `ProcessDeduplicationBatchJob` |
| Every minute | `ProcessPropertyCreationBatchJob` |

## Key Enums

| Enum | Values |
|------|--------|
| `UserRole` | Admin, Agent |
| `DedupStatus` | Pending, Processing, Grouped, Completed, Failed |
| `ListingGroupStatus` | PendingReview, PendingAi, ProcessingAi, Completed, Rejected |
| `ScrapeRunStatus` | Pending, Discovering, Scraping, Completed, Failed, Stopped |
| `ListingPipelineStatus` | AwaitingGeocoding, AwaitingDedup, ProcessingDedup, NeedsReview, QueuedForAi, ProcessingAi, Completed, Failed |
| `PublisherType` | Unknown, Individual, Agency, Developer |

## Livewire Pages

### Admin Subdomain (`admin.{domain}`)

| Route | Component | Purpose |
|-------|-----------|---------|
| `/dashboard` | Admin/Dashboard | Cost stats, pipeline stats, recent activity |
| `/platforms` | Admin/Platforms/Index | List platforms with search queries |
| `/platforms/{id}` | Admin/Platforms/Show | Platform detail, start scrapes |
| `/listings` | Admin/Listings/Index | Browse all listings |
| `/listings/{id}` | Admin/Listings/Show | Listing detail with images |
| `/properties` | Admin/Properties/Index | Browse unified properties |
| `/properties/{id}` | Admin/Properties/Show | Property detail with linked listings |
| `/publishers` | Admin/Publishers/Index | Browse agents/agencies |
| `/publishers/{id}` | Admin/Publishers/Show | Publisher detail |
| `/dedup/review` | Admin/Dedup/ReviewCandidates | Review uncertain dedup matches |
| `/runs/{id}` | Admin/ScrapeRuns/Show | Real-time scrape progress |
| `/settings/*` | Settings/* | Profile, password, 2FA, appearance |

### Agents Subdomain (`agents.{domain}`)

| Route | Component | Purpose |
|-------|-----------|---------|
| `/properties` | (Placeholder) | Agent property search (Phase 4) |
| `/settings/*` | Settings/* | Profile, password, 2FA, appearance |

## Scraper Contracts

Platform-specific scraping is implemented via interfaces:
- `ScraperConfigInterface` - Platform configuration (selectors, URLs)
- `SearchParserInterface` - Parse search results page
- `ListingParserInterface` - Parse individual listing

Implementations: Inmuebles24, Vivanuncios, Propiedades.com (all complete)

## Database Tables

**Core**: `platforms`, `search_queries`, `scrape_runs`, `scrape_jobs`, `discovered_listings`, `listings`, `listing_groups`, `properties`, `publishers`, `dedup_candidates`

**Supporting**: `listing_images`, `listing_phones`, `property_conflicts`, `property_verifications`, `property_publisher` (pivot), `api_usage_logs`

**System**: `users`, `cache`, `jobs`, `failed_jobs`

---

## Subdomain Architecture

The application uses subdomain-based routing to separate concerns:

| Subdomain | Purpose | Access |
|-----------|---------|--------|
| `admin.{domain}` | Internal admin (scraping, data management) | Admin users only |
| `agents.{domain}` | Agent application (search, collections, sharing) | Agent users only |
| `{domain}` (public) | Public pages (shared collections, marketing) | Unauthenticated |

**Configuration:** `config/domains.php` defines subdomain values.

**User Roles:**
- `UserRole::Admin` - Access to admin subdomain only
- `UserRole::Agent` - Access to agents subdomain only
- New user registration defaults to `agent` role
- Admins created via `UserSeeder` only

**Key Files:**
- `app/Enums/UserRole.php` - Role enum
- `app/Http/Middleware/EnsureUserRole.php` - Role-based access control
- `app/Http/Responses/LoginResponse.php` - Role-based login redirect
- `app/Http/Responses/RegisterResponse.php` - Role-based register redirect
- `app/Http/Responses/VerifyEmailResponse.php` - Role-based email verification redirect
- `app/Models/User.php` - `homeUrl()` method for centralized redirect logic

**Route Naming Convention:**
- Admin routes: `admin.{resource}.{action}` (e.g., `admin.platforms.index`)
- Agent routes: `agents.{resource}.{action}` (e.g., `agents.properties.index`)

---

## Recent Changes

- **Subdomain Routing**: Application now uses subdomain-based routing with role-based access control. Admin functionality moved to `admin.` subdomain, agent features at `agents.` subdomain.
- **User Roles**: Added `UserRole` enum with `Admin` and `Agent` cases. Role-based middleware and auth responses implemented.
- **Reverse Geocoding Fallback**: GeocodingService now automatically reverse geocodes when forward geocoding returns no colonia (common for route-level matches). Only fills in colonia and postal_code from reverse geocode, never alters street address.
- **Propiedades.com Scraper**: Complete implementation with geo-restriction handling (`proxy_country=mx`).
- **Single-Request Optimization**: Scraping reduced from 2 ZenRows calls to 1 by extracting scripts via CSS selector.
- **Dedup Review UI**: Added human review interface for uncertain deduplication matches.
- **Pipeline Reset on Re-scrape**: Fixed bug where re-scraping didn't reset pipeline status.

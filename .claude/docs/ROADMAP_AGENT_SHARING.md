# Roadmap: Agent Sharing Tools & Preferences

**Document:** `.claude/docs/ROADMAP_AGENT_SHARING.md`
**Previous:** `.claude/docs/ROADMAP_AGENT_APPLICATION.md` (completed)
**Related:** `.claude/docs/ARCHITECTURE_STATE.md`
**Created:** 2026-01-23
**Status:** In Progress
**Last Updated:** 2026-01-24

---

## Executive Summary

Enhance the agent sharing experience with improved tools, customization options, and client management. Building on the completed agent application foundation.

**Foundation (from previous roadmap):**
- Property search and collection management ✅
- WhatsApp sharing and copy link ✅
- Public collection pages ✅
- View tracking ✅
- PDF export (basic) ✅

---

## Goals

1. **Agent branding** - Let agents personalize their profile and how they appear to clients
2. **Better PDFs** - Magazine-style exports that agents are proud to share
3. **Client management** - Simple CRM for tracking clients and engagement
4. **Customizable sharing** - Personalized WhatsApp messages and improved public views

---

## Phase 1: Agent Profile & Branding ✅

**Goal:** Give agents control over their professional identity.

### Features
- [x] Profile photo upload
- [x] Business name and tagline
- [x] Brand color (accent for PDFs and public pages)
- [x] Phone and WhatsApp configuration
- [x] Default WhatsApp message template with placeholders

### Database Changes
- Add to users: `avatar_path`, `business_name`, `tagline`, `brand_color`, `default_whatsapp_message`

### UI
- Agent profile fields merged into existing Profile settings page (agents only)

### Implementation
- Migration: `2026_01_24_014435_add_agent_profile_fields_to_users_table.php`
- Component: `app/Livewire/Settings/Profile.php` (merged agent fields)
- View: `resources/views/livewire/settings/profile.blade.php` (conditional agent section)
- Tests: `tests/Feature/Agents/AgentProfileTest.php` (19 tests)

---

## Phase 2: Magazine-Style PDF & Public View ✅

**Goal:** Create professional layouts that agents are proud to share (both PDF and web).

### Features
- [x] Agent branding header (photo, business name, tagline, contact)
- [x] Large property images (1 property per section)
- [x] Full property details (specs, description excerpt, operation type badge)
- [x] Brand color accents throughout
- [x] Footer with agent contact info
- [x] Matching web view for shared links (same magazine layout)
- [x] WhatsApp CTAs on each property (web view only - PDFs are static)

### Implementation
- PDF template: `resources/views/pdf/collection.blade.php` (magazine layout)
- Public view: `resources/views/livewire/public/collections/show.blade.php` (matching magazine layout)
- Component: `app/Livewire/Agents/Collections/Show.php` (downloadPdf method)
- Component: `app/Livewire/Public/Collections/Show.php` (loads client relationship)

---

## Phase 3: Client Mini-CRM

**Goal:** Help agents track their clients and engagement.

### Features
- [ ] Dedicated clients list page
- [ ] Client search
- [ ] View client activity (collections shared, total views, last activity)
- [ ] Edit and delete clients
- [ ] Quick WhatsApp contact from client list

### Database Changes
- None (Client model already exists)

### UI
- New `/clients` page
- "Clientes" menu item in agent navigation

---

## Phase 4: Enhanced Sharing

**Goal:** Make sharing more personal and effective.

### WhatsApp Customization
- [ ] Editable message templates
- [ ] Placeholders: `{collection_name}`, `{client_name}`, `{link}`, `{property_count}`, `{agent_name}`
- [ ] Per-agent default template

### Public Collection Improvements
- [x] Display agent avatar and business name (completed in Phase 2)
- [x] Apply brand color as accent (completed in Phase 2)
- [ ] Open Graph meta tags for social sharing previews

---

## Future Considerations

*Not planned for immediate implementation:*

- Email sharing option
- Collection expiration settings
- Password-protected collections
- View notifications (email when collection is viewed)
- Geographic analytics on views
- Client tags/categories

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-23 | Initial document created |
| 2.0 | 2026-01-24 | Defined 4-phase implementation plan |

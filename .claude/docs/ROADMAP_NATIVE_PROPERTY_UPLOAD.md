# Native Property Upload - Implementation Plan

## Overview

Enable agents to upload their own properties using natural language description + AI extraction, bypassing the traditional form-heavy approach.

**Core Innovation**: "Describe tu propiedad como se la contarías a un colega. Nosotros hacemos el resto."

**Target**: Property upload in under 3 minutes.

---

## Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Property ownership | Add `user_id` (nullable) to Property | One model keeps things simple |
| Source tracking | Add `source_type` enum (scraped, native) | Explicit filtering |
| Collaboration visibility | Collaborative → all agents, Private → owner only | Core collaboration value |
| Price storage | `ai_extracted_data` JSON + `operation_type` field | Consistent with scraped |
| Operations per property | One (rent OR sale) | Simpler UX for V1 |
| V1 tracking | None | Ship fast, validate concept |
| Editing | Full edit + soft delete | Agents own their data |
| Voice input | Not in scope | Validate text flow first |

---

## Database Schema Changes

### Migration: Add Native Upload Fields to Properties

```php
Schema::table('properties', function (Blueprint $table) {
    // Ownership
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

    // Source tracking
    $table->string('source_type')->default('scraped'); // scraped | native

    // Operation type (for native properties with direct pricing)
    $table->string('operation_type')->nullable(); // rent | sale

    // Native property pricing
    $table->decimal('price', 14, 2)->nullable();
    $table->string('price_currency', 3)->default('MXN');

    // Collaboration settings
    $table->boolean('is_collaborative')->default(false);
    $table->decimal('commission_split', 5, 2)->nullable(); // e.g., 50.00 for 50%

    // Original agent description
    $table->text('original_description')->nullable();

    // Soft delete for unpublishing
    $table->softDeletes();

    // Indexes
    $table->index(['user_id', 'source_type']);
    $table->index(['source_type', 'is_collaborative']);
});
```

### New Model: PropertyImage

```php
Schema::create('property_images', function (Blueprint $table) {
    $table->id();
    $table->foreignId('property_id')->constrained()->cascadeOnDelete();
    $table->string('path'); // Storage path
    $table->string('original_filename')->nullable();
    $table->unsignedInteger('size_bytes')->nullable();
    $table->unsignedSmallInteger('position')->default(0);
    $table->boolean('is_cover')->default(false);
    $table->timestamps();

    $table->index(['property_id', 'position']);
});
```

### New Enum: PropertySourceType

```php
enum PropertySourceType: string
{
    case Scraped = 'scraped';
    case Native = 'native';
}
```

---

## Upload Flow: 5 Screens

### Screen 1: Describe (`/propiedades/nueva`)

**Component**: `App\Livewire\Agents\Properties\Upload\Describe`

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  Describe tu propiedad                                      │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │                                                       │ │
│  │  Ej: Casa en Providencia, 4 recámaras, 3 baños,      │ │
│  │  350m² de construcción, tiene roof garden y está     │ │
│  │  en 6.5 millones...                                  │ │
│  │                                                       │ │
│  │                                                       │ │
│  │                                                       │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│                                        [Continuar →]        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- Single large `<flux:textarea>` with placeholder
- Minimum 20 characters to proceed
- Show character count
- Mobile-optimized (full-width, comfortable height)
- "Continuar" calls AI extraction, shows loading state

**State**:
```php
public string $description = '';
public bool $isProcessing = false;
```

---

### Screen 2: Review & Confirm (`/propiedades/nueva/revisar`)

**Component**: `App\Livewire\Agents\Properties\Upload\Review`

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  Esto es lo que entendí                                     │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Tipo          [Casa ▼]           Operación  [Venta ▼]  ││
│  │ Ubicación     [Providencia, Guadalajara      ] [edit]  ││
│  │ Precio        [$6,500,000 MXN                ] [edit]  ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Recámaras     [4]     Baños      [3]     Medio baño [0]││
│  │ Construcción  [350 m²]           Terreno  [— m²]       ││
│  │ Estacionamiento [—]              Antigüedad [— años]   ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  Amenidades     [+ Agregar]                                 │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ [Roof garden ×] [Jardín ×]                              ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  Descripción para mostrar                                   │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Hermosa casa en Providencia con 4 amplias recámaras... ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  ⚠️ Falta: Terreno (opcional)                              │
│                                                             │
│                              [← Volver]  [Confirmar →]      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- All fields editable inline (tap to edit)
- Dropdowns for type/operation (Flux select)
- Number inputs for beds/baths/sizes
- Pill-based amenity editor
- Show "missing but optional" hints
- Require: price, location, operation type
- Clean description generated by AI for display

**State** (extracted data):
```php
public ?string $propertyType = null;
public ?string $operationType = null;
public ?string $location = null;  // Colonia, City format
public ?string $colonia = null;
public ?string $city = null;
public ?string $state = null;
public ?float $price = null;
public ?int $bedrooms = null;
public ?int $bathrooms = null;
public ?int $halfBathrooms = null;
public ?float $builtSize = null;
public ?float $lotSize = null;
public ?int $parkingSpots = null;
public ?int $ageYears = null;
public array $amenities = [];
public string $displayDescription = '';
public string $originalDescription = '';
```

---

### Screen 3: Photos (`/propiedades/nueva/fotos`)

**Component**: `App\Livewire\Agents\Properties\Upload\Photos`

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  Agrega fotos de la propiedad                               │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │                                                         ││
│  │      📷  Arrastra fotos aquí o haz clic para subir     ││
│  │                                                         ││
│  │          Máximo 20 fotos, 10MB cada una                ││
│  │                                                         ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐                   │
│  │ ★     │ │       │ │       │ │       │                   │
│  │ img1  │ │ img2  │ │ img3  │ │ img4  │                   │
│  │   [×] │ │   [×] │ │   [×] │ │   [×] │                   │
│  └───────┘ └───────┘ └───────┘ └───────┘                   │
│  ↑ Portada                                                  │
│                                                             │
│  Arrastra para reordenar. Primera foto = portada.           │
│                                                             │
│                    [← Volver]  [Saltar]  [Continuar →]      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- Livewire file upload with multiple selection
- Drag & drop zone (Flux file-upload or custom)
- Thumbnail preview grid
- Drag to reorder (Alpine.js sortable)
- First image = cover (marked with star)
- Remove button on each thumbnail
- "Saltar" skips photos (can add later)
- Max 20 photos, 10MB each, jpg/png/webp

**State**:
```php
public array $photos = []; // Temporary uploaded files
public array $photoOrder = []; // Order for drag/drop
```

---

### Screen 4: Sharing Settings (`/propiedades/nueva/compartir`)

**Component**: `App\Livewire\Agents\Properties\Upload\Sharing`

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  ¿Quieres que otros agentes puedan ofrecer esta propiedad? │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  ○  Solo yo                                             ││
│  │     Solo tú podrás compartir esta propiedad con         ││
│  │     clientes.                                           ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  ●  Abierta a colaboración                              ││
│  │     Otros agentes en PropData podrán incluirla en      ││
│  │     sus colecciones y compartirla con sus clientes.     ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  ¿Qué porcentaje de comisión compartes?                 ││
│  │                                                         ││
│  │  [30%]  [40%]  [50%]  [Otro: ___]                      ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│                              [← Volver]  [Publicar →]       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- Radio selection for sharing mode
- Commission options appear only if collaborative
- Default: "Solo yo" (safer, less friction)
- Commission defaults to 50%
- "Publicar" creates the property

**State**:
```php
public bool $isCollaborative = false;
public ?float $commissionSplit = 50.0;
```

---

### Screen 5: Confirmation (`/propiedades/nueva/listo`)

**Component**: `App\Livewire\Agents\Properties\Upload\Complete`

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│              ✓                                              │
│                                                             │
│  ¡Lista! Tu propiedad ya está publicada.                   │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  [Property Card Preview - same as search results]       ││
│  │                                                         ││
│  │  Casa en Providencia         $6,500,000                 ││
│  │  4 rec · 3 baños · 350 m²    Venta                     ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  [📤 Compartir ahora]                                       │
│                                                             │
│  [📋 Ver mis propiedades]    [+ Agregar otra]              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- Show property card preview (same component used in search)
- "Compartir ahora" → goes to share flow (link/WhatsApp)
- "Ver mis propiedades" → goes to My Properties list
- "Agregar otra" → back to Screen 1

---

## AI Extraction Service

### Service: `App\Services\AI\PropertyExtractionService`

Uses existing `ClaudeClient` to extract structured data from freeform text.

```php
class PropertyExtractionService
{
    public function __construct(
        private ClaudeClient $claude,
        private ApiUsageTracker $usageTracker
    ) {}

    /**
     * Extract property data from agent's natural language description.
     *
     * @return array{
     *   property_type: ?string,
     *   operation_type: ?string,
     *   location: array{colonia: ?string, city: ?string, state: ?string},
     *   price: ?float,
     *   bedrooms: ?int,
     *   bathrooms: ?int,
     *   half_bathrooms: ?int,
     *   built_size_m2: ?float,
     *   lot_size_m2: ?float,
     *   parking_spots: ?int,
     *   age_years: ?int,
     *   amenities: array<string>,
     *   display_description: string,
     *   missing_fields: array<string>,
     *   confidence: float
     * }
     */
    public function extract(string $description): array
    {
        // Use tool_use for structured extraction
        // Handle Mexican Spanish, abbreviations, informal language
        // Return structured data + cleaned description
    }
}
```

**AI Prompt Strategy**:
- Use tool_use with JSON schema for reliable extraction
- Handle Mexican real estate terminology:
  - "rec" → recámaras, "m2" → metros cuadrados
  - "depto" → departamento, "bodega" → warehouse
  - "medio baño" → half bathroom
  - Price formats: "4.2 millones", "4,200,000", "$4.2M"
- Identify what's missing for prompting
- Generate clean display description
- Don't hallucinate data not provided

---

## Property Management

### My Properties List (`/propiedades/mis-propiedades`)

**Component**: `App\Livewire\Agents\Properties\MyProperties`

Shows all properties owned by the current agent.

```
┌─────────────────────────────────────────────────────────────┐
│  Mis Propiedades                            [+ Nueva]       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ [img] Casa en Providencia            $6.5M  Venta      ││
│  │       4 rec · 3 baños · 350 m²                         ││
│  │       🤝 Abierta a colaboración                        ││
│  │                                    [Editar] [···]      ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ [img] Depto en Roma Norte            $18k/mes  Renta   ││
│  │       2 rec · 1 baño · 85 m²                           ││
│  │       🔒 Privada                                       ││
│  │                                    [Editar] [···]      ││
│  └─────────────────────────────────────────────────────────┘│
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Menu options** (`[···]`):
- Ver detalles
- Compartir
- Editar
- Eliminar (soft delete with confirmation)

---

### Edit Property (`/propiedades/{property}/editar`)

**Component**: `App\Livewire\Agents\Properties\Edit`

Similar to Review screen but for existing property:
- All fields editable
- Photo management (add/remove/reorder)
- Sharing settings changeable
- Save changes

---

## Search Integration

### Update Property Query in `agents.properties.index`

```php
// Current: all properties (scraped only)
Property::query()->...

// New: include native properties with visibility rules
Property::query()
    ->where(function ($q) {
        // All scraped properties (no owner)
        $q->where('source_type', 'scraped')
        // OR native + collaborative (visible to all)
        ->orWhere(function ($q) {
            $q->where('source_type', 'native')
              ->where('is_collaborative', true);
        })
        // OR native + owned by current user (always visible to owner)
        ->orWhere(function ($q) {
            $q->where('source_type', 'native')
              ->where('user_id', auth()->id());
        });
    })
    ->whereNull('deleted_at') // Soft delete check
    ->...
```

### Property Card Updates

Show ownership/collaboration badge on property cards:
- Native + yours: "Tu propiedad"
- Native + collaborative: "Colaboración" with agent name
- Scraped: no badge (default)

---

## Navigation Updates

### Agent Navigation

```
[Propiedades ▼]          [Colecciones]  [Clientes]  [Configuración]
  └─ Buscar propiedades
  └─ Mis propiedades
  └─ Nueva propiedad
```

Or simpler:
```
[Propiedades]  [Mis Propiedades]  [Colecciones]  [Clientes]  [Configuración]
                └─ [+ Nueva] button inside
```

---

## Implementation Phases

### Phase 1: Foundation (Database & Models)
**Files to create/modify:**
- `database/migrations/xxxx_add_native_upload_fields_to_properties.php`
- `database/migrations/xxxx_create_property_images_table.php`
- `app/Enums/PropertySourceType.php`
- `app/Models/Property.php` - Add relationships, scopes, fillable
- `app/Models/PropertyImage.php` - New model
- `database/factories/PropertyFactory.php` - Update for new fields

**Verification:**
- Migration runs successfully
- Property model has new fields
- PropertyImage model works

### Phase 2: AI Extraction Service
**Files to create:**
- `app/Services/AI/PropertyExtractionService.php`
- `tests/Feature/Services/PropertyExtractionServiceTest.php`

**Verification:**
- Service extracts data from sample descriptions
- Handles various formats and informal language
- Returns structured data matching expected schema

### Phase 3: Upload Flow UI (Core)
**Files to create:**
- `app/Livewire/Agents/Properties/Upload/Describe.php`
- `app/Livewire/Agents/Properties/Upload/Review.php`
- `app/Livewire/Agents/Properties/Upload/Photos.php`
- `app/Livewire/Agents/Properties/Upload/Sharing.php`
- `app/Livewire/Agents/Properties/Upload/Complete.php`
- `resources/views/livewire/agents/properties/upload/*.blade.php` (5 views)
- `routes/web.php` - Add upload routes

**Verification:**
- Full flow works end-to-end
- Property created with correct data
- Photos uploaded and linked
- Mobile-responsive

### Phase 4: Property Management
**Files to create:**
- `app/Livewire/Agents/Properties/MyProperties.php`
- `app/Livewire/Agents/Properties/Edit.php`
- `resources/views/livewire/agents/properties/my-properties.blade.php`
- `resources/views/livewire/agents/properties/edit.blade.php`

**Verification:**
- My Properties shows owned properties
- Edit flow works
- Delete (soft) works with confirmation

### Phase 5: Search Integration
**Files to modify:**
- `app/Livewire/Agents/Properties/Index.php` - Update query
- `resources/views/livewire/agents/properties/index.blade.php` - Add badges
- `resources/views/components/layouts/agent.blade.php` - Navigation

**Verification:**
- Native collaborative properties visible to all agents
- Private properties visible only to owner
- Badges display correctly

### Phase 6: Tests
**Files to create:**
- `tests/Feature/Agents/PropertyUploadTest.php`
- `tests/Feature/Agents/MyPropertiesTest.php`
- `tests/Feature/Agents/PropertySearchIntegrationTest.php`

---

## File Structure Summary

```
app/
├── Enums/
│   └── PropertySourceType.php
├── Livewire/Agents/Properties/
│   ├── Upload/
│   │   ├── Describe.php
│   │   ├── Review.php
│   │   ├── Photos.php
│   │   ├── Sharing.php
│   │   └── Complete.php
│   ├── MyProperties.php
│   └── Edit.php
├── Models/
│   ├── Property.php (modified)
│   └── PropertyImage.php (new)
└── Services/AI/
    └── PropertyExtractionService.php

resources/views/livewire/agents/properties/
├── upload/
│   ├── describe.blade.php
│   ├── review.blade.php
│   ├── photos.blade.php
│   ├── sharing.blade.php
│   └── complete.blade.php
├── my-properties.blade.php
└── edit.blade.php

database/migrations/
├── xxxx_add_native_upload_fields_to_properties.php
└── xxxx_create_property_images_table.php

tests/Feature/
├── Agents/PropertyUploadTest.php
├── Agents/MyPropertiesTest.php
└── Services/PropertyExtractionServiceTest.php
```

---

## Routes

```php
// Property Upload Flow
Route::middleware(['auth', 'verified'])->prefix('propiedades')->group(function () {
    Route::get('/nueva', Upload\Describe::class)->name('agents.properties.upload.describe');
    Route::get('/nueva/revisar', Upload\Review::class)->name('agents.properties.upload.review');
    Route::get('/nueva/fotos', Upload\Photos::class)->name('agents.properties.upload.photos');
    Route::get('/nueva/compartir', Upload\Sharing::class)->name('agents.properties.upload.sharing');
    Route::get('/nueva/listo', Upload\Complete::class)->name('agents.properties.upload.complete');

    Route::get('/mis-propiedades', MyProperties::class)->name('agents.properties.mine');
    Route::get('/{property}/editar', Edit::class)->name('agents.properties.edit');
});
```

---

## Success Criteria

1. ✅ Agent can describe property in natural language and get structured data
2. ✅ Full upload flow completes in under 3 minutes
3. ✅ AI extraction is accurate enough that most fields don't need editing
4. ✅ Photos upload smoothly with drag-to-reorder
5. ✅ Collaborative properties visible to other agents in search
6. ✅ Owner can edit/delete their properties
7. ✅ Mobile experience is smooth and touch-friendly
8. ✅ All flows work on mobile

---

## Not In Scope (V1)

- Voice input
- Property analytics/stats
- Bulk upload
- Import from other platforms
- Video upload
- Floor plan upload
- Virtual tour integration
- Collaboration notifications
- Revenue/commission tracking
- Duplicate detection with scraped properties

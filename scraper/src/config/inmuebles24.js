/**
 * Selector configuration for Inmuebles24.com
 * Update these selectors when the site structure changes
 */
export const config = {
  platform: 'inmuebles24',

  // Main content selectors
  selectors: {
    // Page load indicators
    pageLoaded: '[class*="property"], [class*="posting"], article, [data-qa="posting"]',

    // Title
    title: [
      'h1[class*="title"]',
      'h1[data-qa="title"]',
      '[class*="posting-title"] h1',
      'h1',
    ],

    // Description
    description: [
      '[data-qa="description"]',
      '[class*="description-content"]',
      '[class*="posting-description"]',
      '#description',
      '[itemprop="description"]',
      '[class*="section-description"]',
    ],

    // Price
    price: [
      '[data-qa="price"]',
      '[class*="price-value"]',
      '[class*="Price"]',
      '[class*="price-container"]',
    ],

    // Maintenance fee
    maintenanceFee: [
      '[class*="maintenance"]',
      '[class*="expensas"]',
      '[data-qa="expenses"]',
      '[class*="Expenses"]',
    ],

    // Location
    location: [
      '[data-qa="location"]',
      '[class*="location-container"]',
      '[class*="posting-location"]',
      '[itemprop="address"]',
      '[class*="address"]',
    ],

    // Breadcrumbs (for location parsing)
    breadcrumbs: [
      '[class*="breadcrumb"]',
      'nav[aria-label*="breadcrumb"]',
      '[data-qa="breadcrumb"]',
    ],

    // Property features/details
    features: [
      '[class*="feature"]',
      '[class*="attribute"]',
      '[data-qa*="feature"]',
      '[class*="property-features"]',
      '[class*="main-features"]',
      'li[class*="icon"]',
    ],

    // Amenities
    amenities: [
      '[class*="amenities"]',
      '[class*="servicios"]',
      '[class*="general-features"]',
      '[data-qa="amenities"]',
    ],

    // Gallery/Images
    gallery: [
      '[class*="gallery"] img',
      '[class*="carousel"] img',
      '[class*="slider"] img',
      '[data-qa="gallery"] img',
      '[class*="gallery-image"]',
      'picture source',
      'img[class*="photo"]',
    ],

    // Contact section
    contact: {
      container: [
        '[class*="contact"]',
        '[class*="publisher"]',
        '[class*="seller"]',
        '[data-qa="contact"]',
      ],
      phoneRevealButton: [
        'button[data-qa="show-phone"]',
        '[class*="show-phone"]',
        '[class*="ver-telefono"]',
        'button[class*="phone"]',
        '[data-qa="phone-button"]',
        'a[class*="phone"][class*="button"]',
      ],
      phoneNumber: [
        '[data-qa="phone-number"]',
        '[class*="phone-number"]',
        '[class*="phone-revealed"]',
        'a[href^="tel:"]',
        '[class*="contact-phone"]',
      ],
      whatsapp: [
        'a[href*="wa.me"]',
        'a[href*="whatsapp"]',
        '[class*="whatsapp"]',
        '[data-qa="whatsapp"]',
      ],
      agentName: [
        '[class*="agent-name"]',
        '[data-qa="publisher-name"]',
        '[class*="contact-name"]',
        '[class*="publisher-name"]',
        '[class*="seller-name"]',
      ],
      agencyName: [
        '[class*="agency-name"]',
        '[class*="real-estate-name"]',
        '[class*="inmobiliaria"]',
        '[class*="publisher-logo"] + *',
        '[data-qa="agency-name"]',
      ],
      agencyUrl: [
        'a[href*="/inmobiliarias/"]',
        'a[href*="/agencia/"]',
        '[class*="agency-link"]',
      ],
    },

    // Map/coordinates
    map: [
      '[data-lat][data-lng]',
      '[data-latitude][data-longitude]',
      '#map',
      '[class*="map-container"]',
      '[class*="static-map"]',
    ],
  },

  // Regex patterns for extraction
  patterns: {
    // External ID from URL
    externalId: /(?:propiedades|propiedad|clasificado)[/-].*?(\d{6,})/,

    // Price parsing
    price: /\$?\s*([\d,]+(?:\.\d{2})?)/,

    // Currency detection
    currencyUSD: /(?:usd|us\$|dólares?)/i,

    // Phone number (10 digits)
    phone: /(?:\+?52)?[\s.-]*(\d{2,3})[\s.-]*(\d{3,4})[\s.-]*(\d{4})/g,

    // Postal code (5 digits)
    postalCode: /\b(\d{5})\b/,

    // Property features
    bedrooms: /(\d+)\s*(?:rec[aá]maras?|dormitorios?|habitaci[oó]n(?:es)?|cuartos?)/i,
    bathrooms: /(\d+)\s*(?:ba[nñ]os?(?:\s+completos?)?|wc)/i,
    halfBathrooms: /(\d+)\s*(?:medios?\s+ba[nñ]os?|1\/2\s+ba[nñ]os?)/i,
    parking: /(\d+)\s*(?:estacionamientos?|garages?|cocheras?|autos?|cajones?)/i,
    m2Built: /(\d+(?:,\d+)?)\s*m[2²]\s*(?:const(?:ruidos?)?|cubiertos?|construcci[oó]n)/i,
    m2Lot: /(\d+(?:,\d+)?)\s*m[2²]\s*(?:terreno|totales?|lote)/i,
    floor: /(?:piso|nivel)\s*(\d+)/i,
    age: /(\d+)\s*a[nñ]os?\s*(?:de\s+)?(?:antig[uü]edad|construido)/i,
    newConstruction: /(?:a\s+estrenar|nuevo|reci[eé]n\s+construido)/i,
  },

  // Property type mappings
  propertyTypes: {
    departamento: 'apartment',
    apartamento: 'apartment',
    depto: 'apartment',
    casa: 'house',
    oficina: 'office',
    local: 'commercial',
    comercial: 'commercial',
    terreno: 'land',
    lote: 'land',
    bodega: 'warehouse',
    edificio: 'building',
  },

  // Amenity standardization
  amenityMappings: {
    'alberca': 'pool',
    'piscina': 'pool',
    'gimnasio': 'gym',
    'gym': 'gym',
    'seguridad': 'security_24h',
    'vigilancia': 'security_24h',
    'elevador': 'elevator',
    'ascensor': 'elevator',
    'roof': 'roof_garden',
    'terraza': 'terrace',
    'jardín': 'garden',
    'jardin': 'garden',
    'áreas verdes': 'green_areas',
    'areas verdes': 'green_areas',
    'pet friendly': 'pet_friendly',
    'mascotas': 'pet_friendly',
    'estacionamiento': 'parking',
    'bodega': 'storage',
    'cuarto de servicio': 'service_room',
    'aire acondicionado': 'ac',
    'calefacción': 'heating',
    'amueblado': 'furnished',
  },

  // Timeouts (ms)
  timeouts: {
    pageLoad: 30000,
    phoneReveal: 5000,
    elementWait: 10000,
  },
};

export default config;

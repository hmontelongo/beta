import { writeFile, mkdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { BaseScraper } from './BaseScraper.js';
import config from '../config/inmuebles24.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DEBUG_DIR = join(__dirname, '../../debug');

/**
 * Normalization maps for platform IDs
 */
const OPERATION_TYPES = { 1: 'sale', 2: 'rent' };
const CURRENCY_TYPES = { 1: 'USD', 10: 'MXN' };
const PROPERTY_TYPES = { 1: 'house', 2: 'apartment', 3: 'land', 4: 'commercial', 5: 'office' };
const PUBLISHER_TYPES = { 1: 'individual', 2: 'agency', 3: 'developer' };

/**
 * Scraper for Inmuebles24.com listings
 * Uses JavaScript extraction as primary method, HTML as fallback
 */
export class Inmuebles24Scraper extends BaseScraper {
  constructor(browser, options = {}) {
    super(browser);
    this.platform = 'inmuebles24';
    this.config = config;
    this.debug = options.debug || false;
    this.warnings = [];
  }

  /**
   * Try multiple selectors and return first match
   */
  async trySelectors(page, selectors, extractFn = 'text') {
    const selectorList = Array.isArray(selectors) ? selectors : [selectors];

    for (const selector of selectorList) {
      try {
        const element = await page.$(selector);

        if (element) {
          if (extractFn === 'text') {
            const text = await element.textContent();
            if (text && text.trim()) {
              return text.trim();
            }
          } else if (extractFn === 'html') {
            return await element.innerHTML();
          } else if (extractFn === 'element') {
            return element;
          }
        }
      } catch {
        continue;
      }
    }
    return null;
  }

  /**
   * Save debug files (HTML and screenshot)
   */
  async saveDebugFiles(page, externalId) {
    if (!this.debug) return null;

    try {
      await mkdir(DEBUG_DIR, { recursive: true });

      const timestamp = new Date().toISOString().split('T')[0];
      const baseName = `${timestamp}-${externalId || 'unknown'}`;

      const htmlPath = join(DEBUG_DIR, `${baseName}.html`);
      const screenshotPath = join(DEBUG_DIR, `${baseName}.png`);

      const html = await page.content();
      await writeFile(htmlPath, html);
      await page.screenshot({ path: screenshotPath, fullPage: true });

      return {
        html_path: `debug/${baseName}.html`,
        screenshot_path: `debug/${baseName}.png`,
      };
    } catch (error) {
      console.error('Error saving debug files:', error.message);
      return null;
    }
  }

  /**
   * Scrape a single listing page
   */
  async scrapeSingle(url) {
    const page = await this.createPage();
    this.warnings = [];

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded' });

      const loaded = await this.waitForSelector(
        page,
        this.config.selectors.pageLoaded,
        this.config.timeouts.pageLoad
      );

      if (!loaded) {
        throw new Error('Page content did not load - listing may not exist');
      }

      await page.waitForTimeout(2000);

      const externalId = this.extractExternalId(url);
      const debugInfo = await this.saveDebugFiles(page, externalId);
      const data = await this.extractListingData(page, url, externalId);
      const extractionQuality = this.calculateExtractionQuality(data);

      return {
        data,
        debug: debugInfo,
        extraction_quality: extractionQuality,
      };
    } finally {
      await page.close();
    }
  }

  /**
   * Simulate human-like behavior to avoid bot detection
   */
  async simulateHumanBehavior(page) {
    try {
      // Random mouse movement
      const viewportSize = page.viewportSize();
      const x = Math.floor(Math.random() * (viewportSize?.width || 1280));
      const y = Math.floor(Math.random() * (viewportSize?.height || 800));
      await page.mouse.move(x, y);

      // Small random scroll
      await page.evaluate(() => {
        window.scrollBy(0, Math.floor(Math.random() * 300) + 100);
      });

      // Random delay
      await page.waitForTimeout(Math.floor(Math.random() * 1000) + 500);
    } catch {
      // Ignore errors in human simulation
    }
  }

  /**
   * Accept cookie consent if present
   */
  async acceptCookieConsent(page) {
    try {
      // Try multiple cookie consent button selectors
      const consentSelectors = [
        'button:has-text("Acepto")',
        'button:has-text("Aceptar")',
        '[class*="cookie"] button',
        '[class*="consent"] button',
        '[data-qa="cookie-accept"]',
        '#onetrust-accept-btn-handler',
      ];

      for (const selector of consentSelectors) {
        try {
          const button = await page.$(selector);
          if (button) {
            await button.click();
            await page.waitForTimeout(500);
            return true;
          }
        } catch {
          continue;
        }
      }
    } catch {
      // Consent banner not found or already accepted
    }
    return false;
  }

  /**
   * Scrape a search results page
   * Returns listings AND pagination info in a single request
   */
  async scrapeSearch(url, max = 10) {
    const page = await this.createPage();

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded' });

      // Simulate human behavior before interacting
      await this.simulateHumanBehavior(page);

      // Accept cookie consent if present (important for avoiding blocking)
      await this.acceptCookieConsent(page);

      // Wait for content with extended timeout for Cloudflare challenges
      const loaded = await this.waitForSelector(
        page,
        this.config.selectors.pageLoaded,
        this.config.timeouts.pageLoad + 10000 // Extra time for bot challenges
      );

      if (!loaded) {
        // Try one more time after another scroll
        await this.simulateHumanBehavior(page);
        await page.waitForTimeout(3000);
        const retryLoaded = await this.waitForSelector(
          page,
          this.config.selectors.pageLoaded,
          5000
        );
        if (!retryLoaded) {
          // Save debug screenshot on error
          await this.saveDebugFiles(page, 'error-search');
          throw new Error('Search results did not load');
        }
      }

      await page.waitForTimeout(2000);
      const debugInfo = await this.saveDebugFiles(page, 'search');

      // Extract listings AND pagination in a single evaluate call
      const result = await page.evaluate((maxResults) => {
        const listings = [];
        const seenUrls = new Set();

        // Find listing cards using multiple selectors
        const cardSelectors = [
          '[class*="postingCardLayout"]',
          '[data-qa="posting"]',
          '[class*="posting-card"]',
          '[class*="listing-card"]',
          'article[class*="posting"]',
          'div[class*="PostingCard"]',
        ];

        let cards = [];
        for (const selector of cardSelectors) {
          cards = document.querySelectorAll(selector);
          if (cards.length > 0) break;
        }

        for (const card of cards) {
          if (listings.length >= maxResults) break;

          const link = card.querySelector('a[href*="/propiedades/"], a[href*="/propiedad/"]');
          if (!link) continue;

          const href = link.href.split('?')[0];
          if (seenUrls.has(href)) continue;
          seenUrls.add(href);

          const idMatch = href.match(/(\d{6,})/);
          const externalId = idMatch ? idMatch[1] : null;

          const priceEl = card.querySelector('[data-qa="price"], [class*="price"], [class*="Price"]');
          const locationEl = card.querySelector('[data-qa="location"], [class*="location"], [class*="address"]');
          const titleEl = card.querySelector('[data-qa="title"], h2, h3, [class*="title"]');
          const imageEl = card.querySelector('img');

          listings.push({
            external_id: externalId,
            url: href,
            preview: {
              title: titleEl ? titleEl.textContent.trim() : null,
              price: priceEl ? priceEl.textContent.trim().replace(/\s+/g, ' ') : null,
              location: locationEl ? locationEl.textContent.trim().replace(/\s+/g, ' ') : null,
              image: imageEl ? imageEl.src || imageEl.dataset.src : null,
            },
          });
        }

        // Extract pagination info from the same page
        let totalResults = 0;
        const countSelectors = [
          '[data-qa="result-count"]',
          '[class*="result-count"]',
          '[class*="ResultsCount"]',
          'h1',
        ];

        for (const selector of countSelectors) {
          const el = document.querySelector(selector);
          if (el) {
            const text = el.textContent;
            const match = text.match(/([\d,\.]+)\s*(propiedades?|inmuebles?|resultados?)/i);
            if (match) {
              totalResults = parseInt(match[1].replace(/[,\.]/g, ''), 10);
              break;
            }
          }
        }

        // Find total pages from pagination
        let totalPages = 1;
        const paginationSelectors = [
          '[data-qa="pagination"] a',
          '[class*="pagination"] a',
          'nav[class*="pagination"] a',
          '.pagination a',
        ];

        for (const selector of paginationSelectors) {
          const links = document.querySelectorAll(selector);
          if (links.length > 0) {
            let maxPage = 1;
            links.forEach(link => {
              const text = link.textContent.trim();
              const num = parseInt(text, 10);
              if (!isNaN(num) && num > maxPage) {
                maxPage = num;
              }
            });
            if (maxPage > 1) {
              totalPages = maxPage;
              break;
            }
          }
        }

        // Estimate pages if not found
        if (totalPages === 1 && totalResults > 20) {
          totalPages = Math.ceil(totalResults / 20);
        }

        return {
          listings,
          pagination: { totalResults, totalPages },
        };
      }, max);

      return {
        data: result.listings,
        pagination: result.pagination,
        debug: debugInfo,
      };
    } finally {
      await page.close();
    }
  }

  /**
   * Extract all data from a listing page
   * Uses JavaScript extraction as primary, HTML as fallback
   */
  async extractListingData(page, url, externalId) {
    // Get page HTML for JS extraction
    const html = await page.content();

    // PRIMARY: Extract from JavaScript variables
    const jsData = this.extractFromJavaScript(html);

    // SECONDARY: Extract from HTML (fallback and supplementary)
    const [htmlFeatures, htmlAmenities, htmlLocation, title, description] = await Promise.all([
      this.extractFeaturesFromHtml(page),
      this.extractAmenitiesFromHtml(page),
      this.extractLocationFromHtml(page),
      this.extractTitle(page),
      this.extractDescription(page),
    ]);

    // Extract images (prefer JS, fallback to HTML)
    const images = jsData.pictures.length > 0
      ? jsData.pictures
      : await this.extractImagesFromHtml(page);

    // Extract coordinates
    const coordinates = await this.extractCoordinates(page);

    // Extract stats from HTML
    const stats = await this.extractStats(page);

    // Build operations array
    const operations = this.buildOperations(jsData, html);

    // Merge features (JS data takes precedence where available)
    const features = this.mergeFeatures(jsData, htmlFeatures);

    // Build location (JS IDs + HTML text)
    const location = this.buildLocation(jsData, htmlLocation, coordinates);

    // Build publisher info
    const publisher = this.buildPublisher(jsData);

    // DESCRIPTION PARSING: Extract additional data from description text
    const descriptionData = this.parseDescription(description, title);

    // Merge amenities from HTML and description
    const allAmenities = this.mergeAmenities(htmlAmenities, descriptionData.amenities);

    // Cross-validate features and detect conflicts
    const dataQuality = this.crossValidateFeatures(features, descriptionData.extractedFeatures);

    // Return flat structure matching Laravel schema
    return {
      external_id: externalId || jsData.postingId,
      original_url: url,
      title,
      description,

      // Operations
      operations,

      // Features
      bedrooms: features.bedrooms,
      bathrooms: features.bathrooms,
      half_bathrooms: features.half_bathrooms,
      parking_spots: features.parking_spots,
      lot_size_m2: features.lot_size_m2,
      built_size_m2: features.built_size_m2,
      age_years: features.age_years,
      property_type: features.property_type,
      property_subtype: descriptionData.subtype,

      // Location
      address: location.address,
      colonia: location.colonia,
      city: location.city,
      state: location.state,
      latitude: location.latitude,
      longitude: location.longitude,

      // Publisher
      publisher_id: publisher.id,
      publisher_name: publisher.name,
      publisher_type: publisher.type,
      publisher_url: publisher.url,
      publisher_logo: publisher.logo,
      whatsapp: publisher.whatsapp,

      // Images (just URLs)
      images: images.map(img => typeof img === 'string' ? img : img.url),

      // Amenities (merged from HTML and description)
      amenities: allAmenities,

      // External references
      external_codes: {
        easybroker: descriptionData.easybrokerId,
      },

      // Data quality indicators
      data_quality: dataQuality,

      // Platform metadata (for raw_data)
      platform_metadata: {
        province_id: jsData.provinceId,
        city_id: jsData.cityId,
        neighborhood_id: jsData.neighborhoodId,
        property_type_id: jsData.propertyTypeId,
        publisher_type_id: jsData.publisherTypeId,
        posting_id: jsData.postingId,
        days_published: stats.daysPublished,
        days_published_raw: stats.daysPublishedRaw,
        views: stats.views,
        views_raw: stats.viewsRaw,
      },
    };
  }

  /**
   * Parse description text to extract structured data
   */
  parseDescription(description, title) {
    const result = {
      easybrokerId: null,
      amenities: [],
      extractedFeatures: {},
      subtype: null,
    };

    if (!description) return result;

    const text = description.toUpperCase();
    const combinedText = `${title || ''} ${description}`.toUpperCase();

    // Extract EasyBroker ID
    result.easybrokerId = this.extractEasyBrokerId(description);

    // Extract amenities from description
    result.amenities = this.extractAmenitiesFromDescription(text);

    // Extract features for cross-validation
    result.extractedFeatures = this.extractFeaturesFromDescription(text);

    // Detect property subtype
    result.subtype = this.detectPropertySubtype(combinedText);

    return result;
  }

  /**
   * Extract EasyBroker ID from description
   */
  extractEasyBrokerId(description) {
    const patterns = [
      /EASYBROKER\s*ID:\s*(EB-[A-Z0-9]+)/i,
      /EB-[A-Z0-9]{6,}/i,
      /EASY\s*BROKER[:\s]*(EB-[A-Z0-9]+)/i,
    ];

    for (const pattern of patterns) {
      const match = description.match(pattern);
      if (match) {
        // Ensure we capture just the ID part
        const id = match[1] || match[0];
        if (id.startsWith('EB-')) return id;
      }
    }

    return null;
  }

  /**
   * Extract amenities from description text
   */
  extractAmenitiesFromDescription(text) {
    const amenities = [];

    // Amenity patterns: [pattern, standardized_name]
    const amenityPatterns = [
      [/ALBERCA|PISCINA/g, 'pool'],
      [/GIMNASIO|GYM\b/g, 'gym'],
      [/SEGURIDAD\s*24|VIGILANCIA\s*24|SEGURIDAD\s+LAS?\s+24|SEGURIDAD\s+24\/7/g, 'security_24h'],
      [/ELEVADOR(?:ES)?(?:\s+INTELIGENTES)?|ASCENSOR/g, 'elevator'],
      [/ESTACIONAMIENTO\s+TECHADO|COCHERA\s+TECHADA/g, 'covered_parking'],
      [/ROOF\s*GARDEN|ROOF\s*TOP|ROOFTOP/g, 'rooftop'],
      [/TERRAZA(?!\s+TECHADA)/g, 'terrace'],
      [/BALCON(?:ES)?/g, 'balcony'],
      [/JACUZZI|JACUZI/g, 'jacuzzi'],
      [/[ÁA]REA\s+DE\s+BBQ|ASADOR(?:ES)?|PARRILLA/g, 'bbq_area'],
      [/SALA\s+DE\s+JUNTAS/g, 'meeting_room'],
      [/SAL[OÓ]N\s+DE\s+EVENTOS/g, 'event_room'],
      [/BUSINESS\s+CENTER|CENTRO\s+DE\s+NEGOCIOS/g, 'business_center'],
      [/PET\s*FRIENDLY|ACEPTA\s+MASCOTAS|SE\s+ACEPTAN\s+MASCOTAS/g, 'pet_friendly'],
      [/\bAMUEBLADO\b/g, 'furnished'],
      [/SAL[OÓ]N\s+DE\s+USOS\s+M[UÚ]LTIPLES|USOS\s+M[UÚ]LTIPLES/g, 'multipurpose_room'],
      [/[ÁA]REA\s+DE\s+JUEGOS|LUDOTECA|PLAYGROUND/g, 'playground'],
      [/BIBLIOTECA/g, 'library'],
      [/CANCHA|TENIS|PADEL/g, 'sports_court'],
      [/JARDINES/g, 'garden'],
      [/[ÁA]REAS?\s+VERDES?/g, 'green_areas'],
      [/CUARTO\s+DE\s+SERVICIO/g, 'service_room'],
      [/BODEGA(?!\s+INDUSTRIAL)/g, 'storage'],
      [/AIRE\s+ACONDICIONADO|A\/?C\s+INCLUIDO/g, 'ac'],
      [/CALEFACCI[OÓ]N|HEATING/g, 'heating'],
      [/\bLOBBY\b/g, 'lobby'],
      [/CIRCUITO\s+CERRADO|CCTV|C[ÁA]MARAS\s+DE\s+SEGURIDAD/g, 'cctv'],
      [/PISTA\s+(?:PARA\s+)?CORRER|JOGGING/g, 'jogging_track'],
      [/CO-?WORKING|COWORKING/g, 'coworking'],
      [/\bSPA\b|VAPOR|SAUNA/g, 'spa'],
      [/\bCINE\b/g, 'cinema'],
      [/COCINA\s+INTEGRAL/g, 'integrated_kitchen'],
      [/[ÁA]REA\s+DE\s+LAVADO/g, 'laundry_area'],
      [/COUNTRY\s+CLUB/g, 'country_club'],
      [/VISTA\s+360|VISTA\s+PANOR[ÁA]MICA/g, 'panoramic_view'],
    ];

    for (const [pattern, name] of amenityPatterns) {
      if (pattern.test(text)) {
        if (!amenities.includes(name)) {
          amenities.push(name);
        }
      }
    }

    return amenities;
  }

  /**
   * Extract features from description for cross-validation
   */
  extractFeaturesFromDescription(text) {
    const features = {};

    // Bedrooms
    const bedroomPatterns = [
      /(\d+)\s*REC[ÁA]MARAS?/,
      /(\d+)\s*DORMITORIOS?/,
      /(\d+)\s*HABITACI[OÓ]N(?:ES)?/,
      /(\d+)\s*CUARTOS?/,
    ];
    for (const pattern of bedroomPatterns) {
      const match = text.match(pattern);
      if (match) {
        features.bedrooms = parseInt(match[1]);
        break;
      }
    }

    // Bathrooms
    const bathroomPatterns = [
      /(\d+)\s*BA[ÑN]OS?\s*COMPLETOS?/,
      /(\d+)\s*BA[ÑN]OS?(?!\s*MEDIO)/,
    ];
    for (const pattern of bathroomPatterns) {
      const match = text.match(pattern);
      if (match) {
        features.bathrooms = parseInt(match[1]);
        break;
      }
    }

    // Half bathrooms
    const halfBathMatch = text.match(/(\d+)\s*(?:MEDIO\s*BA[ÑN]O|½\s*BA[ÑN]O)/);
    if (halfBathMatch) {
      features.half_bathrooms = parseInt(halfBathMatch[1]);
    } else if (/1\/2\s*BA[ÑN]O/i.test(text)) {
      // Handle "1/2 BAÑO" format
      features.half_bathrooms = 1;
    }

    // Parking
    const parkingPatterns = [
      /(\d+)\s*(?:CAJONES?\s+DE\s+)?ESTACIONAMIENTOS?/,
      /(\d+)\s*COCHERAS?/,
      /(\d+)\s*LUGARES?\s+DE\s+ESTACIONAMIENTO/,
    ];
    for (const pattern of parkingPatterns) {
      const match = text.match(pattern);
      if (match) {
        features.parking_spots = parseInt(match[1]);
        break;
      }
    }

    // Built size (m2)
    const m2Patterns = [
      /(\d+(?:,\d+)?)\s*M(?:2|²|TS2?)\s*(?:DE\s+)?(?:CONSTRUCCI[OÓ]N|CONSTRUIDOS?|CUBIERTOS?)/,
      /(\d+(?:,\d+)?)\s*M(?:2|²|TS2?)\s*DE\s+SUPERFICIE/,
      /SUPERFICIE[:\s]*(\d+(?:,\d+)?)\s*M(?:2|²)/,
    ];
    for (const pattern of m2Patterns) {
      const match = text.match(pattern);
      if (match) {
        features.built_size_m2 = parseFloat(match[1].replace(',', ''));
        break;
      }
    }

    return features;
  }

  /**
   * Detect property subtype from description and title
   */
  detectPropertySubtype(text) {
    const subtypePatterns = [
      [/\bPH\b|PENTHOUSE/g, 'penthouse'],
      [/GARDEN|PLANTA\s+BAJA|PB\b/g, 'ground_floor'],
      [/\bLOFT\b/g, 'loft'],
      [/DUPLEX|D[ÚU]PLEX/g, 'duplex'],
      [/TRIPLEX/g, 'triplex'],
      [/ESTUDIO/g, 'studio'],
    ];

    for (const [pattern, subtype] of subtypePatterns) {
      if (pattern.test(text)) {
        return subtype;
      }
    }

    return null;
  }

  /**
   * Merge amenities from HTML and description, removing duplicates
   */
  mergeAmenities(htmlAmenities, descriptionAmenities) {
    const merged = new Set([...htmlAmenities, ...descriptionAmenities]);
    return Array.from(merged);
  }

  /**
   * Cross-validate features between extracted and description values
   * Returns data quality object with confirmed fields and conflicts
   */
  crossValidateFeatures(extractedFeatures, descriptionFeatures) {
    const conflicts = [];
    const confirmed = [];
    const fieldsToValidate = ['bedrooms', 'bathrooms', 'half_bathrooms', 'parking_spots', 'built_size_m2'];

    for (const field of fieldsToValidate) {
      const extracted = extractedFeatures[field];
      const fromDesc = descriptionFeatures[field];

      // Skip if description doesn't have this field
      if (fromDesc === null || fromDesc === undefined) {
        continue;
      }

      // Skip if extracted value is missing
      if (extracted === null || extracted === undefined) {
        continue;
      }

      // Check for match or conflict
      if (extracted === fromDesc) {
        // Values match - confirmed
        confirmed.push(field);
      } else {
        // Allow small variance for m2 (±5%)
        if (field === 'built_size_m2') {
          const variance = Math.abs(extracted - fromDesc) / extracted;
          if (variance <= 0.05) {
            // Within tolerance - confirmed
            confirmed.push(field);
          } else {
            conflicts.push({
              field,
              icon_value: extracted,
              description_value: fromDesc,
              variance_percent: Math.round(variance * 100),
            });
          }
        } else {
          conflicts.push({
            field,
            icon_value: extracted,
            description_value: fromDesc,
          });
        }
      }
    }

    return {
      has_conflicts: conflicts.length > 0,
      confirmed,
      conflicts,
      description_features: descriptionFeatures,
    };
  }

  /**
   * Extract data from JavaScript variables in the page HTML
   */
  extractFromJavaScript(html) {
    const data = {
      price: null,
      currencyId: null,
      operationTypeId: null,
      provinceId: null,
      cityId: null,
      neighborhoodId: null,
      propertyTypeId: null,
      postingId: null,
      publisherId: null,
      publisherName: null,
      publisherTypeId: null,
      publisherUrl: null,
      publisherLogo: null,
      whatsapp: null,
      pictures: [],
    };

    try {
      // Extract from doubleClick object
      const priceMatch = html.match(/'price':\s*'(\d+)'/);
      if (priceMatch) data.price = parseInt(priceMatch[1]);

      const currencyMatch = html.match(/'currencyId':\s*'(\d+)'/);
      if (currencyMatch) data.currencyId = currencyMatch[1];

      const operationMatch = html.match(/'operationTypeId':\s*'(\d+)'/);
      if (operationMatch) data.operationTypeId = operationMatch[1];

      const provinceMatch = html.match(/'provinceId':\s*'(\d+)'/);
      if (provinceMatch) data.provinceId = provinceMatch[1];

      const cityMatch = html.match(/'cityId':\s*'(\d+)'/);
      if (cityMatch) data.cityId = cityMatch[1];

      const neighborhoodMatch = html.match(/'neighborhoodId':\s*'(\d+)'/);
      if (neighborhoodMatch) data.neighborhoodId = neighborhoodMatch[1];

      const propertyTypeMatch = html.match(/'propertyTypeId':\s*'(\d+)'/);
      if (propertyTypeMatch) data.propertyTypeId = propertyTypeMatch[1];

      const postingIdMatch = html.match(/'postingId':\s*'(\d+)'/);
      if (postingIdMatch) data.postingId = postingIdMatch[1];

      // Extract from listing.publisher object
      const publisherIdMatch = html.match(/'publisherId':\s*'(\d+)'/);
      if (publisherIdMatch) data.publisherId = publisherIdMatch[1];

      const publisherNameMatch = html.match(/'name':\s*'([^']+)'/);
      if (publisherNameMatch) data.publisherName = publisherNameMatch[1];

      const publisherTypeIdMatch = html.match(/'publisherTypeId':\s*'(\d+)'/);
      if (publisherTypeIdMatch) data.publisherTypeId = publisherTypeIdMatch[1];

      // Publisher URL - look for inmobiliaria/agencia profile URLs specifically
      const publisherUrlMatch = html.match(/'url':\s*'(\/(?:inmobiliaria|agencia|desarrolladora)[^']+)'/);
      if (publisherUrlMatch) data.publisherUrl = publisherUrlMatch[1];

      const publisherLogoMatch = html.match(/'urlLogo':\s*'([^']+)'/);
      if (publisherLogoMatch) data.publisherLogo = publisherLogoMatch[1];

      // WhatsApp - try multiple patterns
      const whatsappMatch = html.match(/'whatsApp':\s*'([^']+)'/) ||
                           html.match(/whatsApp["']?\s*[:=]\s*["']([^"']+)["']/);
      if (whatsappMatch) {
        data.whatsapp = this.normalizePhone(whatsappMatch[1]);
      }

      // Extract pictures array
      const picturesMatch = html.match(/'pictures':\s*(\[[\s\S]*?\])\s*[,}]/);
      if (picturesMatch) {
        try {
          // Clean up the JSON-like string
          const picturesJson = picturesMatch[1]
            .replace(/'/g, '"')
            .replace(/(\w+):/g, '"$1":');
          const pictures = JSON.parse(picturesJson);
          data.pictures = pictures.map(p => ({
            url: p.url1200x1200 || p.url720x532 || p.url360x266 || p.url,
            thumbnail: p.url360x266 || p.url,
          }));
        } catch {
          // Fallback: extract URLs directly
          const urlMatches = html.matchAll(/url1200x1200["']?\s*[:=]\s*["']([^"']+)["']/g);
          for (const match of urlMatches) {
            data.pictures.push({ url: match[1] });
          }
        }
      }

      // Alternative picture extraction from gallery data
      if (data.pictures.length === 0) {
        const galleryMatch = html.match(/galleryImages\s*[:=]\s*(\[[\s\S]*?\])/);
        if (galleryMatch) {
          const urlMatches = galleryMatch[1].matchAll(/["']([^"']+(?:naventcdn|cloudinary)[^"']+)["']/g);
          for (const match of urlMatches) {
            if (!data.pictures.some(p => p.url === match[1])) {
              data.pictures.push({ url: match[1] });
            }
          }
        }
      }
    } catch (error) {
      this.warnings.push(`js_extraction_error: ${error.message}`);
    }

    return data;
  }

  /**
   * Extract features from HTML icon elements
   */
  async extractFeaturesFromHtml(page) {
    const features = {
      bedrooms: null,
      bathrooms: null,
      half_bathrooms: null,
      parking_spots: null,
      lot_size_m2: null,
      built_size_m2: null,
      age_years: null,
      property_type: null,
    };

    try {
      const extracted = await page.evaluate(() => {
        const result = {};

        // Map icon classes to feature names
        const iconMap = {
          'icon-stotal': 'lot_size_m2',
          'icon-scubierta': 'built_size_m2',
          'icon-bano': 'bathrooms',
          'icon-dormitorio': 'bedrooms',
          'icon-cochera': 'parking_spots',
          'icon-toilete': 'half_bathrooms',
          'icon-antiguedad': 'age_years',
        };

        // Find feature items
        const featureItems = document.querySelectorAll(
          'li.icon-feature, li[class*="icon-"], .section-icon-features-property li'
        );

        for (const item of featureItems) {
          const text = item.textContent.trim();
          const numMatch = text.match(/(\d+)/);
          if (!numMatch) continue;

          const value = parseInt(numMatch[1]);
          const classList = item.className || '';

          for (const [iconClass, featureName] of Object.entries(iconMap)) {
            if (classList.includes(iconClass) || item.querySelector(`.${iconClass}`)) {
              result[featureName] = value;
              break;
            }
          }

          // Fallback: detect by text content
          const textLower = text.toLowerCase();
          if (textLower.includes('recámara') || textLower.includes('dormitorio')) {
            result.bedrooms = result.bedrooms || value;
          } else if (textLower.includes('baño') && !textLower.includes('medio')) {
            result.bathrooms = result.bathrooms || value;
          } else if (textLower.includes('medio baño') || textLower.includes('½')) {
            result.half_bathrooms = result.half_bathrooms || value;
          } else if (textLower.includes('estacionamiento') || textLower.includes('cochera')) {
            result.parking_spots = result.parking_spots || value;
          } else if (textLower.includes('m²') || textLower.includes('m2')) {
            if (textLower.includes('total') || textLower.includes('terreno')) {
              result.lot_size_m2 = result.lot_size_m2 || value;
            } else if (textLower.includes('cubierta') || textLower.includes('const')) {
              result.built_size_m2 = result.built_size_m2 || value;
            } else if (!result.built_size_m2) {
              result.built_size_m2 = value;
            }
          } else if (textLower.includes('año') || textLower.includes('antigüedad')) {
            result.age_years = result.age_years || value;
          }
        }

        // Extract property type from title or breadcrumbs
        const title = document.querySelector('h1')?.textContent?.toLowerCase() || '';
        const breadcrumb = document.querySelector('[class*="breadcrumb"]')?.textContent?.toLowerCase() || '';
        const combined = title + ' ' + breadcrumb;

        if (combined.includes('departamento') || combined.includes('depto')) {
          result.property_type = 'apartment';
        } else if (combined.includes('casa')) {
          result.property_type = 'house';
        } else if (combined.includes('terreno') || combined.includes('lote')) {
          result.property_type = 'land';
        } else if (combined.includes('oficina')) {
          result.property_type = 'office';
        } else if (combined.includes('local') || combined.includes('comercial')) {
          result.property_type = 'commercial';
        }

        return result;
      });

      Object.assign(features, extracted);
    } catch (error) {
      this.warnings.push(`html_features_error: ${error.message}`);
    }

    return features;
  }

  /**
   * Extract amenities from HTML
   */
  async extractAmenitiesFromHtml(page) {
    try {
      const amenities = await page.evaluate(() => {
        const result = [];
        const seen = new Set();

        // Try multiple selectors for amenities
        const selectors = [
          '.generalFeaturesProperty-module__description-text',
          '[class*="amenities"] li',
          '[class*="servicios"] li',
          '[class*="general-features"] li',
          '[class*="caracteristicas"] li',
        ];

        for (const selector of selectors) {
          const items = document.querySelectorAll(selector);
          for (const item of items) {
            const text = item.textContent.trim().toLowerCase();
            if (text && text.length < 50 && !seen.has(text)) {
              seen.add(text);
              result.push(text);
            }
          }
          if (result.length > 0) break;
        }

        return result;
      });

      // Standardize amenity names
      return this.standardizeAmenities(amenities);
    } catch {
      return [];
    }
  }

  /**
   * Standardize amenity names
   */
  standardizeAmenities(rawAmenities) {
    const mappings = this.config.amenityMappings;
    const standardized = [];

    for (const raw of rawAmenities) {
      let found = false;
      for (const [keyword, standard] of Object.entries(mappings)) {
        if (raw.includes(keyword)) {
          if (!standardized.includes(standard)) {
            standardized.push(standard);
          }
          found = true;
          break;
        }
      }
      if (!found && raw.length > 2) {
        const cleaned = raw.replace(/[^a-záéíóúñü\s]/gi, '').trim();
        if (cleaned && !standardized.includes(cleaned)) {
          standardized.push(cleaned);
        }
      }
    }

    return standardized;
  }

  /**
   * Extract location from HTML
   */
  async extractLocationFromHtml(page) {
    const location = {
      address: null,
      colonia: null,
      city: null,
      state: null,
    };

    try {
      const extracted = await page.evaluate(() => {
        const result = {};

        // Try location header
        const locationHeader = document.querySelector('.section-location-property h4, [class*="location"] h4');
        if (locationHeader) {
          const parts = locationHeader.textContent.split(',').map(p => p.trim());
          if (parts.length >= 1) result.address = parts[0];
          if (parts.length >= 2) result.colonia = parts[1];
          if (parts.length >= 3) result.city = parts[2];
          if (parts.length >= 4) result.state = parts[3];
        }

        // Try breadcrumbs for state/city
        const breadcrumbs = document.querySelectorAll('[class*="breadcrumb"] a, nav[aria-label*="breadcrumb"] a');
        const crumbs = Array.from(breadcrumbs).map(a => a.textContent.trim());

        // Mexican states list
        const states = ['jalisco', 'nuevo león', 'cdmx', 'ciudad de méxico', 'estado de méxico',
                       'querétaro', 'puebla', 'yucatán', 'quintana roo', 'guanajuato'];

        for (let i = 0; i < crumbs.length; i++) {
          const crumbLower = crumbs[i].toLowerCase();
          if (states.some(s => crumbLower.includes(s))) {
            result.state = result.state || crumbs[i];
            if (i + 1 < crumbs.length) {
              result.city = result.city || crumbs[i + 1];
            }
          }
        }

        // Last meaningful crumb is often colonia
        if (crumbs.length > 0) {
          const lastCrumb = crumbs[crumbs.length - 1];
          if (!lastCrumb.toLowerCase().includes('departamento') &&
              !lastCrumb.toLowerCase().includes('casa')) {
            result.colonia = result.colonia || lastCrumb;
          }
        }

        return result;
      });

      Object.assign(location, extracted);
    } catch (error) {
      this.warnings.push(`html_location_error: ${error.message}`);
    }

    return location;
  }

  /**
   * Extract images from HTML (fallback)
   */
  async extractImagesFromHtml(page) {
    try {
      return await page.evaluate(() => {
        const result = [];
        const seen = new Set();

        const selectors = [
          '[class*="gallery"] img',
          '[class*="carousel"] img',
          '[class*="slider"] img',
          'picture source',
          'img[class*="photo"]',
        ];

        for (const selector of selectors) {
          const elements = document.querySelectorAll(selector);
          for (const el of elements) {
            let src = el.getAttribute('data-src') ||
                     el.getAttribute('srcset')?.split(',').pop()?.trim().split(' ')[0] ||
                     el.getAttribute('src');

            if (!src) continue;
            if (src.includes('icon') || src.includes('logo') || src.includes('placeholder')) continue;

            // Upgrade to high res
            src = src.replace(/360x266/, '1200x1200').replace(/720x532/, '1200x1200');

            try {
              const absoluteUrl = new URL(src, window.location.origin).href;
              if (!seen.has(absoluteUrl)) {
                seen.add(absoluteUrl);
                result.push(absoluteUrl);
              }
            } catch {
              if (!seen.has(src)) {
                seen.add(src);
                result.push(src);
              }
            }
          }
        }

        return result;
      });
    } catch {
      return [];
    }
  }

  /**
   * Extract stats (days published, views)
   */
  async extractStats(page) {
    const stats = {
      daysPublished: null,
      daysPublishedRaw: null,
      views: null,
      viewsRaw: null,
    };

    try {
      const extracted = await page.evaluate(() => {
        const result = {
          daysPublished: null,
          daysPublishedRaw: null,
          views: null,
          viewsRaw: null,
        };

        // Primary selector: .view-users-container
        const statsContainer = document.querySelector('.view-users-container');
        // Fallback selectors
        const statsEl = statsContainer ||
          document.querySelector('.userViews-module') ||
          document.querySelector('[class*="view-users"]') ||
          document.querySelector('[class*="stats"]');

        if (statsEl) {
          const text = statsEl.textContent;

          // Days published - handle various formats
          const daysMatch = text.match(/Publicado hace (\d+) días?/i);
          const todayMatch = text.match(/Publicado hoy/i);
          const yesterdayMatch = text.match(/Publicado ayer/i);

          if (daysMatch) {
            result.daysPublished = parseInt(daysMatch[1]);
            result.daysPublishedRaw = daysMatch[0];
          } else if (todayMatch) {
            result.daysPublished = 0;
            result.daysPublishedRaw = 'Publicado hoy';
          } else if (yesterdayMatch) {
            result.daysPublished = 1;
            result.daysPublishedRaw = 'Publicado ayer';
          }

          // Views - handle singular/plural
          const viewsMatch = text.match(/(\d+)\s*visualizaci[oó]n(es)?/i);
          if (viewsMatch) {
            result.views = parseInt(viewsMatch[1]);
            result.viewsRaw = viewsMatch[0];
          }
        }

        return result;
      });

      Object.assign(stats, extracted);
    } catch {
      // Stats are optional
    }

    return stats;
  }

  /**
   * Build operations array from JS data and HTML
   */
  buildOperations(jsData, html) {
    const operations = [];

    // Primary operation from JS
    if (jsData.price && jsData.operationTypeId) {
      const operation = {
        type: OPERATION_TYPES[jsData.operationTypeId] || 'sale',
        price: jsData.price,
        currency: CURRENCY_TYPES[jsData.currencyId] || 'MXN',
        maintenance_fee: null,
      };

      // Try to find maintenance fee from various patterns
      const maintenancePatterns = [
        /Mantenimiento\s*(?:MN|USD|\$)?\s*([\d,]+)/i,
        /MN\s*([\d,]+)\s*Mantenimiento/i,
        /mantenimiento[:\s]*\$?\s*([\d,]+)/i,
        /\$\s*([\d,]+)\s*(?:de\s+)?mant/i,
      ];

      for (const pattern of maintenancePatterns) {
        const match = html.match(pattern);
        if (match) {
          operation.maintenance_fee = parseInt(match[1].replace(/,/g, ''));
          break;
        }
      }

      operations.push(operation);
    }

    // Check for secondary operation (some listings have both rent and sale)
    const hasRent = html.includes('Renta') || html.includes('renta');
    const hasSale = html.includes('Venta') || html.includes('venta');

    if (hasRent && hasSale && operations.length === 1) {
      // Try to find the second price
      const priceMatches = html.matchAll(/(?:Venta|Renta)\s*MN\s*([\d,]+)/gi);
      for (const match of priceMatches) {
        const price = parseInt(match[1].replace(/,/g, ''));
        const isRent = match[0].toLowerCase().includes('renta');
        const type = isRent ? 'rent' : 'sale';

        if (!operations.some(op => op.type === type)) {
          operations.push({ type, price, currency: 'MXN', maintenance_fee: null });
        }
      }
    }

    return operations;
  }

  /**
   * Merge features from JS and HTML
   */
  mergeFeatures(jsData, htmlFeatures) {
    const features = { ...htmlFeatures };

    // Use JS property type if available
    if (jsData.propertyTypeId) {
      features.property_type = PROPERTY_TYPES[jsData.propertyTypeId] || features.property_type;
    }

    return features;
  }

  /**
   * Build location object
   */
  buildLocation(jsData, htmlLocation, coordinates) {
    return {
      address: htmlLocation.address,
      colonia: htmlLocation.colonia,
      city: htmlLocation.city,
      state: htmlLocation.state,
      latitude: coordinates?.latitude || null,
      longitude: coordinates?.longitude || null,
      // Include IDs for potential lookup
      province_id: jsData.provinceId,
      city_id: jsData.cityId,
      neighborhood_id: jsData.neighborhoodId,
    };
  }

  /**
   * Build publisher info
   */
  buildPublisher(jsData) {
    return {
      id: jsData.publisherId,
      name: jsData.publisherName,
      type: PUBLISHER_TYPES[jsData.publisherTypeId] || null,
      url: jsData.publisherUrl ? `https://www.inmuebles24.com${jsData.publisherUrl}` : null,
      logo: jsData.publisherLogo,
      whatsapp: jsData.whatsapp,
    };
  }

  /**
   * Calculate extraction quality metrics
   */
  calculateExtractionQuality(data) {
    const expectedFields = [
      'external_id',
      'title',
      'description',
      'colonia',
      'city',
      'property_type',
      'bedrooms',
      'bathrooms',
      'built_size_m2',
      'operations',
      'whatsapp',
      'images',
    ];

    const missingFields = [];
    let fieldsFound = 0;

    for (const field of expectedFields) {
      const value = data[field];
      if (value === null || value === undefined || (Array.isArray(value) && value.length === 0)) {
        missingFields.push(field);
      } else {
        fieldsFound++;
      }
    }

    return {
      fields_found: fieldsFound,
      fields_total: expectedFields.length,
      fields_missing: missingFields,
      warnings: this.warnings,
    };
  }

  /**
   * Extract external ID from URL
   */
  extractExternalId(url) {
    const match = url.match(this.config.patterns.externalId);
    return match ? match[1] : null;
  }

  /**
   * Extract listing title
   */
  async extractTitle(page) {
    return await this.trySelectors(page, [
      'h1.title-property',
      'h1[class*="title"]',
      'h1[data-qa="title"]',
      'h1',
    ]);
  }

  /**
   * Extract listing description
   */
  async extractDescription(page) {
    const description = await this.trySelectors(page, [
      '#longDescription',
      '[data-qa="description"]',
      '[class*="description-content"]',
      '[itemprop="description"]',
    ]);

    if (description) {
      return description.replace(/\s+/g, ' ').replace(/Leer descripción completa/gi, '').trim();
    }

    return null;
  }

  /**
   * Extract coordinates from page
   */
  async extractCoordinates(page) {
    try {
      // First try data attributes on map elements
      for (const selector of this.config.selectors.map) {
        try {
          const el = await page.$(selector);
          if (el) {
            const lat = await el.getAttribute('data-lat').catch(() => null) ||
                        await el.getAttribute('data-latitude').catch(() => null);
            const lng = await el.getAttribute('data-lng').catch(() => null) ||
                        await el.getAttribute('data-longitude').catch(() => null);

            if (lat && lng) {
              return { latitude: parseFloat(lat), longitude: parseFloat(lng) };
            }
          }
        } catch {
          continue;
        }
      }

      // Try to find coordinates in page scripts
      const coords = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script');

        for (const script of scripts) {
          const content = script.textContent || '';

          const jsonMatch = content.match(/"latitude":\s*([-\d.]+).*?"longitude":\s*([-\d.]+)/s);
          if (jsonMatch) {
            return { latitude: parseFloat(jsonMatch[1]), longitude: parseFloat(jsonMatch[2]) };
          }

          const latMatch = content.match(/["']?lat(?:itude)?["']?\s*[:=]\s*([-\d.]+)/i);
          const lngMatch = content.match(/["']?(?:lng|lon|longitude)["']?\s*[:=]\s*([-\d.]+)/i);

          if (latMatch && lngMatch) {
            const lat = parseFloat(latMatch[1]);
            const lng = parseFloat(lngMatch[1]);
            if (lat >= 14 && lat <= 33 && lng >= -118 && lng <= -86) {
              return { latitude: lat, longitude: lng };
            }
          }
        }

        return null;
      });

      return coords;
    } catch {
      return null;
    }
  }
}

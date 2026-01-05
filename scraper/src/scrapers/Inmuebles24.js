import { BaseScraper } from './BaseScraper.js';

/**
 * Scraper for Inmuebles24.com listings
 */
export class Inmuebles24Scraper extends BaseScraper {
  constructor(browser) {
    super(browser);
    this.platform = 'inmuebles24';
  }

  /**
   * Scrape a single listing page
   */
  async scrapeSingle(url) {
    const page = await this.createPage();

    try {
      // Navigate to the listing page
      await page.goto(url, { waitUntil: 'domcontentloaded' });

      // Wait for the main content to load
      const loaded = await this.waitForSelector(page, '[class*="property"], [class*="posting"], article', 10000);

      if (!loaded) {
        throw new Error('Page content did not load - listing may not exist');
      }

      // Give extra time for dynamic content
      await page.waitForTimeout(2000);

      // Extract all data
      const data = await this.extractListingData(page, url);

      return data;
    } finally {
      await page.close();
    }
  }

  /**
   * Scrape a search results page
   */
  async scrapeSearch(url, max = 10) {
    const page = await this.createPage();

    try {
      // Navigate to search results
      await page.goto(url, { waitUntil: 'domcontentloaded' });

      // Wait for results to load
      const loaded = await this.waitForSelector(
        page,
        '[data-qa="posting"], [class*="posting-card"], [class*="listing-card"], article',
        15000
      );

      if (!loaded) {
        throw new Error('Search results did not load');
      }

      // Give extra time for all results to render
      await page.waitForTimeout(2000);

      // Extract listing URLs and preview data
      const listings = await page.evaluate((maxResults) => {
        const results = [];

        // Try multiple selector patterns for listing cards
        const cardSelectors = [
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

        for (let i = 0; i < Math.min(cards.length, maxResults); i++) {
          const card = cards[i];

          // Find the link to the listing
          const link = card.querySelector('a[href*="/propiedades/"], a[href*="/propiedad/"]');
          if (!link) continue;

          const href = link.href;

          // Extract preview data
          const priceEl = card.querySelector('[data-qa="price"], [class*="price"], [class*="Price"]');
          const locationEl = card.querySelector(
            '[data-qa="location"], [class*="location"], [class*="address"]'
          );
          const titleEl = card.querySelector('[data-qa="title"], h2, h3, [class*="title"]');

          results.push({
            url: href,
            preview: {
              title: titleEl ? titleEl.textContent.trim() : null,
              price: priceEl ? priceEl.textContent.trim() : null,
              location: locationEl ? locationEl.textContent.trim() : null,
            },
          });
        }

        return results;
      }, max);

      return listings;
    } finally {
      await page.close();
    }
  }

  /**
   * Extract all data from a listing page
   */
  async extractListingData(page, url) {
    // Extract external ID from URL
    const externalId = this.extractExternalId(url);

    // Run all extractions in parallel where possible
    const [title, description, priceData, locationData, propertyDetails, amenities, images, contactData, coordinates] =
      await Promise.all([
        this.extractTitle(page),
        this.extractDescription(page),
        this.extractPriceData(page),
        this.extractLocationData(page),
        this.extractPropertyDetails(page),
        this.extractAmenities(page),
        this.extractImages(page),
        this.extractContactData(page),
        this.extractCoordinates(page),
      ]);

    return {
      external_id: externalId,
      original_url: url,
      title,
      description,
      ...locationData,
      ...coordinates,
      ...propertyDetails,
      operations: priceData.operations,
      maintenance_fee: priceData.maintenanceFee,
      amenities,
      images,
      ...contactData,
    };
  }

  /**
   * Extract external ID from URL
   */
  extractExternalId(url) {
    // Pattern: /propiedades/casa-en-venta-12345678.html or /propiedades/12345678
    const match = url.match(/(?:propiedades|propiedad)[/-].*?(\d{6,})/);
    return match ? match[1] : null;
  }

  /**
   * Extract listing title
   */
  async extractTitle(page) {
    const selectors = ['h1[class*="title"]', 'h1', '[data-qa="title"]', '[class*="posting-title"]'];

    for (const selector of selectors) {
      const text = await this.extractText(page, selector);
      if (text && text.length > 5) {
        return text;
      }
    }

    return null;
  }

  /**
   * Extract listing description
   */
  async extractDescription(page) {
    const selectors = [
      '[data-qa="description"]',
      '[class*="description-content"]',
      '[class*="posting-description"]',
      '#description',
      '[itemprop="description"]',
    ];

    for (const selector of selectors) {
      const text = await this.extractText(page, selector);
      if (text && text.length > 20) {
        return text;
      }
    }

    return null;
  }

  /**
   * Extract price and operation data
   */
  async extractPriceData(page) {
    const operations = [];
    let maintenanceFee = null;

    // Try to find price elements
    const priceText = await this.extractText(page, '[data-qa="price"], [class*="price-value"], [class*="Price"]');

    if (priceText) {
      const price = this.extractPrice(priceText);

      if (price) {
        // Determine operation type from page content
        const pageText = await page.evaluate(() => document.body.textContent.toLowerCase());
        const isRent =
          pageText.includes('renta') || pageText.includes('alquiler') || priceText.toLowerCase().includes('/mes');
        const isSale = pageText.includes('venta') && !isRent;

        operations.push({
          type: isRent ? 'rent' : 'sale',
          price: price.amount,
          currency: price.currency,
        });
      }
    }

    // Look for maintenance fee
    const maintenanceText = await this.extractText(
      page,
      '[class*="maintenance"], [class*="expensas"], [data-qa="expenses"]'
    );
    if (maintenanceText) {
      maintenanceFee = this.extractPrice(maintenanceText);
    }

    return { operations, maintenanceFee };
  }

  /**
   * Extract location data
   */
  async extractLocationData(page) {
    const locationData = {
      address: null,
      colonia: null,
      city: null,
      state: null,
      postal_code: null,
    };

    // Try to get full address
    const addressText = await this.extractText(
      page,
      '[data-qa="location"], [class*="location"], [class*="address"], [itemprop="address"]'
    );

    if (addressText) {
      // Parse address components - Inmuebles24 typically uses format:
      // "Colonia, Ciudad, Estado" or "Calle #, Colonia, Ciudad"
      const parts = addressText.split(',').map((p) => p.trim());

      if (parts.length >= 2) {
        // Last part is usually state
        locationData.state = parts[parts.length - 1];
        // Second to last is usually city
        locationData.city = parts[parts.length - 2];
        // First part is usually colonia or street
        locationData.colonia = parts[0];

        if (parts.length >= 3) {
          // If we have 3+ parts, first might be street address
          locationData.address = parts[0];
          locationData.colonia = parts[1];
        }
      }
    }

    // Try to find postal code separately
    const postalMatch = addressText?.match(/\b(\d{5})\b/);
    if (postalMatch) {
      locationData.postal_code = postalMatch[1];
    }

    return locationData;
  }

  /**
   * Extract property details (bedrooms, bathrooms, etc.)
   */
  async extractPropertyDetails(page) {
    const details = {
      property_type: null,
      bedrooms: null,
      bathrooms: null,
      half_bathrooms: null,
      parking_spots: null,
      m2_built: null,
      m2_lot: null,
      floor: null,
      year_built: null,
      furnished: null,
    };

    // Use evaluate for more complex extraction
    const extractedDetails = await page.evaluate(() => {
      const result = {};

      // Find all feature/detail items
      const featureSelectors = [
        '[class*="feature"]',
        '[class*="attribute"]',
        '[class*="detail"]',
        '[data-qa*="feature"]',
        'li[class*="icon"]',
      ];

      let features = [];
      for (const selector of featureSelectors) {
        const els = document.querySelectorAll(selector);
        if (els.length > 0) {
          features = Array.from(els).map((el) => el.textContent.toLowerCase().trim());
          break;
        }
      }

      // Also check for structured data
      const allText = features.join(' ');

      // Bedrooms
      const bedroomMatch = allText.match(/(\d+)\s*(?:rec[aá]mara|dormitorio|habitaci[oó]n|cuarto)/);
      if (bedroomMatch) result.bedrooms = parseInt(bedroomMatch[1]);

      // Bathrooms
      const bathMatch = allText.match(/(\d+)\s*(?:ba[nñ]o|wc)/);
      if (bathMatch) result.bathrooms = parseInt(bathMatch[1]);

      // Half bathrooms
      const halfBathMatch = allText.match(/(\d+)\s*(?:medio ba[nñ]o|1\/2 ba[nñ]o)/);
      if (halfBathMatch) result.half_bathrooms = parseInt(halfBathMatch[1]);

      // Parking
      const parkingMatch = allText.match(/(\d+)\s*(?:estacionamiento|garage|cochera|auto)/);
      if (parkingMatch) result.parking_spots = parseInt(parkingMatch[1]);

      // Built area
      const builtMatch = allText.match(/(\d+(?:,\d+)?)\s*m[2²]\s*(?:const|cubierto|construido)/);
      if (builtMatch) result.m2_built = parseFloat(builtMatch[1].replace(',', ''));

      // Lot size
      const lotMatch = allText.match(/(\d+(?:,\d+)?)\s*m[2²]\s*(?:terreno|total|lote)/);
      if (lotMatch) result.m2_lot = parseFloat(lotMatch[1].replace(',', ''));

      // Floor
      const floorMatch = allText.match(/(?:piso|nivel)\s*(\d+)/);
      if (floorMatch) result.floor = parseInt(floorMatch[1]);

      // Age/Year built
      const ageMatch = allText.match(/(\d+)\s*a[nñ]os?\s*(?:antig[uü]edad|de construido)/);
      if (ageMatch) {
        const age = parseInt(ageMatch[1]);
        result.year_built = new Date().getFullYear() - age;
      }

      // Furnished
      result.furnished = allText.includes('amueblado');

      // Property type - check title and breadcrumbs
      const titleText = document.querySelector('h1')?.textContent.toLowerCase() || '';
      const breadcrumb =
        document.querySelector('[class*="breadcrumb"], nav[aria-label*="breadcrumb"]')?.textContent.toLowerCase() ||
        '';
      const typeText = titleText + ' ' + breadcrumb;

      if (typeText.includes('departamento') || typeText.includes('apartamento')) {
        result.property_type = 'apartment';
      } else if (typeText.includes('casa')) {
        result.property_type = 'house';
      } else if (typeText.includes('oficina')) {
        result.property_type = 'office';
      } else if (typeText.includes('local') || typeText.includes('comercial')) {
        result.property_type = 'commercial';
      } else if (typeText.includes('terreno') || typeText.includes('lote')) {
        result.property_type = 'land';
      }

      return result;
    });

    return { ...details, ...extractedDetails };
  }

  /**
   * Extract amenities list
   */
  async extractAmenities(page) {
    const amenities = await page.evaluate(() => {
      const result = [];

      // Common amenity container selectors
      const containerSelectors = ['[class*="amenities"]', '[class*="features"]', '[class*="servicios"]', 'ul.features'];

      for (const selector of containerSelectors) {
        const container = document.querySelector(selector);
        if (container) {
          const items = container.querySelectorAll('li, span, div[class*="item"]');
          items.forEach((item) => {
            const text = item.textContent.trim();
            if (text && text.length < 50) {
              // Filter out long texts
              result.push(text);
            }
          });
          if (result.length > 0) break;
        }
      }

      return result;
    });

    return amenities;
  }

  /**
   * Extract image URLs
   */
  async extractImages(page) {
    const images = await page.evaluate(() => {
      const result = [];
      const seen = new Set();

      // Find images in gallery/carousel
      const imgSelectors = [
        '[class*="gallery"] img',
        '[class*="carousel"] img',
        '[class*="slider"] img',
        '[data-qa="gallery"] img',
        'picture source',
        'img[class*="photo"]',
      ];

      for (const selector of imgSelectors) {
        const elements = document.querySelectorAll(selector);
        elements.forEach((el) => {
          // Get the best quality URL
          const src =
            el.getAttribute('data-src') ||
            el.getAttribute('data-lazy') ||
            el.getAttribute('srcset')?.split(',')[0]?.split(' ')[0] ||
            el.getAttribute('src');

          if (src && !seen.has(src)) {
            // Filter out tiny images/icons
            if (!src.includes('icon') && !src.includes('logo') && !src.includes('placeholder')) {
              seen.add(src);
              // Convert relative URLs to absolute
              try {
                result.push(new URL(src, window.location.origin).href);
              } catch {
                result.push(src);
              }
            }
          }
        });
      }

      return result;
    });

    return images;
  }

  /**
   * Extract contact information
   */
  async extractContactData(page) {
    const contactData = {
      phones: [],
      agent_name: null,
      agency_name: null,
    };

    // Extract phone numbers
    const phoneTexts = await this.extractAllTexts(page, '[class*="phone"] a, a[href^="tel:"], [data-qa*="phone"]');

    for (const phoneText of phoneTexts) {
      const normalized = this.normalizePhone(phoneText);
      if (normalized && !contactData.phones.includes(normalized)) {
        contactData.phones.push(normalized);
      }
    }

    // Also look for phones in text
    const contactSection = await this.extractText(page, '[class*="contact"], [class*="publisher"]');
    if (contactSection) {
      const phoneMatches = contactSection.match(/[\d\s\-()]+/g);
      if (phoneMatches) {
        for (const match of phoneMatches) {
          if (match.replace(/\D/g, '').length >= 10) {
            const normalized = this.normalizePhone(match);
            if (normalized && !contactData.phones.includes(normalized)) {
              contactData.phones.push(normalized);
            }
          }
        }
      }
    }

    // Extract agent name
    contactData.agent_name = await this.extractText(
      page,
      '[class*="agent-name"], [data-qa="publisher-name"], [class*="contact-name"]'
    );

    // Extract agency name
    contactData.agency_name = await this.extractText(
      page,
      '[class*="agency-name"], [class*="real-estate"], [class*="inmobiliaria"]'
    );

    return contactData;
  }
}

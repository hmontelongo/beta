/**
 * TEMPLATE SCRAPER
 *
 * Copy this file and customize for new platforms.
 * Replace PLATFORM with the actual platform name (e.g., Vivanuncios, MercadoLibre)
 */

import { writeFile, mkdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { BaseScraper } from './BaseScraper.js';
import { fetchSearchPage, fetchListingPage } from '../utils/zenrows.js';
// import config from '../config/PLATFORM.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DEBUG_DIR = join(__dirname, '../../debug');

/**
 * Scraper for PLATFORM listings
 *
 * Key methods to implement:
 * - scrapeSingle(url) - Scrape a single listing page
 * - scrapeSearch(url, max) - Scrape a search results page
 * - extractListingData(page, url, externalId) - Extract data from listing page
 * - extractSearchResults(page, max, baseUrl) - Extract listings from search page
 */
export class PlatformScraper extends BaseScraper {
  constructor(browser, options = {}) {
    super(browser, options);
    this.platform = 'PLATFORM'; // Change to platform name
    // this.config = config;
    this.debug = options.debug || false;
    this.warnings = [];
  }

  // ==========================================
  // MAIN SCRAPING METHODS
  // ==========================================

  /**
   * Scrape a single listing page using ZenRows
   */
  async scrapeSingle(url) {
    const page = await this.createPage();
    this.warnings = [];

    try {
      console.log('[scraper] Using ZenRows for Cloudflare bypass');

      // Fetch HTML via ZenRows
      const html = await fetchListingPage(url);

      // Load HTML into Playwright page for parsing
      await page.setContent(html, { waitUntil: 'domcontentloaded' });

      // Extract external ID from URL
      const externalId = this.extractExternalId(url);

      // Save debug files if enabled
      const debugInfo = this.debug ? await this.saveDebugFiles(page, externalId) : null;

      // Extract listing data
      const data = await this.extractListingData(page, url, externalId);

      return {
        data,
        debug: debugInfo,
      };
    } finally {
      await page.close();
    }
  }

  /**
   * Scrape a search results page using ZenRows
   * Returns listings AND pagination info
   */
  async scrapeSearch(url, max = 10) {
    const page = await this.createPage();

    try {
      console.log('[scraper] Using ZenRows for Cloudflare bypass');

      // Fetch HTML via ZenRows
      const html = await fetchSearchPage(url);

      // Load HTML into Playwright page for parsing
      await page.setContent(html, { waitUntil: 'domcontentloaded' });

      // Extract listings BEFORE any operations that might trigger JS
      const baseUrl = new URL(url).origin;
      const result = await this.extractSearchResults(page, max, baseUrl);

      // Save debug files if enabled
      const debugInfo = this.debug ? await this.saveDebugFiles(page, 'search') : null;

      return {
        data: result.listings,
        pagination: result.pagination,
        debug: debugInfo,
      };
    } finally {
      await page.close();
    }
  }

  // ==========================================
  // EXTRACTION METHODS - IMPLEMENT THESE
  // ==========================================

  /**
   * Extract external ID from URL
   * Example: https://platform.com/listing/12345 → "12345"
   */
  extractExternalId(url) {
    // Customize this regex for your platform's URL structure
    const match = url.match(/(\d{6,})/);
    return match ? match[1] : null;
  }

  /**
   * Extract all data from a listing page
   * This is the main extraction method - customize for each platform
   */
  async extractListingData(page, url, externalId) {
    // Get raw HTML for JavaScript extraction
    const html = await page.content();

    // Extract from JavaScript variables embedded in page
    const jsData = this.extractFromJavaScript(html);

    // Extract from HTML using selectors
    const title = await this.extractTitle(page);
    const description = await this.extractDescription(page);
    const features = await this.extractFeatures(page);
    const location = await this.extractLocation(page);
    const images = await this.extractImages(page);
    const contact = await this.extractContact(page);

    return {
      external_id: externalId,
      original_url: url,
      title,
      description,

      // Pricing
      operations: [{
        type: jsData.operationType || 'rent',
        price: jsData.price,
        currency: jsData.currency || 'MXN',
        maintenance_fee: null,
      }],

      // Features
      bedrooms: features.bedrooms,
      bathrooms: features.bathrooms,
      half_bathrooms: features.half_bathrooms,
      parking_spots: features.parking_spots,
      lot_size_m2: features.lot_size_m2,
      built_size_m2: features.built_size_m2,
      age_years: features.age_years,
      property_type: features.property_type,

      // Location
      address: location.address,
      colonia: location.colonia,
      city: location.city,
      state: location.state,
      latitude: location.latitude,
      longitude: location.longitude,

      // Publisher
      publisher_id: contact.id,
      publisher_name: contact.name,
      publisher_type: contact.type,
      publisher_url: contact.url,
      whatsapp: contact.whatsapp,

      // Images
      images,

      // Amenities
      amenities: [],
    };
  }

  /**
   * Extract listings from a search results page
   */
  async extractSearchResults(page, max = 10, baseUrl = '') {
    const result = await page.evaluate(({ maxResults, base }) => {
      const listings = [];
      const seenUrls = new Set();

      // TODO: Update these selectors for your platform
      const cardSelectors = [
        '[data-qa="posting"]',
        '[class*="listing-card"]',
        '[class*="property-card"]',
        'article[class*="listing"]',
      ];

      let cards = [];
      for (const selector of cardSelectors) {
        cards = document.querySelectorAll(selector);
        if (cards.length > 0) break;
      }

      for (const card of cards) {
        if (listings.length >= maxResults) break;

        // TODO: Update link selector for your platform
        const linkEl = card.querySelector('a[href*="/listing/"], a[href*="/property/"]');
        if (!linkEl) continue;

        let url = linkEl.href;
        if (url.startsWith('/')) {
          url = base + url;
        }

        // Skip duplicates
        if (seenUrls.has(url)) continue;
        seenUrls.add(url);

        // TODO: Update ID extraction for your platform
        const idMatch = url.match(/(\d{6,})/);
        const externalId = idMatch ? idMatch[1] : null;

        if (externalId) {
          listings.push({ url, external_id: externalId });
        }
      }

      // TODO: Extract pagination info for your platform
      const pagination = {
        totalResults: null,
        totalPages: null,
        currentPage: 1,
      };

      return { listings, pagination };
    }, { maxResults: max, base: baseUrl });

    return result;
  }

  // ==========================================
  // HELPER EXTRACTION METHODS
  // ==========================================

  /**
   * Extract data from JavaScript variables in the page
   */
  extractFromJavaScript(html) {
    const data = {
      price: null,
      currency: null,
      operationType: null,
    };

    try {
      // TODO: Add patterns specific to your platform
      // Example:
      // const priceMatch = html.match(/"price":\s*(\d+)/);
      // if (priceMatch) data.price = parseInt(priceMatch[1]);
    } catch (error) {
      this.warnings.push(`js_extraction_error: ${error.message}`);
    }

    return data;
  }

  /**
   * Extract title from page
   */
  async extractTitle(page) {
    const selectors = [
      'h1[class*="title"]',
      'h1[data-qa="title"]',
      'h1',
    ];

    for (const selector of selectors) {
      const text = await this.extractText(page, selector);
      if (text) return text;
    }
    return null;
  }

  /**
   * Extract description from page
   */
  async extractDescription(page) {
    const selectors = [
      '[data-qa="description"]',
      '[class*="description"]',
      '#description',
    ];

    for (const selector of selectors) {
      const text = await this.extractText(page, selector);
      if (text) return text;
    }
    return null;
  }

  /**
   * Extract property features (bedrooms, bathrooms, etc.)
   */
  async extractFeatures(page) {
    return {
      bedrooms: null,
      bathrooms: null,
      half_bathrooms: null,
      parking_spots: null,
      lot_size_m2: null,
      built_size_m2: null,
      age_years: null,
      property_type: null,
    };
  }

  /**
   * Extract location information
   */
  async extractLocation(page) {
    return {
      address: null,
      colonia: null,
      city: null,
      state: null,
      latitude: null,
      longitude: null,
    };
  }

  /**
   * Extract images
   */
  async extractImages(page) {
    // TODO: Implement for your platform
    return [];
  }

  /**
   * Extract contact/publisher information
   */
  async extractContact(page) {
    return {
      id: null,
      name: null,
      type: null,
      url: null,
      whatsapp: null,
    };
  }

  // ==========================================
  // DEBUG METHODS
  // ==========================================

  /**
   * Save debug files (HTML and screenshot)
   */
  async saveDebugFiles(page, identifier) {
    try {
      await mkdir(DEBUG_DIR, { recursive: true });

      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const prefix = `${this.platform}-${identifier}-${timestamp}`;

      // Save HTML
      const html = await page.content();
      const htmlPath = join(DEBUG_DIR, `${prefix}.html`);
      await writeFile(htmlPath, html);

      // Save screenshot
      const screenshotPath = join(DEBUG_DIR, `${prefix}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: false });

      return { html: htmlPath, screenshot: screenshotPath };
    } catch (error) {
      console.error('[debug] Failed to save debug files:', error.message);
      return null;
    }
  }
}

export default PlatformScraper;

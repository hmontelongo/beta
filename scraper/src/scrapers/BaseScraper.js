import { createPage } from '../utils/browser.js';

/**
 * Base scraper class with shared functionality
 */
export class BaseScraper {
  constructor(browser, options = {}) {
    this.browser = browser;
    this.forceNewContext = options.forceNewContext || false;
  }

  /**
   * Scrape a single listing page - must be implemented by subclass
   */
  async scrapeSingle(url) {
    throw new Error('scrapeSingle() must be implemented by subclass');
  }

  /**
   * Scrape a search results page - must be implemented by subclass
   */
  async scrapeSearch(url, max) {
    throw new Error('scrapeSearch() must be implemented by subclass');
  }

  /**
   * Create a new page with proper settings
   */
  async createPage() {
    return createPage(this.browser, this.forceNewContext);
  }

  /**
   * Wait for a selector with custom timeout
   */
  async waitForSelector(page, selector, timeout = 30000) {
    try {
      await page.waitForSelector(selector, { timeout });
      return true;
    } catch (error) {
      return false;
    }
  }

  /**
   * Extract text content from a selector, returns null if not found
   */
  async extractText(page, selector) {
    try {
      const element = page.locator(selector).first();
      const text = await element.textContent({ timeout: 5000 });
      return text ? text.trim() : null;
    } catch (error) {
      return null;
    }
  }

  /**
   * Extract all text contents from matching selectors
   */
  async extractAllTexts(page, selector) {
    try {
      const elements = page.locator(selector);
      const count = await elements.count();
      const texts = [];

      for (let i = 0; i < count; i++) {
        const text = await elements.nth(i).textContent({ timeout: 5000 });
        if (text) {
          texts.push(text.trim());
        }
      }

      return texts;
    } catch (error) {
      return [];
    }
  }

  /**
   * Extract an attribute from a selector
   */
  async extractAttribute(page, selector, attribute) {
    try {
      const element = page.locator(selector).first();
      const value = await element.getAttribute(attribute, { timeout: 5000 });
      return value || null;
    } catch (error) {
      return null;
    }
  }

  /**
   * Extract all attribute values from matching selectors
   */
  async extractAllAttributes(page, selector, attribute) {
    try {
      const elements = page.locator(selector);
      const count = await elements.count();
      const values = [];

      for (let i = 0; i < count; i++) {
        const value = await elements.nth(i).getAttribute(attribute, { timeout: 5000 });
        if (value) {
          values.push(value);
        }
      }

      return values;
    } catch (error) {
      return [];
    }
  }

  /**
   * Extract a number from text like "3 recámaras" → 3
   */
  extractNumber(text) {
    if (!text) return null;

    // Match digits (including decimals)
    const match = text.match(/[\d,]+(?:\.\d+)?/);
    if (match) {
      // Remove commas and parse
      return parseFloat(match[0].replace(/,/g, ''));
    }

    return null;
  }

  /**
   * Extract price from text like "$25,000/mes" → {amount: 25000, currency: "MXN"}
   */
  extractPrice(text) {
    if (!text) return null;

    // Remove whitespace and normalize
    const normalized = text.replace(/\s+/g, '');

    // Match price pattern: optional currency symbol, number with commas, optional decimals
    const match = normalized.match(/\$?([\d,]+(?:\.\d{2})?)/);
    if (match) {
      const amount = parseFloat(match[1].replace(/,/g, ''));

      // Detect currency - default to MXN for Mexican sites
      let currency = 'MXN';
      if (normalized.toLowerCase().includes('usd') || normalized.includes('US$')) {
        currency = 'USD';
      }

      return { amount, currency };
    }

    return null;
  }

  /**
   * Normalize Mexican phone number to +52 format
   */
  normalizePhone(phone) {
    if (!phone) return null;

    // Remove all non-digit characters
    let digits = phone.replace(/\D/g, '');

    // Handle different formats
    if (digits.length === 10) {
      // Local format: 3312345678
      return `+52${digits}`;
    } else if (digits.length === 12 && digits.startsWith('52')) {
      // Already has country code without +
      return `+${digits}`;
    } else if (digits.length === 13 && digits.startsWith('521')) {
      // Has country code with mobile prefix
      return `+52${digits.slice(3)}`;
    }

    // Return original if we can't normalize
    return phone;
  }

  /**
   * Extract coordinates from various sources in the page
   */
  async extractCoordinates(page) {
    try {
      // Try to find coordinates in page scripts
      const coords = await page.evaluate(() => {
        // Look for common patterns in scripts
        const scripts = document.querySelectorAll('script');
        for (const script of scripts) {
          const content = script.textContent || '';

          // Pattern 1: lat/lng in JSON
          const jsonMatch = content.match(/"latitude":\s*([-\d.]+).*?"longitude":\s*([-\d.]+)/);
          if (jsonMatch) {
            return {
              latitude: parseFloat(jsonMatch[1]),
              longitude: parseFloat(jsonMatch[2]),
            };
          }

          // Pattern 2: lat, lng variables
          const varMatch = content.match(/lat(?:itude)?['":\s]+([-\d.]+).*?lng|lon(?:gitude)?['":\s]+([-\d.]+)/i);
          if (varMatch) {
            return {
              latitude: parseFloat(varMatch[1]),
              longitude: parseFloat(varMatch[2]),
            };
          }
        }

        // Look for data attributes on map elements
        const mapEl = document.querySelector('[data-lat][data-lng], [data-latitude][data-longitude]');
        if (mapEl) {
          const lat = mapEl.getAttribute('data-lat') || mapEl.getAttribute('data-latitude');
          const lng = mapEl.getAttribute('data-lng') || mapEl.getAttribute('data-longitude');
          if (lat && lng) {
            return {
              latitude: parseFloat(lat),
              longitude: parseFloat(lng),
            };
          }
        }

        return null;
      });

      return coords;
    } catch (error) {
      return null;
    }
  }
}

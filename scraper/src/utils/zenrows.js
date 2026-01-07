/**
 * ZenRows API integration for Cloudflare bypass
 * ZenRows handles anti-bot protection and returns clean HTML
 */

import axios from 'axios';

const ZENROWS_API_KEY = process.env.ZENROWS_API_KEY;
const ZENROWS_BASE_URL = 'https://api.zenrows.com/v1/';

/**
 * Check if ZenRows is configured
 */
export function isZenRowsConfigured() {
  return Boolean(ZENROWS_API_KEY);
}

/**
 * Fetch a URL through ZenRows API
 * @param {string} url - The URL to fetch
 * @param {Object} options - Additional options
 * @returns {Promise<{html: string, statusCode: number}>}
 */
export async function fetchWithZenRows(url, options = {}) {
  if (!ZENROWS_API_KEY) {
    throw new Error('ZENROWS_API_KEY environment variable is not set');
  }

  const {
    jsRender = true,        // Enable JavaScript rendering (needed for dynamic content)
    premiumProxy = true,    // Use premium residential proxies
    timeout = 90000,        // Request timeout in ms
    waitFor = null,         // CSS selector to wait for
    wait = null,            // Wait time in ms after page load
  } = options;

  console.log(`[zenrows] Fetching: ${url}`);
  const startTime = Date.now();

  try {
    const params = new URLSearchParams({
      apikey: ZENROWS_API_KEY,
      url: url,
      js_render: jsRender.toString(),
      premium_proxy: premiumProxy.toString(),
    });

    // Wait for a specific element to appear (critical for SPAs)
    if (waitFor) {
      params.append('wait_for', waitFor);
    }

    // Wait additional time after page load
    if (wait) {
      params.append('wait', wait.toString());
    }

    const response = await axios.get(`${ZENROWS_BASE_URL}?${params.toString()}`, {
      timeout,
      validateStatus: (status) => status < 500, // Don't throw on 4xx
    });

    const elapsed = Date.now() - startTime;
    console.log(`[zenrows] Success in ${elapsed}ms (${response.status})`);

    if (response.status >= 400) {
      throw new Error(`ZenRows returned status ${response.status}: ${response.data}`);
    }

    return {
      html: response.data,
      statusCode: response.status,
    };
  } catch (error) {
    const elapsed = Date.now() - startTime;

    if (error.response) {
      console.error(`[zenrows] Error in ${elapsed}ms: ${error.response.status} - ${error.response.data}`);
      throw new Error(`ZenRows error: ${error.response.status} - ${typeof error.response.data === 'string' ? error.response.data.substring(0, 200) : JSON.stringify(error.response.data)}`);
    }

    console.error(`[zenrows] Request failed in ${elapsed}ms: ${error.message}`);
    throw error;
  }
}

/**
 * Fetch a search page through ZenRows
 * @param {string} url - Search URL
 * @returns {Promise<string>} HTML content
 */
export async function fetchSearchPage(url) {
  const result = await fetchWithZenRows(url, {
    jsRender: true,
    premiumProxy: true,
    // Wait for listing cards to render using data-qa attribute (more stable)
    waitFor: '[data-qa^="posting"]',
    // Additional wait time for all content to load
    wait: 5000,
  });
  return result.html;
}

/**
 * Fetch a listing page through ZenRows
 * @param {string} url - Listing URL
 * @returns {Promise<string>} HTML content
 */
export async function fetchListingPage(url) {
  const result = await fetchWithZenRows(url, {
    jsRender: true,
    premiumProxy: true,
    // Wait for main content to load
    waitFor: 'h1',
    wait: 2000,
  });
  return result.html;
}

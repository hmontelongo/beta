#!/usr/bin/env node

import 'dotenv/config';
import { createServer } from 'http';
import { URL } from 'url';
import {
  launchBrowser,
  closeBrowser,
  randomDelay,
  getBackoffDelay,
} from './utils/browser.js';
// CapSolver removed - ZenRows handles Cloudflare bypass
import { isZenRowsConfigured } from './utils/zenrows.js';
import { Inmuebles24Scraper } from './scrapers/Inmuebles24.js';

const PORT = process.env.PORT || 3000;
// Rate limiting disabled by default - ZenRows handles anti-bot protection
// Set RATE_LIMIT=true to enable delays between requests
const RATE_LIMIT_ENABLED = process.env.RATE_LIMIT === 'true';
const MIN_DELAY_MS = RATE_LIMIT_ENABLED ? parseInt(process.env.MIN_DELAY_MS || '2000', 10) : 0;
const MAX_DELAY_MS = RATE_LIMIT_ENABLED ? parseInt(process.env.MAX_DELAY_MS || '5000', 10) : 0;
const HEADED_MODE = process.env.HEADED === 'true'; // Run with visible browser

// Available scrapers
const SCRAPERS = {
  inmuebles24: Inmuebles24Scraper,
};

let browser = null;
let lastRequestTime = 0;
let requestQueue = Promise.resolve();
let isWarmedUp = false;

/**
 * Rate limit requests (bypassed when RATE_LIMIT_ENABLED is false)
 */
async function withRateLimit(fn) {
  // When rate limiting is disabled, execute immediately
  if (!RATE_LIMIT_ENABLED) {
    return fn();
  }

  // Queue requests to run sequentially with delay
  requestQueue = requestQueue.then(async () => {
    const now = Date.now();
    const elapsed = now - lastRequestTime;
    const delay = Math.max(0, randomDelay(MIN_DELAY_MS, MAX_DELAY_MS) - elapsed);

    if (delay > 0) {
      console.log(`[rate-limit] Waiting ${Math.round(delay / 1000)}s before next request...`);
      await new Promise(resolve => setTimeout(resolve, delay));
    }

    lastRequestTime = Date.now();
    return fn();
  });

  return requestQueue;
}

/**
 * Send JSON response
 */
function sendJson(res, data, status = 200) {
  res.writeHead(status, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(data));
}

/**
 * Send error response
 */
function sendError(res, message, status = 500) {
  sendJson(res, { error: message }, status);
}

/**
 * Get scraper for URL
 */
function getScraperForUrl(url) {
  if (url.includes('inmuebles24.com')) {
    return { name: 'inmuebles24', Scraper: Inmuebles24Scraper };
  }
  return null;
}

/**
 * Handle /discover endpoint with retry on Cloudflare blocking
 * Returns: { total_results, total_pages, listings: [{url, external_id}] }
 */
async function handleDiscover(req, res, params) {
  const targetUrl = params.get('url');
  const page = parseInt(params.get('page') || '1', 10);

  if (!targetUrl) {
    return sendError(res, 'Missing url parameter', 400);
  }

  const scraperInfo = getScraperForUrl(targetUrl);
  if (!scraperInfo) {
    return sendError(res, 'Unknown platform for URL', 400);
  }

  // Add pagination to URL if needed
  // Inmuebles24 uses path-based pagination: /inmuebles-en-renta-en-jalisco-pagina-2.html
  let paginatedUrl = targetUrl;
  if (page > 1) {
    // Insert -pagina-N before .html
    paginatedUrl = targetUrl.replace(/\.html$/, `-pagina-${page}.html`);
  }

  console.log(`[discover] Page ${page}: ${paginatedUrl}`);

  try {
    const searchResult = await withRateLimit(async () => {
      const scraper = new scraperInfo.Scraper(browser, { debug: true });
      return scraper.scrapeSearch(paginatedUrl, 50);
    });

    return sendJson(res, {
      total_results: searchResult.pagination?.totalResults || 0,
      total_pages: searchResult.pagination?.totalPages || 1,
      listings: searchResult.data.map(item => ({
        url: item.url,
        external_id: item.external_id,
      })),
    });
  } catch (error) {
    console.error(`[discover] Failed: ${error.message}`);
    sendError(res, error.message);
  }
}

/**
 * Handle /scrape endpoint
 * Returns: full listing data
 */
async function handleScrape(req, res, params) {
  const targetUrl = params.get('url');

  if (!targetUrl) {
    return sendError(res, 'Missing url parameter', 400);
  }

  const scraperInfo = getScraperForUrl(targetUrl);
  if (!scraperInfo) {
    return sendError(res, 'Unknown platform for URL', 400);
  }

  console.log(`[scrape] ${targetUrl}`);

  try {
    const result = await withRateLimit(async () => {
      const scraper = new scraperInfo.Scraper(browser, { debug: false });
      return scraper.scrapeSingle(targetUrl);
    });

    return sendJson(res, result.data);
  } catch (error) {
    console.error(`[scrape] Failed: ${error.message}`);
    sendError(res, error.message);
  }
}

/**
 * Handle /health endpoint
 */
function handleHealth(req, res) {
  sendJson(res, {
    status: 'ok',
    browser: browser ? 'running' : 'stopped',
    warmed_up: isWarmedUp,
    headed_mode: HEADED_MODE,
  });
}

/**
 * Request handler
 */
async function handleRequest(req, res) {
  const url = new URL(req.url, `http://localhost:${PORT}`);
  const params = url.searchParams;

  console.log(`${req.method} ${url.pathname}`);

  try {
    switch (url.pathname) {
      case '/discover':
        await handleDiscover(req, res, params);
        break;
      case '/scrape':
        await handleScrape(req, res, params);
        break;
      case '/health':
        handleHealth(req, res);
        break;
      default:
        sendError(res, 'Not found', 404);
    }
  } catch (error) {
    console.error(`Request error: ${error.message}`);
    sendError(res, error.message);
  }
}

/**
 * Start server
 */
async function start() {
  console.log('='.repeat(50));
  console.log('PropData Scraper Server');
  console.log('='.repeat(50));
  console.log(`Mode: ${HEADED_MODE ? 'HEADED (visible browser)' : 'HEADLESS'}`);
  console.log(`Rate limiting: ${MIN_DELAY_MS / 1000}s - ${MAX_DELAY_MS / 1000}s between requests`);

  // ZenRows status (primary bypass method)
  if (isZenRowsConfigured()) {
    console.log('ZenRows: CONFIGURED ✓ (Cloudflare will be bypassed via API)');
  } else {
    console.log('ZenRows: NOT CONFIGURED');
    console.log('  → Set ZENROWS_API_KEY env var to enable Cloudflare bypass');
    console.log('  → Get API key at: https://www.zenrows.com/');
  }

  console.log('');

  console.log('[startup] Launching browser...');
  browser = await launchBrowser({ headless: !HEADED_MODE });
  console.log('[startup] ZenRows configured - skipping browser warmup');
  isWarmedUp = true;

  const server = createServer(handleRequest);

  server.listen(PORT, () => {
    console.log('');
    console.log(`Scraper server running on http://localhost:${PORT}`);
    console.log('Endpoints:');
    console.log('  GET /discover?url=<search_url>&page=<page>');
    console.log('  GET /scrape?url=<listing_url>');
    console.log('  GET /health');
    console.log('');
    console.log('Environment variables:');
    console.log('  ZENROWS_API_KEY    - ZenRows API key for Cloudflare bypass (required)');
    console.log('  HEADED=true        - Run with visible browser');
    console.log('  MIN_DELAY_MS       - Min delay between requests (default: 30000)');
    console.log('  MAX_DELAY_MS       - Max delay between requests (default: 60000)');
    console.log('='.repeat(50));
  });

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\nShutting down...');
    server.close();
    await closeBrowser(browser);
    process.exit(0);
  });

  process.on('SIGTERM', async () => {
    console.log('\nShutting down...');
    server.close();
    await closeBrowser(browser);
    process.exit(0);
  });
}

start().catch(error => {
  console.error('Failed to start server:', error);
  process.exit(1);
});

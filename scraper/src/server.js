#!/usr/bin/env node

import { createServer } from 'http';
import { URL } from 'url';
import { launchBrowser, closeBrowser } from './utils/browser.js';
import { Inmuebles24Scraper } from './scrapers/Inmuebles24.js';

const PORT = process.env.PORT || 3000;
const REQUEST_DELAY_MS = parseInt(process.env.REQUEST_DELAY_MS || '15000', 10); // 15 seconds to avoid bot detection

// Available scrapers
const SCRAPERS = {
  inmuebles24: Inmuebles24Scraper,
};

let browser = null;
let lastRequestTime = 0;
let requestQueue = Promise.resolve();

/**
 * Rate limit requests to avoid being blocked
 */
async function withRateLimit(fn) {
  // Queue requests to run sequentially with delay
  requestQueue = requestQueue.then(async () => {
    const now = Date.now();
    const elapsed = now - lastRequestTime;
    const delay = Math.max(0, REQUEST_DELAY_MS - elapsed);

    if (delay > 0) {
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
 * Handle /discover endpoint
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
      const scraper = new scraperInfo.Scraper(browser, { debug: true }); // Enable debug for screenshots
      // scrapeSearch now returns pagination info along with listings
      return scraper.scrapeSearch(paginatedUrl, 50);
    });

    sendJson(res, {
      total_results: searchResult.pagination?.totalResults || 0,
      total_pages: searchResult.pagination?.totalPages || 1,
      listings: searchResult.data.map(item => ({
        url: item.url,
        external_id: item.external_id,
      })),
    });
  } catch (error) {
    console.error(`[discover] Error: ${error.message}`);
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

    sendJson(res, result.data);
  } catch (error) {
    console.error(`[scrape] Error: ${error.message}`);
    sendError(res, error.message);
  }
}

/**
 * Handle /health endpoint
 */
function handleHealth(req, res) {
  sendJson(res, { status: 'ok', browser: browser ? 'running' : 'stopped' });
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
  console.log('Launching browser...');
  browser = await launchBrowser();

  const server = createServer(handleRequest);

  server.listen(PORT, () => {
    console.log(`Scraper server running on http://localhost:${PORT}`);
    console.log('Endpoints:');
    console.log('  GET /discover?url=<search_url>&page=<page>');
    console.log('  GET /scrape?url=<listing_url>');
    console.log('  GET /health');
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

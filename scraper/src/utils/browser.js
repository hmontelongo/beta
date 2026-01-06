import { chromium } from 'playwright';

// Persistent browser context for session reuse
let persistentContext = null;

// User agent rotation pool
const USER_AGENTS = [
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
];

/**
 * Launch a Chromium browser instance with stealth settings
 */
export async function launchBrowser() {
  return chromium.launch({
    headless: true,
    args: [
      '--disable-blink-features=AutomationControlled',
      '--disable-features=IsolateOrigins,site-per-process',
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--disable-gpu',
      '--window-size=1920,1080',
      '--start-maximized',
    ],
  });
}

/**
 * Get or create a persistent browser context
 */
async function getOrCreateContext(browser) {
  if (!persistentContext || persistentContext.browser() !== browser) {
    const userAgent = USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)];

    persistentContext = await browser.newContext({
      viewport: { width: 1280, height: 800 },
      locale: 'es-MX',
      timezoneId: 'America/Mexico_City',
      userAgent,
      // Accept cookies
      extraHTTPHeaders: {
        'Accept-Language': 'es-MX,es;q=0.9,en;q=0.8',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Encoding': 'gzip, deflate, br',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
      },
      // Permissions
      permissions: ['geolocation'],
      geolocation: { latitude: 20.6597, longitude: -103.3496 }, // Guadalajara
    });

    // Add stealth scripts to context
    await persistentContext.addInitScript(() => {
      // Override webdriver detection
      Object.defineProperty(navigator, 'webdriver', { get: () => false });

      // Override automation flags
      window.chrome = { runtime: {} };

      // Override plugins
      Object.defineProperty(navigator, 'plugins', {
        get: () => [1, 2, 3, 4, 5],
      });

      // Override languages
      Object.defineProperty(navigator, 'languages', {
        get: () => ['es-MX', 'es', 'en'],
      });
    });
  }

  return persistentContext;
}

/**
 * Create a new page with Mexican locale and timezone settings
 * Reuses persistent context to maintain session/cookies
 */
export async function createPage(browser) {
  const context = await getOrCreateContext(browser);
  const page = await context.newPage();

  // Set default timeouts
  page.setDefaultTimeout(30000);
  page.setDefaultNavigationTimeout(30000);

  return page;
}

/**
 * Safely close browser instance
 */
export async function closeBrowser(browser) {
  if (browser) {
    try {
      await browser.close();
    } catch (error) {
      console.error('Error closing browser:', error.message);
    }
  }
}

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Persistent context - reused across requests
let currentContext = null;

// Session configuration
const SESSION_CONFIG = {
  viewport: { width: 1920, height: 1080 },
  locale: 'es-MX',
  timezoneId: 'America/Mexico_City',
};

/**
 * Launch a Chromium browser with persistent context
 * Uses a user data directory for persistent cookies/state
 */
export async function launchBrowser(options = {}) {
  const headed = options.headless === false;
  const channel = process.env.BROWSER_CHANNEL || 'chrome';
  const userDataDir = join(__dirname, '../../.chrome-profile');

  console.log(`[browser] Launching ${headed ? 'headed' : 'headless'} mode, channel: ${channel}`);
  console.log(`[browser] Using persistent profile: ${userDataDir}`);

  // Ensure user data directory exists
  if (!existsSync(userDataDir)) {
    mkdirSync(userDataDir, { recursive: true });
  }

  // Use launchPersistentContext for persistent cookies/state across sessions
  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: !headed,
    channel: channel,
    viewport: SESSION_CONFIG.viewport,
    locale: SESSION_CONFIG.locale,
    timezoneId: SESSION_CONFIG.timezoneId,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
    ],
  });

  currentContext = context;

  // Return an object that mimics browser API
  return {
    _context: context,
    _isPersistent: true,
    newContext: async () => context,
    close: async () => context.close(),
  };
}

/**
 * Get the persistent browser context
 */
export function getContext(browser) {
  if (browser._isPersistent) {
    return browser._context;
  }
  return currentContext;
}

/**
 * Create a new page with consistent settings
 */
export async function createPage(browser) {
  const context = getContext(browser);
  const page = await context.newPage();

  page.setDefaultTimeout(60000);
  page.setDefaultNavigationTimeout(60000);

  return page;
}

/**
 * Add a random delay to appear more human-like
 */
export function randomDelay(minMs = 2000, maxMs = 5000) {
  return Math.floor(Math.random() * (maxMs - minMs)) + minMs;
}

/**
 * Calculate exponential backoff delay
 */
export function getBackoffDelay(attempts = 0, baseDelayMs = 30000) {
  if (attempts === 0) return baseDelayMs;
  // Exponential backoff: 30s, 60s, 120s, max 5min
  const delay = Math.min(baseDelayMs * Math.pow(2, attempts), 5 * 60 * 1000);
  console.log(`[backoff] Delay: ${Math.round(delay / 1000)}s (attempts: ${attempts})`);
  return delay;
}

/**
 * Safely close browser instance
 */
export async function closeBrowser(browser) {
  if (browser) {
    try {
      if (browser._isPersistent) {
        console.log('[browser] Closing persistent browser context');
        currentContext = null;
        await browser.close();
      } else if (currentContext) {
        await currentContext.close();
        currentContext = null;
      }
    } catch (error) {
      console.error('[browser] Error closing:', error.message);
    }
  }
}

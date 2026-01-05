import { chromium } from 'playwright';

/**
 * Launch a headless Chromium browser instance
 */
export async function launchBrowser() {
  return chromium.launch({
    headless: true,
  });
}

/**
 * Create a new page with Mexican locale and timezone settings
 */
export async function createPage(browser) {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    locale: 'es-MX',
    timezoneId: 'America/Mexico_City',
    userAgent:
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  });

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

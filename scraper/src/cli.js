#!/usr/bin/env node

import minimist from 'minimist';
import { launchBrowser, closeBrowser } from './utils/browser.js';
import { Inmuebles24Scraper } from './scrapers/Inmuebles24.js';

// Available scrapers
const SCRAPERS = {
  inmuebles24: Inmuebles24Scraper,
};

/**
 * Output JSON result to stdout
 */
function outputResult(result) {
  console.log(JSON.stringify(result, null, 2));
}

/**
 * Output error result to stdout
 */
function outputError(platform, url, error) {
  outputResult({
    status: 'error',
    platform,
    error: error.message || String(error),
    url,
  });
}

/**
 * Show usage help
 */
function showHelp() {
  console.error(`
PropData Scraper CLI

Usage:
  node src/cli.js <command> --platform=<platform> --url=<url> [options]

Commands:
  single    Scrape a single listing page
  search    Scrape a search results page

Options:
  --platform    Platform to scrape (required): inmuebles24
  --url         URL to scrape (required)
  --max         Maximum results for search command (default: 10)
  --help        Show this help message

Examples:
  node src/cli.js single --platform=inmuebles24 --url="https://www.inmuebles24.com/propiedades/..."
  node src/cli.js search --platform=inmuebles24 --url="https://www.inmuebles24.com/departamentos-en-renta-en-guadalajara.html" --max=5
`);
}

/**
 * Main CLI function
 */
async function main() {
  // Parse arguments
  const args = minimist(process.argv.slice(2), {
    string: ['platform', 'url'],
    default: {
      max: 10,
    },
    alias: {
      p: 'platform',
      u: 'url',
      m: 'max',
      h: 'help',
    },
  });

  // Get command (first positional argument)
  const [command] = args._;
  const { platform, url, max, help } = args;

  // Show help if requested
  if (help || !command) {
    showHelp();
    process.exit(command ? 0 : 1);
  }

  // Validate command
  if (!['single', 'search'].includes(command)) {
    console.error(`Error: Unknown command "${command}". Use "single" or "search".`);
    showHelp();
    process.exit(1);
  }

  // Validate required arguments
  if (!platform) {
    console.error('Error: --platform is required');
    process.exit(1);
  }

  if (!url) {
    console.error('Error: --url is required');
    process.exit(1);
  }

  // Validate platform
  const ScraperClass = SCRAPERS[platform.toLowerCase()];
  if (!ScraperClass) {
    console.error(`Error: Unknown platform "${platform}". Available: ${Object.keys(SCRAPERS).join(', ')}`);
    process.exit(1);
  }

  // Validate URL
  try {
    new URL(url);
  } catch {
    console.error(`Error: Invalid URL "${url}"`);
    process.exit(1);
  }

  let browser = null;

  try {
    // Launch browser
    console.error('Launching browser...');
    browser = await launchBrowser();

    // Create scraper instance
    const scraper = new ScraperClass(browser);

    // Execute command
    console.error(`Running ${command} scrape for ${platform}...`);
    const startTime = Date.now();

    let data;
    if (command === 'single') {
      data = await scraper.scrapeSingle(url);

      outputResult({
        status: 'completed',
        platform,
        scraped_at: new Date().toISOString(),
        data,
      });
    } else if (command === 'search') {
      data = await scraper.scrapeSearch(url, parseInt(max, 10));

      outputResult({
        status: 'completed',
        platform,
        scraped_at: new Date().toISOString(),
        count: data.length,
        data,
      });
    }

    const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
    console.error(`Completed in ${elapsed}s`);

    process.exit(0);
  } catch (error) {
    console.error(`Error: ${error.message}`);
    outputError(platform, url, error);
    process.exit(1);
  } finally {
    // Always close browser
    await closeBrowser(browser);
  }
}

// Run the CLI
main();

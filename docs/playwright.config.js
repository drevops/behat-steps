// @ts-check
// @see https://playwright.dev/docs/test-configuration

import {defineConfig} from '@playwright/test';

const PORT = 3210;

export default defineConfig({
  testDir: './tests/accessibility',
  outputDir: './.logs/playwright',
  forbidOnly: Boolean(process.env.CI),
  reporter: 'list',

  use: {
    baseURL: `http://localhost:${PORT}`,
    browserName: 'chromium',
  },

  // The interaction tests set their own viewport, so only the page scans run
  // twice.
  projects: [
    {name: 'desktop', use: {viewport: {width: 1280, height: 800}}},
    {name: 'mobile', use: {viewport: {width: 390, height: 844}}, testMatch: 'pages.spec.js'},
  ],

  // Serves build/ as it is, so 'npm run build' comes first.
  webServer: {
    command: `npm run serve -- --port ${PORT} --no-open`,
    url: `http://localhost:${PORT}`,
    reuseExistingServer: false,
  },
});

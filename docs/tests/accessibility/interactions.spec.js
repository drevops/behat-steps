import { expect, test } from '@playwright/test';
import { COLOR_MODES, open, scan } from './helpers';

const MOBILE = { width: 390, height: 844 };
const PAGE_URL = '/quick-start';

test.describe('search', () => {
  for (const colorMode of COLOR_MODES) {
    test(`dropdown in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      await page.locator('.navbar__search-input').click();
      await page.keyboard.type('cookie');
      await expect(page.getByRole('option').first()).toBeVisible();
      await expect(page.getByRole('button', { name: 'Clear search' })).toBeVisible();

      expect(await scan(page, '.navbar')).toEqual([]);
    });

    test(`dropdown without results in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      await page.locator('.navbar__search-input').click();
      await page.keyboard.type('zzqxv');
      await expect(page.getByText('No results')).toBeVisible();

      expect(await scan(page, '.navbar')).toEqual([]);
    });

    test(`highlighted result in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/guides/configuration?_highlight=backend', colorMode);
      await expect(page.locator('article mark').first()).toBeVisible();

      expect(await scan(page)).toEqual([]);
    });
  }
});

test.describe('mobile menu', () => {
  test.use({ viewport: MOBILE });

  // The home page's menu holds the navbar items, and a doc page's menu opens
  // on the sidebar.
  for (const url of ['/', '/introduction']) {
    test(`moves keyboard focus inside, keeps it there and closes on Escape on ${url}`, async ({ page }) => {
      await open(page, url, 'light');

      const toggle = page.getByRole('button', { name: 'Toggle navigation bar' });
      const sidebar = page.locator('.navbar-sidebar');
      const focusIn = () => page.evaluate(() => {
        if (document.activeElement === document.body) {
          return 'body';
        }

        return document.activeElement.closest('.navbar-sidebar') ? 'menu' : 'outside';
      });

      await toggle.focus();
      await page.keyboard.press('Enter');

      await expect.poll(focusIn).toBe('menu');

      const controls = await sidebar.evaluate((element) => Array.from(element.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')).filter((control) => !control.closest('[inert]') && control.checkVisibility({ visibilityProperty: true })).length);
      const stops = [];

      // 2 presses more than the menu has controls, so focus wraps around.
      for (let press = 0; press < controls + 2; press++) {
        await page.keyboard.press('Tab');
        stops.push(await focusIn());
      }

      // Tab past the last control leaves the page for 1 press before it wraps.
      expect(stops).not.toContain('outside');
      expect(stops.filter((stop) => stop === 'body').length).toBeLessThanOrEqual(1);
      expect(stops.slice(-2)).not.toContain('body');

      await page.keyboard.press('Escape');

      await expect(sidebar).toBeHidden();
      await expect(toggle).toBeFocused();
    });
  }

  test('closes on a click outside', async ({ page }) => {
    await open(page, '/', 'light');

    await page.getByRole('button', { name: 'Toggle navigation bar' }).click();
    await expect(page.locator('.navbar-sidebar')).toBeVisible();

    await page.mouse.click(MOBILE.width - 10, MOBILE.height / 2);

    await expect(page.locator('.navbar-sidebar')).toBeHidden();
  });

  for (const colorMode of COLOR_MODES) {
    test(`open in ${colorMode} mode`, async ({ page }) => {
      await open(page, PAGE_URL, colorMode);

      await page.getByRole('button', { name: 'Toggle navigation bar' }).click();

      // The menu fades in, and a half-transparent menu fails on contrast.
      await expect(page.locator('.navbar-sidebar')).toHaveCSS('opacity', '1');

      expect(await scan(page, '.navbar-sidebar')).toEqual([]);
    });
  }
});

test.describe('lightbox', () => {
  const DIAGRAM_PAGE = '/architecture/logical-architecture';
  const DIAGRAM = /^Logical architecture: /;

  // The scans run once the dialog has faded in.
  test.use({ reducedMotion: 'reduce' });

  // Each diagram is embedded once per color mode, and only 1 of the 2 shows.
  const diagramLink = (page) => page.locator('article a[aria-haspopup="dialog"]:visible');

  const lightbox = (page) => page.getByRole('dialog', { name: DIAGRAM });

  for (const colorMode of COLOR_MODES) {
    test(`shows the diagram of the color mode, fitted and zoomed, in ${colorMode} mode`, async ({ page }) => {
      await open(page, DIAGRAM_PAGE, colorMode);

      const link = diagramLink(page);
      const dialog = lightbox(page);

      await link.click();

      await expect(dialog).toBeVisible();
      await expect(dialog.locator('img')).toHaveAttribute('src', await link.locator('img').getAttribute('src'));
      await expect(dialog.getByRole('link', { name: 'Open the image in a new tab' })).toHaveAttribute('href', await link.getAttribute('href'));
      await expect(page.locator('html')).toHaveCSS('overflow', 'hidden');
      expect(await scan(page, 'dialog')).toEqual([]);

      await dialog.getByRole('button', { name: 'Zoom in' }).click();

      await expect(dialog.getByRole('button', { name: 'Zoom out' })).toBeVisible();
      expect(await scan(page, 'dialog')).toEqual([]);
    });
  }

  test('opens, zooms, pans and closes from the keyboard', async ({ page }) => {
    await open(page, DIAGRAM_PAGE, 'light');

    const link = diagramLink(page);
    const dialog = lightbox(page);

    await link.focus();
    await page.keyboard.press('Enter');

    await expect(dialog.getByRole('button', { name: 'Zoom in' })).toBeFocused();

    await page.keyboard.press('Enter');

    const zoomOut = dialog.getByRole('button', { name: 'Zoom out' });
    const scrollLeft = () => zoomOut.evaluate((button) => button.parentElement.scrollLeft);

    await expect(zoomOut).toBeFocused();

    const before = await scrollLeft();

    await page.keyboard.press('ArrowRight');

    await expect.poll(scrollLeft).toBeGreaterThan(before);

    await page.keyboard.press('Escape');

    await expect(dialog).toHaveCount(0);
    await expect(link).toBeFocused();
    await expect(page.locator('html')).not.toHaveCSS('overflow', 'hidden');
  });

  test('closes with the close button and with a click beside the image', async ({ page }) => {
    await open(page, DIAGRAM_PAGE, 'light');

    const link = diagramLink(page);
    const dialog = lightbox(page);

    await link.click();
    await dialog.getByRole('button', { name: 'Close' }).click();

    await expect(dialog).toHaveCount(0);

    await link.click();
    await expect(dialog).toBeVisible();
    await page.mouse.click(8, 792);

    await expect(dialog).toHaveCount(0);
  });

  test('leaves a click with a modifier key to the browser', async ({ page, context }) => {
    await open(page, DIAGRAM_PAGE, 'light');

    const link = diagramLink(page);
    const popup = context.waitForEvent('page');

    await link.click({ modifiers: ['ControlOrMeta'] });

    expect((await popup).url()).toMatch(/\/assets\/files\/logical-architecture-[0-9a-f]+\.svg$/);
    await expect(lightbox(page)).toHaveCount(0);
  });
});

test.describe('home page', () => {
  const INSTALL_COMMAND = 'composer require --dev drevops/behat-steps:^4';

  test('switches the feature file from the keyboard', async ({ page }) => {
    await open(page, '/', 'light');

    const tabs = page.getByRole('tablist', { name: 'Example feature files' });

    await tabs.getByRole('tab', { name: 'drafts.feature' }).focus();
    await page.keyboard.press('ArrowRight');

    await expect(tabs.getByRole('tab', { name: 'cookies.feature' })).toBeFocused();
    await expect(tabs.getByRole('tab', { name: 'cookies.feature' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('tabpanel', { name: 'cookies.feature' })).toContainText('Scenario: Visitor accepts the cookies');
  });

  test('shows every block in print, scrolled into view or not', async ({ page }) => {
    await open(page, '/', 'light');

    const waiting = page.locator('[data-reveal=""][data-revealed="false"], [data-reveal="children"][data-revealed="false"] > *');

    await expect(waiting.first()).toBeAttached();
    await page.emulateMedia({ media: 'print' });

    expect(await waiting.evaluateAll((blocks) => blocks.filter((block) => getComputedStyle(block).opacity !== '1').length)).toBe(0);
  });

  test('copies the install command', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await open(page, '/', 'light');

    await page.getByRole('button', { name: 'Copy the install command' }).first().click();

    await expect(page.getByRole('status').filter({ hasText: 'Copied' })).toHaveText('Copied the install command to the clipboard.');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(INSTALL_COMMAND);
  });

  for (const colorMode of COLOR_MODES) {
    test(`passes once every block has played its entrance in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      // Scrolls down in steps, so every block crosses the viewport.
      await page.evaluate(async () => {
        for (let top = 0; top < document.body.scrollHeight; top += 300) {
          window.scrollTo(0, top);
          await new Promise((resolve) => {
            setTimeout(resolve, 50);
          });
        }
      });

      await expect(page.locator('[data-revealed="false"]')).toHaveCount(0);

      // An animation that never ends would never settle, so only the ones
      // that end are waited for.
      await page.evaluate(() => Promise.all(document.getAnimations().filter((animation) => animation.effect.getComputedTiming().endTime !== Infinity).map((animation) => animation.finished)));

      expect(await scan(page)).toEqual([]);
    });
  }
});

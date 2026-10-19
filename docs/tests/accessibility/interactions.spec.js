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

  test('moves keyboard focus inside, keeps it there and closes on Escape', async ({ page }) => {
    await open(page, '/', 'light');

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

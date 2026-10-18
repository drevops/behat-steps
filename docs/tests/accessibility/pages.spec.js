import { expect, test } from '@playwright/test';
import { builtPages, COLOR_MODES, open, scan } from './helpers';

for (const colorMode of COLOR_MODES) {
  test.describe(`${colorMode} mode`, () => {
    for (const url of builtPages()) {
      test(url, async ({ page }) => {
        await open(page, url, colorMode);

        expect(await scan(page)).toEqual([]);
      });
    }
  });
}

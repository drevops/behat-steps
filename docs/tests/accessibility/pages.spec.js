import { expect, test } from '@playwright/test';
import { builtPages, COLOR_MODES, open, scan } from './helpers';

// Without motion, the home page shows every block at once and plays no
// entrance, so the scan sees each page whole and settled.
test.use({ reducedMotion: 'reduce' });

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

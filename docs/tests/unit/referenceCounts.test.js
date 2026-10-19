/**
 * @jest-environment node
 */

import fs from 'node:fs';
import path from 'node:path';
import referenceCounts, { countSection, SECTIONS } from '../../src/referenceCounts';

const FIXTURES = path.join(__dirname, 'fixtures');
const CONTENT_DIR = path.join(__dirname, '..', '..', 'content');
const ROOT = path.join(__dirname, '..', '..', '..');

const occurrences = (file, pattern) => (fs.readFileSync(path.join(ROOT, file), 'utf8').match(pattern) || []).length;

describe('countSection', () => {
  test.each([
    { section: 'web-steps', expected: { pages: 2, entries: 3 } },
    { section: 'drupal-steps', expected: { pages: 1, entries: 3 } },
    { section: 'web-helpers', expected: { pages: 1, entries: 1 } },
    { section: 'drupal-helpers', expected: { pages: 1, entries: 2 } },
  ])('counts the pages and entries of $section', ({ section, expected }) => {
    expect(countSection(path.join(FIXTURES, 'content', section))).toEqual(expected);
  });

  test('fails on a section without generated entries', () => {
    const directory = path.join(FIXTURES, 'empty', 'web-steps');

    expect(() => countSection(directory)).toThrow(`${directory} holds no generated steps or helpers. Run "ahoy update-docs" from the repository root.`);
  });

  test('fails on a section that does not exist', () => {
    expect(() => countSection(path.join(FIXTURES, 'missing'))).toThrow(/ENOENT/);
  });
});

describe('referenceCounts', () => {
  const plugin = referenceCounts({}, { contentDir: path.join(FIXTURES, 'content') });

  test('loads the counts of every section', () => {
    expect(plugin.name).toBe('reference-counts');
    expect(plugin.loadContent()).toEqual({
      webSteps: { pages: 2, entries: 3 },
      drupalSteps: { pages: 1, entries: 3 },
      webHelpers: { pages: 1, entries: 1 },
      drupalHelpers: { pages: 1, entries: 2 },
    });
  });

  test('publishes the counts as global data', () => {
    const actions = { setGlobalData: jest.fn() };
    const content = plugin.loadContent();

    plugin.contentLoaded({ content, actions });

    expect(actions.setGlobalData).toHaveBeenCalledWith(content);
  });

  test('watches the pages of every section', () => {
    expect(plugin.getPathsToWatch()).toEqual(Object.values(SECTIONS).map((section) => path.join(FIXTURES, 'content', section, '*.md')));
  });

  // The site pages and the 1-file references come from the same run of
  // docs.php, so their totals agree unless the markup the count relies on
  // changes.
  test('counts every step of STEPS.md and every helper of HELPERS.md', () => {
    const counts = referenceCounts({}, { contentDir: CONTENT_DIR }).loadContent();

    expect(counts.webSteps.entries + counts.drupalSteps.entries).toBe(occurrences('STEPS.md', /^ *<summary><code>@/gm));
    expect(counts.webHelpers.entries + counts.drupalHelpers.entries).toBe(occurrences('HELPERS.md', /^ *<summary><code>/gm));
  });
});

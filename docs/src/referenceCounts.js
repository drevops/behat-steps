import fs from 'node:fs';
import path from 'node:path';

// The sections docs.php writes into the content directory, keyed by the name
// the home page reads their counts by.
export const SECTIONS = {
  webSteps: 'web-steps',
  drupalSteps: 'drupal-steps',
  webHelpers: 'web-helpers',
  drupalHelpers: 'drupal-helpers',
};

// docs.php writes each step and each helper as a '<details>' block that opens
// with its pattern or signature.
const ENTRY = /^ *<summary><code>/gm;

// Counts the pages of a section, 1 per trait or class beside its index, and
// the steps or helpers on them.
export const countSection = (directory) => {
  const pages = fs.readdirSync(directory).filter((file) => file.endsWith('.md') && file !== 'README.md');
  const entries = pages.reduce((total, page) => total + (fs.readFileSync(path.join(directory, page), 'utf8').match(ENTRY) || []).length, 0);

  if (entries === 0) {
    throw new Error(`${directory} holds no generated steps or helpers. Run "ahoy update-docs" from the repository root.`);
  }

  return { pages: pages.length, entries };
};

// Publishes the counts of every section as the plugin's global data.
export default function referenceCounts(context, { contentDir }) {
  return {
    name: 'reference-counts',
    loadContent: () => Object.fromEntries(Object.entries(SECTIONS).map(([key, section]) => [key, countSection(path.join(contentDir, section))])),
    contentLoaded: ({ content, actions }) => actions.setGlobalData(content),
    getPathsToWatch: () => Object.values(SECTIONS).map((section) => path.join(contentDir, section, '*.md')),
  };
}

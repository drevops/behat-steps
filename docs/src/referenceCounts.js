import fs from 'node:fs';
import path from 'node:path';

// The sections docs.php writes into the content directory, keyed by the name
// the home page reads their counts by.
export const SECTIONS = {
  webSteps: 'web-steps',
  drupalSteps: 'drupal-steps',
};

// docs.php writes each step and each helper as a '<details>' block that opens
// with its pattern or signature.
const ENTRY = /^ *<summary><code>/gm;

const countEntries = (file) => (fs.readFileSync(file, 'utf8').match(ENTRY) || []).length;

// Counts the pages of a section, 1 per trait beside its index, and the steps
// on them.
export const countSection = (directory) => {
  const pages = fs.readdirSync(directory).filter((file) => file.endsWith('.md') && file !== 'README.md');
  const entries = pages.reduce((total, page) => total + countEntries(path.join(directory, page)), 0);

  if (entries === 0) {
    throw new Error(`${directory} holds no generated steps. Run "ahoy update-docs" from the repository root.`);
  }

  return { pages: pages.length, entries };
};

// Counts the helpers of HELPERS.md, the only reference that lists them.
export const countHelpers = (file) => {
  const entries = countEntries(file);

  if (entries === 0) {
    throw new Error(`${file} holds no generated helpers. Run "ahoy update-docs" from the repository root.`);
  }

  return { entries };
};

// Publishes the counts of every section and of the helper reference as the
// plugin's global data.
export default function referenceCounts(context, { contentDir, helpersFile }) {
  return {
    name: 'reference-counts',
    loadContent: () => ({
      ...Object.fromEntries(Object.entries(SECTIONS).map(([key, section]) => [key, countSection(path.join(contentDir, section))])),
      helpers: countHelpers(helpersFile),
    }),
    contentLoaded: ({ content, actions }) => actions.setGlobalData(content),
    getPathsToWatch: () => [...Object.values(SECTIONS).map((section) => path.join(contentDir, section, '*.md')), helpersFile],
  };
}

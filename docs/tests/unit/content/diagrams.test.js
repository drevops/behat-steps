import fs from 'node:fs';
import path from 'node:path';

const CONTENT = path.join(__dirname, '..', '..', '..', 'content');

const files = fs.readdirSync(CONTENT, { recursive: true }).map(String);

// Every diagram, as its path relative to the content directory without the
// '.drawio' extension.
const diagrams = files.filter((file) => file.endsWith('.drawio')).map((file) => file.slice(0, -'.drawio'.length));

// Every image a page embeds, as [page, target], with the target resolved
// against the page's directory and its fragment kept.
const images = files.filter((file) => file.endsWith('.md')).flatMap((page) => {
  const markdown = fs.readFileSync(path.join(CONTENT, page), 'utf8');

  return Array.from(markdown.matchAll(/!\[[^\]]*\]\(([^)\s]+)\)/g), (match) => [page, path.join(path.dirname(page), match[1])]);
});

const isExportOf = (target, diagram) => target.startsWith(`${diagram}.svg`) || target.startsWith(`${diagram}.dark.svg`);

const diagramImages = images.filter(([, target]) => diagrams.some((diagram) => isExportOf(target, diagram)));

// The embed of the same diagram's export for the other color mode.
const twinOf = (target) => (target.endsWith('#gh-light-mode-only')
  ? target.replace(/\.svg#gh-light-mode-only$/, '.dark.svg#gh-dark-mode-only')
  : target.replace(/\.dark\.svg#gh-dark-mode-only$/, '.svg#gh-light-mode-only'));

describe('the diagrams', () => {
  test('exist', () => {
    expect(diagrams.length).toBeGreaterThan(0);
  });

  test.each(diagrams)('%s has a light and a dark export', (diagram) => {
    expect(fs.existsSync(path.join(CONTENT, `${diagram}.svg`))).toBe(true);
    expect(fs.existsSync(path.join(CONTENT, `${diagram}.dark.svg`))).toBe(true);
  });

  test.each(diagrams)('%s is embedded on a page', (diagram) => {
    expect(diagramImages.some(([, target]) => isExportOf(target, diagram))).toBe(true);
  });

  test.each(diagramImages)('%s embeds %s for 1 color mode', (page, target) => {
    expect(target).toMatch(/(\.dark\.svg#gh-dark-mode-only|(?<!\.dark)\.svg#gh-light-mode-only)$/);
  });

  test.each(diagramImages)('%s pairs %s with the export for the other color mode', (page, target) => {
    expect(images).toContainEqual([page, twinOf(target)]);
  });
});

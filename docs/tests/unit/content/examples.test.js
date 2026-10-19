import fs from 'node:fs';
import path from 'node:path';
import { isStep } from '../../../src/components/FeatureFile';
import { shippedSteps, turnip } from '../stepPatterns';

const ROOT = path.join(__dirname, '..', '..', '..', '..');

// The pages a newcomer reads first. Their scenarios use only shipped steps,
// Mink's steps below and steps the page itself defines, so they run as written.
const PAGES = ['README.md', 'docs/content/README.md', 'docs/content/quick-start.md', 'docs/content/concepts.md'];

// The steps of Mink's own context the pages use, which STEPS.md doesn't list.
const MINK_STEPS = [/^the response status code should be \d+$/];

// The body of every Gherkin code block in a page.
const gherkinBlocks = (markdown) => Array.from(markdown.matchAll(/^```gherkin\n([\s\S]*?)^```$/gm), (match) => match[1]);

// The pattern of every step a page defines in its PHP code blocks, such as
// "#[Then('the footer should mention :text')]".
const definedSteps = (markdown) => Array.from(markdown.matchAll(/#\[(?:Given|When|Then)\('([^']+)'\)\]/g), (match) => turnip(match[1]));

const steps = PAGES.flatMap((page) => {
  const markdown = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const known = [...shippedSteps(), ...MINK_STEPS, ...definedSteps(markdown)];

  return gherkinBlocks(markdown).flatMap((block) => block.split('\n').filter(isStep).map((line) => [page, line.trim().replace(/^\w+ /, ''), known]));
});

describe('the examples on the newcomer pages', () => {
  test.each(PAGES)('%s holds steps', (page) => {
    expect(steps.some(([name]) => name === page)).toBe(true);
  });

  test.each(steps)('%s uses a defined step: %s', (page, step, known) => {
    expect(known.some((pattern) => pattern.test(step))).toBe(true);
  });
});

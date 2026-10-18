/**
 * @jest-environment node
 */

import path from 'node:path';
import repositoryLinks from '../../../src/remark/repositoryLinks';

const ROOT = path.resolve(__dirname, '..', '..', '..', '..');
const CONTENT = path.join(ROOT, 'docs', 'content');
const PAGE = path.join(CONTENT, 'guides', 'page.md');
const OPTIONS = { repositoryRoot: ROOT, contentDir: CONTENT, repositoryUrl: 'https://github.com/drevops/behat-steps', ref: 'main' };

// Runs the plugin over a tree holding 1 node of the given type.
const rewrite = (url, { type = 'link', options = {}, file = PAGE } = {}) => {
  const node = { type, url, children: [] };
  const tree = { type: 'root', children: [{ type: 'paragraph', children: [node] }] };

  repositoryLinks({ ...OPTIONS, ...options })(tree, { path: file });

  return node.url;
};

describe('repositoryLinks', () => {
  test.each([
    { name: 'a repository file', url: '../../../CONTRIBUTING.md', expected: 'https://github.com/drevops/behat-steps/blob/main/CONTRIBUTING.md' },
    { name: 'a repository file with an anchor', url: '../../../CONTRIBUTING.md#steps-format', expected: 'https://github.com/drevops/behat-steps/blob/main/CONTRIBUTING.md#steps-format' },
    { name: 'a repository directory', url: '../../../tests/behat/features', expected: 'https://github.com/drevops/behat-steps/blob/main/tests/behat/features' },
    { name: 'a file in docs/ outside the content', url: '../../package.json', expected: 'https://github.com/drevops/behat-steps/blob/main/docs/package.json' },
  ])('rewrites a link to $name', ({ url, expected }) => {
    expect(rewrite(url)).toBe(expected);
  });

  test.each([
    { name: 'a page', url: '../steps.md#cookietrait' },
    { name: 'a page in the same folder', url: 'usage.md' },
    { name: 'the content directory', url: '..' },
    { name: 'an anchor on the page', url: '#options' },
    { name: 'a site path', url: '/steps' },
    { name: 'an absolute URL', url: 'https://behat.org' },
    { name: 'an email address', url: 'mailto:alex@drevops.com' },
  ])('leaves a link to $name alone', ({ url }) => {
    expect(rewrite(url)).toBe(url);
  });

  test('rewrites a link definition', () => {
    expect(rewrite('../../../MIGRATION.md', { type: 'definition' })).toBe('https://github.com/drevops/behat-steps/blob/main/MIGRATION.md');
  });

  test('leaves an image alone', () => {
    expect(rewrite('../../../logo.png', { type: 'image' })).toBe('../../../logo.png');
  });

  test('links the ref it is given', () => {
    expect(rewrite('../../../composer.json', { options: { ref: '4.0.0' } })).toBe('https://github.com/drevops/behat-steps/blob/4.0.0/composer.json');
  });

  test('resolves a link from the page holding it', () => {
    expect(rewrite('../../composer.json', { file: path.join(CONTENT, 'README.md') })).toBe('https://github.com/drevops/behat-steps/blob/main/composer.json');
  });

  test.each([
    { name: 'a file that does not exist', url: '../../../missing.md' },
    { name: 'a path outside the repository', url: '../../../../outside.md' },
  ])('fails on a link to $name', ({ url }) => {
    expect(() => rewrite(url)).toThrow(`The link "${url}" in ${PAGE} names no file in the repository.`);
  });
});

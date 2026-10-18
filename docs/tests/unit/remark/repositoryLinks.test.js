/**
 * @jest-environment node
 */

import path from 'node:path';
import repositoryLinks from '../../../src/remark/repositoryLinks';

const ROOT = path.resolve(__dirname, '..', '..', '..', '..');
const CONTENT = path.join(ROOT, 'docs', 'content');
const PAGE = path.join(CONTENT, 'guides', 'page.md');
const OPTIONS = { repositoryRoot: ROOT, contentDir: CONTENT, repositoryUrl: 'https://github.com/drevops/behat-steps', ref: 'main' };

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
    { name: 'a repository file with a query', url: '../../../README.md?plain=1', expected: 'https://github.com/drevops/behat-steps/blob/main/README.md?plain=1' },
    { name: 'a repository file with a query and an anchor', url: '../../../README.md?plain=1#installation', expected: 'https://github.com/drevops/behat-steps/blob/main/README.md?plain=1#installation' },
    { name: 'a percent-encoded path', url: '../../../CONTRIBUTING%2Emd', expected: 'https://github.com/drevops/behat-steps/blob/main/CONTRIBUTING.md' },
    { name: 'an anchor holding a line separator', url: '../../../CONTRIBUTING.md#a\u2028b', expected: 'https://github.com/drevops/behat-steps/blob/main/CONTRIBUTING.md#a\u2028b' },
    { name: 'a repository directory', url: '../../../tests/behat/features', expected: 'https://github.com/drevops/behat-steps/tree/main/tests/behat/features' },
    { name: 'a file in docs/ outside the content', url: '../../package.json', expected: 'https://github.com/drevops/behat-steps/blob/main/docs/package.json' },
  ])('rewrites a link to $name', ({ url, expected }) => {
    expect(rewrite(url)).toBe(expected);
  });

  test.each([
    { name: 'a page', url: '../migrating.md#migrate-by-hand' },
    { name: 'a page in the same folder', url: 'usage.md' },
    { name: 'the content directory', url: '..' },
  ])('leaves a link to $name alone', ({ url }) => {
    expect(rewrite(url)).toBe(url);
  });

  // The page sits outside this content directory, so a URL that reaches path
  // resolution fails the test.
  test.each([
    { name: 'an anchor on the page', url: '#options' },
    { name: 'a site path', url: '/web-steps' },
    { name: 'a protocol-relative URL', url: '//example.com/page' },
    { name: 'an absolute URL', url: 'https://behat.org' },
    { name: 'an email address', url: 'mailto:alex@drevops.com' },
  ])('leaves a link to $name alone without resolving it', ({ url }) => {
    expect(rewrite(url, { options: { contentDir: path.join(ROOT, 'docs', 'static') } })).toBe(url);
  });

  test('treats a directory that only shares the content prefix as outside it', () => {
    expect(rewrite('../migrating.md', { options: { contentDir: path.join(ROOT, 'docs', 'cont') } })).toBe('https://github.com/drevops/behat-steps/blob/main/docs/content/migrating.md');
  });

  test('rewrites a link definition', () => {
    expect(rewrite('../../../MIGRATION.md', { type: 'definition' })).toBe('https://github.com/drevops/behat-steps/blob/main/MIGRATION.md');
  });

  test('rewrites a link that follows a text node', () => {
    const node = { type: 'link', url: '../../../CONTRIBUTING.md', children: [] };
    const tree = { type: 'root', children: [{ type: 'paragraph', children: [{ type: 'text', value: 'See ' }, node] }] };

    repositoryLinks(OPTIONS)(tree, { path: PAGE });

    expect(node.url).toBe('https://github.com/drevops/behat-steps/blob/main/CONTRIBUTING.md');
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
    { name: 'a path outside the repository', url: '../../../..' },
  ])('fails on a link to $name', ({ url }) => {
    expect(() => rewrite(url)).toThrow(`The link "${url}" in ${PAGE} names no file in the repository.`);
  });
});

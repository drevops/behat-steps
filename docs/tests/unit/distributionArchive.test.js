/**
 * @jest-environment node
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '..', '..', '..');

// A consuming project's AI agent reads both from vendor/.
const SHIPPED = ['docs/content/helpers.md', 'docs/content/steps.md'];

const git = (args, input) => execFileSync('git', args, { cwd: ROOT, input, encoding: 'utf8' });

// 'git archive' skips everything below an ignored directory, so a path ships
// only when neither it nor any directory above it is ignored.
const withAncestors = (files) => Array.from(new Set(files.flatMap((file) => file.split('/').map((part, index, parts) => parts.slice(0, index + 1).join('/')))));

const ignoredPaths = (paths) => {
  const fields = git(['check-attr', '-z', '--stdin', 'export-ignore'], paths.join('\0')).split('\0');
  const ignored = new Set();

  for (let index = 0; index + 2 < fields.length; index += 3) {
    if (fields[index + 2] === 'set') {
      ignored.add(fields[index]);
    }
  }

  return ignored;
};

const archived = (files) => {
  const ignored = ignoredPaths(withAncestors(files));

  return files.filter((file) => withAncestors([file]).every((part) => !ignored.has(part)));
};

describe('distribution archive', () => {
  test('ships the step and helper references', () => {
    expect(archived(SHIPPED)).toEqual(SHIPPED);
  });

  test('ships nothing else from docs/', () => {
    const tracked = git(['ls-files', '-z', '--', 'docs']).split('\0').filter(Boolean);

    expect(archived(tracked).sort()).toEqual(SHIPPED);
  });
});

/**
 * @jest-environment node
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '..', '..', '..');

// A consuming project's AI agent reads these from vendor/.
const SHIPPED = ['HELPERS.md', 'STEPS.md', 'skills/create-behat-step/SKILL.md'];

// A shipped skill names the package files it reads by their path in a
// consuming project.
const PACKAGE_PATH = /vendor\/drevops\/behat-steps\/([^\s`"')]*)/g;

const git = (args, input) => execFileSync('git', args, { cwd: ROOT, input, encoding: 'utf8' });

// 'git archive' checks each directory with a trailing slash, so a pattern
// ending in '/' matches it.
const ancestors = (file) => file.split('/').map((_, index, parts) => parts.slice(0, index + 1).join('/') + (index < parts.length - 1 ? '/' : ''));

// 'git archive' skips everything below an ignored directory, so a path ships
// only when neither it nor any directory above it is ignored.
const withAncestors = (files) => Array.from(new Set(files.flatMap(ancestors)));

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

  return files.filter((file) => ancestors(file).every((ancestor) => !ignored.has(ancestor)));
};

describe('distribution archive', () => {
  test('ships the step and helper references', () => {
    expect(archived(SHIPPED)).toEqual(SHIPPED);
  });

  test('ships nothing from docs/', () => {
    const tracked = git(['ls-files', '-z', '--', 'docs']).split('\0').filter(Boolean);

    expect(archived(tracked)).toEqual([]);
  });

  test('ships every skill under skills/', () => {
    const skills = git(['ls-files', '-z', '--', 'skills']).split('\0').filter(Boolean);

    expect(skills.length).toBeGreaterThan(0);
    expect(archived(skills)).toEqual(skills);
  });

  test('ships every package file a shipped skill reads', () => {
    const skills = git(['ls-files', '-z', '--', 'skills/*/SKILL.md']).split('\0').filter(Boolean);
    const paths = new Set(skills.flatMap((skill) => Array.from(fs.readFileSync(path.join(ROOT, skill), 'utf8').matchAll(PACKAGE_PATH), (match) => match[1].replace(/[.,:;]+$/, '')).filter(Boolean)));

    expect(paths.size).toBeGreaterThan(0);

    paths.forEach((packagePath) => {
      const tracked = git(['ls-files', '-z', '--', packagePath]).split('\0').filter(Boolean);

      expect({ packagePath, tracked: tracked.length > 0 }).toEqual({ packagePath, tracked: true });
      expect(archived(tracked)).toEqual(tracked);
    });
  });
});

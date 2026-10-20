import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.join(__dirname, '..', '..', '..');

// A Behat turnip pattern as a regular expression: a ':name' placeholder takes
// a quoted string or a single word, '(s)' is optional and 'a/b' is either.
export const turnip = (pattern) => {
  const escape = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const source = pattern.split(/(:\w+|\([^)]+\)|\w+\/\w+)/).map((part) => {
    if (/^:\w+$/.test(part)) {
      return '(?:"[^"]*"|\'[^\']*\'|-?[\\w.,]+)';
    }

    if (/^\([^)]+\)$/.test(part)) {
      return `(?:${escape(part.slice(1, -1))})?`;
    }

    if (/^\w+\/\w+$/.test(part)) {
      return `(?:${part.split('/').map(escape).join('|')})`;
    }

    return escape(part);
  }).join('');

  return new RegExp(`^${source}$`);
};

// Every step pattern the library ships, without its keyword.
export const shippedSteps = () => Array.from(fs.readFileSync(path.join(ROOT, 'STEPS.md'), 'utf8').matchAll(/<summary><code>@(?:Given|When|Then) ([^<]+)<\/code>/g), (match) => turnip(match[1].replaceAll('&quot;', '"')));

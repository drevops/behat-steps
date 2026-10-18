import fs from 'node:fs';
import path from 'node:path';

// A URL with a scheme, a path from the site root, a protocol-relative URL or
// an anchor on the page.
const NOT_RELATIVE = /^([a-z][a-z0-9+.-]*:|\/|#)/i;

const isInside = (directory, target) => target === directory || target.startsWith(directory + path.sep);

const visit = (node, callback) => {
  callback(node);
  (node.children || []).forEach((child) => visit(child, callback));
};

// The site serves only the pages, so a link that leaves the content directory
// points at the file on GitHub instead.
export default function repositoryLinks({ repositoryRoot, contentDir, repositoryUrl, ref }) {
  return (tree, file) => {
    visit(tree, (node) => {
      if ((node.type !== 'link' && node.type !== 'definition') || NOT_RELATIVE.test(node.url)) {
        return;
      }

      const [, target, suffix] = node.url.match(/^([^?#]*)(.*)$/);
      const absolute = path.resolve(path.dirname(file.path), decodeURIComponent(target));

      if (isInside(contentDir, absolute)) {
        return;
      }

      if (!isInside(repositoryRoot, absolute) || !fs.existsSync(absolute)) {
        throw new Error(`The link "${node.url}" in ${file.path} names no file in the repository.`);
      }

      const relative = path.relative(repositoryRoot, absolute).split(path.sep).join('/');
      const kind = fs.statSync(absolute).isDirectory() ? 'tree' : 'blob';

      node.url = `${repositoryUrl}/${kind}/${ref}/${relative}${suffix}`;
    });
  };
}

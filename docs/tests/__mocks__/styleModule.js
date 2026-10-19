// Stands in for a CSS module, returning each class name as written. It isn't
// an ES module, so a default import gets the whole object.
module.exports = new Proxy({}, { get: (target, name) => (name === '__esModule' ? false : name) });

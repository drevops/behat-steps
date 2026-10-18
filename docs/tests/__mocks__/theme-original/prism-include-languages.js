// Stands in for the theme's language loader with the 1 language the tests
// tokenize, loaded onto the instance the way the loader loads each language.
const prismIncludeLanguages = (Prism) => {
  globalThis.Prism = Prism;
  require('prismjs/components/prism-gherkin');
  delete globalThis.Prism;
};

export default prismIncludeLanguages;

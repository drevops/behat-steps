import prismIncludeLanguages from '@theme-original/prism-include-languages';

// Gherkin allows a step keyword at the start of a line, but Prism's grammar
// matches 1 only after leading whitespace.
const includeLanguages = (Prism) => {
  prismIncludeLanguages(Prism);

  const { atrule } = Prism.languages.gherkin;

  atrule.pattern = new RegExp(atrule.pattern.source.replace('(^[ \\t]+)', '(^[ \\t]*)'), atrule.pattern.flags);
};

export default includeLanguages;

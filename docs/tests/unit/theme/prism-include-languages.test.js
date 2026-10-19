import { Prism } from 'prism-react-renderer';
import includeLanguages from '../../../src/theme/prism-include-languages';

// The type of each token Prism finds in a line of Gherkin.
const tokenTypes = (line) => Prism.tokenize(line, Prism.languages.gherkin).filter((token) => typeof token !== 'string').map((token) => token.type);

describe('prism-include-languages', () => {
  beforeAll(() => includeLanguages(Prism));

  test.each([
    'Then the page should pass accessibility checks',
    '  When I visit the homepage',
    '    And I should see the text "Welcome"',
  ])('highlights the step keyword in "%s"', (line) => {
    expect(tokenTypes(line)[0]).toBe('atrule');
  });

  test('leaves a word that only starts with a keyword unhighlighted', () => {
    expect(tokenTypes('Thenceforth the page loads')).not.toContain('atrule');
  });
});

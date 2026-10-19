import MDXComponents from '../../../src/theme/MDXComponents';
import ScrollableTable from '../../../src/components/ScrollableTable';

describe('MDXComponents', () => {
  test.each([
    { element: 'a', expected: 'a' },
    { element: 'img', expected: 'img' },
    { element: 'table', expected: ScrollableTable },
  ])('renders Markdown $element elements', ({ element, expected }) => {
    expect(MDXComponents[element]).toBe(expected);
  });
});

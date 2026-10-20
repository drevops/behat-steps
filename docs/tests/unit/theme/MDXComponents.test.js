import MDXComponents from '../../../src/theme/MDXComponents';
import { LightboxImage, LightboxLink } from '../../../src/components/Lightbox';
import ScrollableTable from '../../../src/components/ScrollableTable';

describe('MDXComponents', () => {
  test.each([
    { element: 'a', expected: LightboxLink },
    { element: 'img', expected: LightboxImage },
    { element: 'table', expected: ScrollableTable },
  ])('renders Markdown $element elements', ({ element, expected }) => {
    expect(MDXComponents[element]).toBe(expected);
  });
});

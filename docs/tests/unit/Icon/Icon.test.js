import { render } from '@testing-library/react';
import Icon, { ICON_NAMES } from '../../../src/components/Icon';

describe('Icon', () => {
  afterEach(() => {
    jest.restoreAllMocks();
  });

  test.each(ICON_NAMES)('draws the %s icon as decoration', (name) => {
    const { container } = render(<Icon name={name} className="icon" />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveAttribute('aria-hidden', 'true');
    expect(svg).toHaveAttribute('focusable', 'false');
    expect(svg).toHaveAttribute('viewBox', '0 0 24 24');
    expect(svg).toHaveClass('icon');
    expect(svg.children.length).toBeGreaterThan(0);
  });

  test.each(['unknown', 'toString', undefined])('throws for the name %p', (name) => {
    jest.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => render(<Icon name={name} />)).toThrow(`Icon "${name}" doesn't exist.`);
  });
});

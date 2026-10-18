import { act, render, screen } from '@testing-library/react';
import ScrollableTable from '../../../src/components/ScrollableTable';

describe('ScrollableTable', () => {
  let widths;
  let resize;
  let disconnect;

  beforeEach(() => {
    widths = { scrollWidth: 600, clientWidth: 600 };
    disconnect = jest.fn();

    // jsdom has no layout, so the widths and the resize events are stubbed.
    jest.spyOn(Element.prototype, 'scrollWidth', 'get').mockImplementation(() => widths.scrollWidth);
    jest.spyOn(Element.prototype, 'clientWidth', 'get').mockImplementation(() => widths.clientWidth);

    global.ResizeObserver = jest.fn((callback) => {
      resize = callback;

      return { observe: jest.fn(), disconnect };
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete global.ResizeObserver;
  });

  const renderTable = () => render(
    <ScrollableTable className="custom">
      <tbody>
        <tr>
          <td>cell</td>
        </tr>
      </tbody>
    </ScrollableTable>,
  );

  test.each([
    { name: 'a table that fits', scrollWidth: 600, clientWidth: 600, expected: null },
    { name: 'a table wider than the page', scrollWidth: 900, clientWidth: 600, expected: '0' },
  ])('makes $name focusable only when it scrolls', ({ scrollWidth, clientWidth, expected }) => {
    widths = { scrollWidth, clientWidth };

    renderTable();

    expect(screen.getByRole('table').getAttribute('tabindex')).toBe(expected);
  });

  test('follows the table as its width changes', () => {
    renderTable();

    const table = screen.getByRole('table');

    expect(table).not.toHaveAttribute('tabindex');

    widths = { scrollWidth: 900, clientWidth: 600 };
    act(() => resize());

    expect(table).toHaveAttribute('tabindex', '0');

    widths = { scrollWidth: 600, clientWidth: 600 };
    act(() => resize());

    expect(table).not.toHaveAttribute('tabindex');
  });

  test('keeps a focused table focusable until it loses focus', () => {
    widths = { scrollWidth: 900, clientWidth: 600 };

    renderTable();

    const table = screen.getByRole('table');

    act(() => table.focus());

    widths = { scrollWidth: 600, clientWidth: 600 };
    act(() => resize());

    expect(table).toHaveAttribute('tabindex', '0');
    expect(table).toHaveFocus();

    act(() => table.blur());

    expect(table).not.toHaveAttribute('tabindex');
  });

  test('keeps the content and attributes of the table', () => {
    renderTable();

    const table = screen.getByRole('table');

    expect(table).toHaveClass('custom');
    expect(table).toHaveTextContent('cell');
  });

  test('stops watching the table once it is removed', () => {
    const { unmount } = renderTable();

    unmount();

    expect(disconnect).toHaveBeenCalledTimes(1);
  });
});

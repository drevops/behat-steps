import { fireEvent, render, screen } from '@testing-library/react';
import FeatureFile, { isStep, summarize } from '../../../src/components/FeatureFile';

const FILES = [
  {
    name: 'login.feature',
    code: ['Feature: Login', '', '  Scenario: User logs in', '    Given I am logged in as the user "admin"', '    | name |', '    Then I should see the heading "Welcome"'].join('\n'),
    caption: 'Logs a user in.',
  },
  {
    name: 'search.feature',
    code: ['Feature: Search', '  Scenario: Visitor searches', '    When I visit "/search"'].join('\n'),
    caption: 'Searches the site.',
  },
  {
    name: 'api.feature',
    code: ['Feature: API', '  Scenario: Client reads', '    When I send a REST "GET" request to "/api"', '    Then the REST response status code should be 200', '    And the REST response should contain "ok"'].join('\n'),
    caption: 'Reads the API.',
  },
];

const tab = (name) => screen.getByRole('tab', { name });

// The rendered lines of the file on show, each with its status mark and text.
const lines = () => Array.from(screen.getByRole('tabpanel').querySelectorAll('code > span'));

describe('isStep', () => {
  test.each([
    { line: 'Given I visit "/"', expected: true },
    { line: '    When I press the key "Enter"', expected: true },
    { line: '\tThen the path should be "/"', expected: true },
    { line: '  And the cookie "a" should exist', expected: true },
    { line: '  But I should not see the modal', expected: true },
    { line: '  Scenario: Given a title', expected: false },
    { line: '  Givenness is no keyword', expected: false },
    { line: '  | Given | When |', expected: false },
    { line: '  @javascript', expected: false },
    { line: '', expected: false },
  ])('returns $expected for "$line"', ({ line, expected }) => {
    expect(isStep(line)).toBe(expected);
  });
});

describe('summarize', () => {
  test.each([
    { name: '1 scenario and 1 step', code: 'Scenario: A\n  Given I visit "/"', expected: ['1 scenario (1 passed)', '1 step (1 passed)'] },
    { name: 'several scenarios and steps', code: 'Scenario: A\n  Given I visit "/"\nScenario Outline: B\n  When I visit "/"\n  Then the path should be "/"', expected: ['2 scenarios (2 passed)', '3 steps (3 passed)'] },
    { name: 'a file without scenarios', code: 'Feature: Empty', expected: ['0 scenarios (0 passed)', '0 steps (0 passed)'] },
  ])('prints the Behat summary of $name', ({ code, expected }) => {
    expect(summarize(code)).toEqual(expected);
  });
});

describe('FeatureFile', () => {
  test('lists the files as tabs and shows the first one', () => {
    const { container } = render(<FeatureFile files={FILES} label="Examples" />);

    expect(screen.getByRole('tablist', { name: 'Examples' })).toBeInTheDocument();
    expect(screen.getAllByRole('tab').map((item) => item.textContent)).toEqual(['login.feature', 'search.feature', 'api.feature']);
    expect(tab('login.feature')).toHaveAttribute('aria-selected', 'true');
    expect(tab('login.feature')).toHaveAttribute('tabindex', '0');
    expect(tab('search.feature')).toHaveAttribute('tabindex', '-1');
    expect(screen.getByRole('tabpanel', { name: 'login.feature' })).toHaveAttribute('tabindex', '0');
    expect(screen.getByRole('tabpanel').textContent).toContain('Feature: Login');
    expect(container.querySelector('figcaption')).toHaveTextContent('Logs a user in.');
  });

  test('draws every file in the same place and shows only the chosen one', () => {
    const { container } = render(<FeatureFile files={FILES} label="Examples" />);
    const panels = Array.from(container.querySelectorAll('[role="tabpanel"]'));

    expect(panels.map((panel) => panel.querySelector('code > span').textContent)).toEqual(['Feature: Login', 'Feature: Search', 'Feature: API']);
    expect(panels.map((panel) => panel.classList.contains('active'))).toEqual([true, false, false]);
    expect(panels.map((panel) => panel.getAttribute('aria-hidden'))).toEqual([null, 'true', 'true']);
    expect(panels.map((panel) => panel.getAttribute('tabindex'))).toEqual(['0', null, null]);
    expect(screen.getAllByRole('tab').map((item) => item.getAttribute('aria-controls'))).toEqual(panels.map((panel) => panel.id));
  });

  test('marks each step, in order, and only the steps', () => {
    render(<FeatureFile files={FILES} label="Examples" />);
    const rendered = lines();

    expect(rendered.map((line) => line.textContent)).toEqual(FILES[0].code.split('\n'));
    expect(rendered.map((line) => line.style.getPropertyValue('--step'))).toEqual(['', '', '', '0', '', '1']);
    expect(rendered.map((line) => line.querySelectorAll('svg').length)).toEqual([0, 0, 0, 1, 0, 1]);
    rendered.forEach((line) => {
      expect(line.firstChild).toHaveAttribute('aria-hidden', 'true');
    });
  });

  test('indents the wrapped part of each line past its text', () => {
    render(<FeatureFile files={FILES} label="Examples" />);

    expect(lines().map((line) => line.style.getPropertyValue('--indent'))).toEqual(['0', '0', '2', '4', '4', '4']);
  });

  test('prints the summary after the steps', () => {
    render(<FeatureFile files={FILES} label="Examples" />);

    const panel = screen.getByRole('tabpanel');

    expect(panel.style.getPropertyValue('--steps')).toBe('2');
    expect(panel.querySelector('p').textContent).toBe('1 scenario (1 passed)2 steps (2 passed)');
  });

  test('shows the file of a clicked tab', () => {
    const { container } = render(<FeatureFile files={FILES} label="Examples" />);

    fireEvent.click(tab('api.feature'));

    expect(tab('api.feature')).toHaveAttribute('aria-selected', 'true');
    expect(tab('login.feature')).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByRole('tabpanel', { name: 'api.feature' }).textContent).toContain('Feature: API');
    expect(screen.getByRole('tabpanel')).toHaveClass('active');
    expect(screen.getByRole('tabpanel').style.getPropertyValue('--steps')).toBe('3');
    expect(container.querySelector('figcaption')).toHaveTextContent('Reads the API.');
  });

  test.each([
    { key: 'ArrowRight', from: 0, to: 1 },
    { key: 'ArrowRight', from: 2, to: 0 },
    { key: 'ArrowLeft', from: 1, to: 0 },
    { key: 'ArrowLeft', from: 0, to: 2 },
    { key: 'Home', from: 2, to: 0 },
    { key: 'End', from: 0, to: 2 },
  ])('moves from tab $from to tab $to on $key', ({ key, from, to }) => {
    render(<FeatureFile files={FILES} label="Examples" />);

    fireEvent.click(tab(FILES[from].name));
    fireEvent.keyDown(tab(FILES[from].name), { key });

    expect(tab(FILES[to].name)).toHaveAttribute('aria-selected', 'true');
    expect(tab(FILES[to].name)).toHaveFocus();
  });

  test('leaves other keys to the browser', () => {
    render(<FeatureFile files={FILES} label="Examples" />);

    const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });

    tab('login.feature').dispatchEvent(event);

    expect(event.defaultPrevented).toBe(false);
    expect(tab('login.feature')).toHaveAttribute('aria-selected', 'true');
  });
});

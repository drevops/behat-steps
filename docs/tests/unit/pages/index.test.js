import fs from 'node:fs';
import path from 'node:path';
import { useRef } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import { usePluginData } from '@docusaurus/useGlobalData';
import { isStep } from '../../../src/components/FeatureFile';
import Home, { BEHAT_VERSIONS, DRUPAL_VERSIONS, FEATURE_FILES, INSTALL_COMMAND, PHP_VERSIONS, RUN_COMMAND, WORKS_WITH, listOf, useReveal } from '../../../src/pages';
import { shippedSteps } from '../stepPatterns';

const ROOT = path.join(__dirname, '..', '..', '..', '..');

const COUNTS = {
  webSteps: { pages: 28, entries: 220 },
  drupalSteps: { pages: 29, entries: 229 },
  helpers: { entries: 323 },
};

// Records what the hook observes, and plays the entries a test passes it.
class FakeObserver {
  static instances = [];

  constructor(callback, options) {
    this.callback = callback;
    this.options = options;
    this.observed = new Set();
    this.disconnected = false;
    FakeObserver.instances.push(this);
  }

  observe(element) {
    this.observed.add(element);
  }

  unobserve(element) {
    this.observed.delete(element);
  }

  disconnect() {
    this.disconnected = true;
    this.observed.clear();
  }

  trigger(entries) {
    act(() => this.callback(entries));
  }
}

let reducedMotion;

beforeEach(() => {
  reducedMotion = false;
  window.matchMedia = jest.fn((query) => ({ matches: query === '(prefers-reduced-motion: reduce)' && reducedMotion }));
  usePluginData.mockReturnValue(COUNTS);
});

afterEach(() => {
  cleanup();
  delete window.matchMedia;
});

describe('Home', () => {
  test('reads the counts the reference counts plugin publishes', () => {
    render(<Home />);

    expect(usePluginData).toHaveBeenCalledWith('reference-counts');
  });

  test('describes the page to search engines and the browser', () => {
    render(<Home />);

    const layout = screen.getByTestId('layout');

    expect(layout).toHaveAttribute('data-title', 'Reusable Behat steps for PHP and Drupal projects');
    expect(layout.getAttribute('data-description')).toMatch(/^Behat Steps is a collection of reusable Behat steps/);
  });

  test('opens with the promise, the step count and the 2 ways in', () => {
    render(<Home />);

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('The Behat steps you need, already written.');
    expect(screen.getByText(/^Behat Steps is a library of 449 tested steps/)).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: 'Get started' }).map((link) => link.getAttribute('href'))).toEqual(['/installation', '/installation']);
    expect(screen.getByRole('link', { name: 'Read the docs' })).toHaveAttribute('href', '/introduction');
  });

  test('offers the install command in the hero, the first step and the closing call', () => {
    const { container } = render(<Home />);

    expect(screen.getAllByRole('button', { name: 'Copy the install command' })).toHaveLength(3);
    expect(Array.from(container.querySelectorAll('code')).filter((code) => code.textContent === `$ ${INSTALL_COMMAND}`)).toHaveLength(3);
    expect(screen.getByRole('button', { name: 'Copy the Behat command' })).toBeInTheDocument();
    expect(Array.from(container.querySelectorAll('code')).filter((code) => code.textContent === `$ ${RUN_COMMAND}`)).toHaveLength(1);
  });

  test('shows the example feature files', () => {
    render(<Home />);

    expect(within(screen.getByRole('tablist', { name: 'Example feature files' })).getAllByRole('tab').map((item) => item.textContent)).toEqual(FEATURE_FILES.map(({ name }) => name));
  });

  test('counts the steps, traits and helpers of the reference', () => {
    const { container } = render(<Home />);
    const stats = Array.from(container.querySelectorAll('dl > div')).map((stat) => [stat.querySelector('dd').textContent, stat.querySelector('dt').textContent]);

    expect(stats).toEqual([
      ['449', 'Steps, each with an example'],
      ['57', 'Traits for web and Drupal'],
      ['323', 'Helpers for steps of your own'],
      ['2018', 'Released since'],
    ]);
    expect(screen.getByRole('link', { name: 'Web steps' }).closest('article')).toHaveTextContent('28 traits for pages');
    expect(screen.getByRole('link', { name: 'Drupal steps' }).closest('article')).toHaveTextContent('29 traits for content');
  });

  test('lists what the steps work with', () => {
    render(<Home />);

    expect(within(screen.getByRole('list', { name: 'Works with' })).getAllByRole('listitem').map((item) => item.textContent)).toEqual(WORKS_WITH);
    expect(WORKS_WITH).toEqual(['Behat 3.33+', 'Behat 4', 'Mink', 'BrowserKit', 'Selenium', 'Chrome DevTools', 'Drupal 11', 'Drupal 12', 'Drush', 'PHP 8.3', 'PHP 8.4', 'PHP 8.5']);
  });

  test.each([
    ['Web steps', '/web-steps'],
    ['Drupal steps', '/drupal-steps'],
    ['Helpers behind every step', '/concepts#helpers-behind-every-step'],
    ['Backends by capability', '/guides/configuration#backend-resolution'],
    ['Browser drivers', '/installation#javascript-browser-drivers'],
    ['Cleanup after each scenario', '/guides/usage#entities-a-scenario-creates'],
    ['References for AI agents', '/guides/ai-assistants'],
    ['Guided upgrade from 3.x', '/migrating'],
  ])('links the %s feature to %s', (name, href) => {
    render(<Home />);

    expect(screen.getByRole('link', { name })).toHaveAttribute('href', href);
  });

  test.each([
    ['Introduction', '/introduction'],
    ['Installation', '/installation'],
    ['Quick start', '/quick-start'],
    ['Usage', '/guides/usage'],
    ['Configuration', '/guides/configuration'],
    ['Extending', '/extending'],
    ['Migrating from 3.x', '/migrating'],
    ['Architecture', '/architecture'],
  ])('links the docs card %s to %s', (name, href) => {
    render(<Home />);

    expect(screen.getByRole('link', { name: new RegExp(`^${name.replace('.', '\\.')} `) })).toHaveAttribute('href', href);
  });

  test.each([
    ['Install the package', 'Installation', '/installation#optional-dependencies'],
    ['Create your context', 'See both files', '/quick-start#3-create-your-context-and-configuration'],
  ])('links the docs from the step "%s"', (step, name, href) => {
    render(<Home />);

    const item = screen.getByRole('heading', { name: step }).closest('li');

    expect(within(item).getByRole('link', { name })).toHaveAttribute('href', href);
  });

  test('says that every external link opens a new tab', () => {
    const { container } = render(<Home />);
    const external = Array.from(container.querySelectorAll('a[href^="https://"]'));

    expect(external.map((link) => link.getAttribute('href'))).toEqual(['https://github.com/drevops/behat-steps']);
    external.forEach((link) => {
      expect(link).toHaveAccessibleName(/ \(opens in new tab\)$/);
    });
  });

  test('draws the bars of the logo as decoration', () => {
    const { container } = render(<Home />);
    const bars = container.querySelectorAll('svg[viewBox="0 0 240 200"]');

    expect(bars).toHaveLength(2);
    bars.forEach((svg) => {
      expect(svg).toHaveAttribute('aria-hidden', 'true');
      expect(svg.querySelectorAll('rect')).toHaveLength(4);
    });
  });
});

describe('FEATURE_FILES', () => {
  const steps = FEATURE_FILES.flatMap(({ name, code }) => code.split('\n').filter(isStep).map((line) => [name, line.trim().replace(/^\w+ /, '')]));

  test('holds steps in every file', () => {
    expect(new Set(steps.map(([name]) => name)).size).toBe(FEATURE_FILES.length);
  });

  test.each(steps)('ships the step of %s: %s', (name, step) => {
    expect(shippedSteps().some((pattern) => pattern.test(step))).toBe(true);
  });
});

describe('the supported versions', () => {
  // The first row of the table in the introduction is the current major.
  test('match the introduction', () => {
    const introduction = fs.readFileSync(path.join(ROOT, 'docs', 'content', 'README.md'), 'utf8');
    const [behat, drupal, php] = introduction.match(/^\| 4\.x \| ([^|]+) \| ([^|]+) \| ([^|]+) \|/m).slice(1).map((cell) => cell.trim());

    expect(BEHAT_VERSIONS.join(', ')).toBe(behat);
    expect(DRUPAL_VERSIONS.join(', ')).toBe(drupal);
    expect(PHP_VERSIONS.join(', ')).toBe(php);
  });
});

describe('listOf', () => {
  test.each([
    { items: [], expected: '' },
    { items: ['11'], expected: '11' },
    { items: ['11', '12'], expected: '11 and 12' },
    { items: ['8.3', '8.4', '8.5'], expected: '8.3, 8.4 and 8.5' },
  ])('joins $items as "$expected"', ({ items, expected }) => {
    expect(listOf(items)).toBe(expected);
  });
});

describe('useReveal', () => {
  const Blocks = () => {
    const root = useRef(null);

    useReveal(root);

    return (
      <div ref={root}>
        <p data-reveal="" data-testid="above">
          Above
        </p>
        <p data-reveal="" data-testid="below">
          Below
        </p>
        <div data-reveal="children" data-testid="grid">
          <span>Card</span>
        </div>
        <p data-testid="plain">Plain</p>
      </div>
    );
  };

  beforeEach(() => {
    FakeObserver.instances = [];
    global.IntersectionObserver = FakeObserver;

    // The block marked 'above' starts in view, and the others below the fold.
    jest.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function boundingRect() {
      return { top: this.dataset.testid === 'above' ? 100 : window.innerHeight + 100 };
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete global.IntersectionObserver;
  });

  const revealed = (id) => screen.getByTestId(id).dataset.revealed;

  test('hides the blocks below the fold and leaves the rest alone', () => {
    render(<Blocks />);

    const [observer] = FakeObserver.instances;

    expect(revealed('above')).toBeUndefined();
    expect(revealed('below')).toBe('false');
    expect(revealed('grid')).toBe('false');
    expect(revealed('plain')).toBeUndefined();
    expect(Array.from(observer.observed)).toEqual([screen.getByTestId('below'), screen.getByTestId('grid')]);
    expect(observer.options).toEqual({ rootMargin: '0px 0px -10% 0px' });
  });

  test('shows a block once it scrolls into view and stops watching it', () => {
    render(<Blocks />);

    const [observer] = FakeObserver.instances;

    observer.trigger([
      { target: screen.getByTestId('below'), isIntersecting: true },
      { target: screen.getByTestId('grid'), isIntersecting: false },
    ]);

    expect(revealed('below')).toBe('true');
    expect(revealed('grid')).toBe('false');
    expect(Array.from(observer.observed)).toEqual([screen.getByTestId('grid')]);
  });

  test('stops watching once unmounted', () => {
    const { unmount } = render(<Blocks />);

    unmount();

    expect(FakeObserver.instances[0].disconnected).toBe(true);
  });

  test.each([
    {
      name: 'for a reader who prefers reduced motion',
      setUp: () => {
        reducedMotion = true;
      },
    },
    {
      name: 'without IntersectionObserver',
      setUp: () => {
        delete global.IntersectionObserver;
      },
    },
  ])('leaves every block visible $name', ({ setUp }) => {
    setUp();

    render(<Blocks />);

    expect(['above', 'below', 'grid', 'plain'].map(revealed)).toEqual([undefined, undefined, undefined, undefined]);
    expect(FakeObserver.instances).toHaveLength(0);
  });
});

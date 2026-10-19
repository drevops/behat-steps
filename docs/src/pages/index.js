import { useEffect, useRef } from 'react';
import clsx from 'clsx';
import Layout from '@theme/Layout';
import Link from '@docusaurus/Link';
import { usePluginData } from '@docusaurus/useGlobalData';
import CopyCommand from '@site/src/components/CopyCommand';
import FeatureFile from '@site/src/components/FeatureFile';
import Icon from '@site/src/components/Icon';
import styles from './index.module.css';

export const INSTALL_COMMAND = 'composer require --dev drevops/behat-steps:^4';

export const RUN_COMMAND = 'vendor/bin/behat';

// The versions of the current major, as the introduction's table of
// supported versions lists them.
export const BEHAT_VERSIONS = ['3.33+', '4'];
export const DRUPAL_VERSIONS = ['11', '12'];
export const PHP_VERSIONS = ['8.3', '8.4', '8.5'];

const GITHUB_URL = 'https://github.com/drevops/behat-steps';

const NEW_TAB = <span className="visually-hidden"> (opens in new tab)</span>;

// The logo's bars in order, which the cards of a section take in turn.
const ACCENTS = ['blue', 'green', 'orange', 'yellow'];

export const listOf = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}` : items.join(''));

const accent = (index) => styles[ACCENTS[index % ACCENTS.length]];

export const FEATURE_FILES = [
  {
    name: 'drafts.feature',
    code: [
      'Feature: Drafts',
      '',
      '  Scenario: Editor previews a draft',
      '    Given the following "page" content exists:',
      '      | title    | status |',
      '      | About us | 0      |',
      '    And I am logged in as a user with the role "editor"',
      '    When I visit the "page" content page with the title "About us"',
      '    Then I should see the heading "About us"',
      '    And the "page" content with the title "About us" should not be published',
    ].join('\n'),
    caption: 'Drupal steps create the page and the editor, check what the editor sees, and delete both after the scenario.',
  },
  {
    name: 'cookies.feature',
    code: [
      'Feature: Cookie banner',
      '',
      '  @javascript',
      '  Scenario: Visitor accepts the cookies',
      '    When I visit "/"',
      '    And I click on the element "#accept-cookies"',
      '    Then the cookie "consent" should exist',
      '    And the element ".cookie-banner" should not be displayed',
      '    And the page should pass accessibility checks',
    ].join('\n'),
    caption: 'Web steps drive the browser of a @javascript scenario: they click, read cookies and run accessibility checks.',
  },
  {
    name: 'api.feature',
    code: [
      'Feature: Articles API',
      '',
      '  Scenario: Client lists the articles',
      '    Given the REST header "Accept" has the value "application/json"',
      '    When I send a REST "GET" request to "/api/articles"',
      '    Then the REST response status code should be 200',
      '    And the REST response should contain "About us"',
    ].join('\n'),
    caption: 'REST steps set headers, send requests and check the status code and the body of the response.',
  },
];

export const WORKS_WITH = [
  ...BEHAT_VERSIONS.map((version) => `Behat ${version}`),
  'Mink',
  'BrowserKit',
  'Selenium',
  'Chrome DevTools',
  ...DRUPAL_VERSIONS.map((version) => `Drupal ${version}`),
  'Drush',
  ...PHP_VERSIONS.map((version) => `PHP ${version}`),
];

const features = (counts) => [
  {
    icon: 'globe',
    title: 'Web steps',
    text: `${counts.webSteps.pages} traits for pages, forms, links, cookies, JSON, XML and REST APIs, on any website.`,
    to: '/web-steps',
  },
  {
    icon: 'drop',
    title: 'Drupal steps',
    text: `${counts.drupalSteps.pages} traits for content, users, media, menus, email, queues, caches and config.`,
    to: '/drupal-steps',
  },
  {
    icon: 'code',
    title: 'Helpers behind every step',
    text: 'Each step is a thin wrapper over a named helper, so a step of your own reuses the same code.',
    to: '/web-helpers',
  },
  {
    icon: 'layers',
    title: 'Backends by capability',
    text: 'A step names the capability it needs, so clearing caches runs in-process or through Drush.',
    to: '/guides/configuration#backend-resolution',
  },
  {
    icon: 'browser',
    title: 'Browser drivers',
    text: 'BrowserKit for plain HTTP, and Selenium or Chrome DevTools for @javascript scenarios.',
    to: '/installation#javascript-browser-drivers',
  },
  {
    icon: 'trash',
    title: 'Cleanup after each scenario',
    text: 'The entities a scenario creates are deleted after it, in reverse creation order.',
    to: '/guides/usage#entities-a-scenario-creates',
  },
  {
    icon: 'sparkles',
    title: 'References for AI agents',
    text: 'STEPS.md and HELPERS.md ship with the package, so a coding agent reads them from vendor/.',
    to: '/guides/ai-assistants',
  },
  {
    icon: 'refresh',
    title: 'Guided upgrade from 3.x',
    text: 'MIGRATION.md lists every changed name, and an AI agent skill can apply it to your project.',
    to: '/migrating',
  },
];

const FLOW = [
  { icon: 'file', title: 'Scenario', text: 'Your feature file, in Gherkin, written with the step text the library ships.' },
  { icon: 'steps', title: 'Step', text: 'A step trait on your context matches the line and passes its arguments to a helper.' },
  { icon: 'code', title: 'Helper', text: 'A named method does the work, and your own steps can call it too.' },
  { icon: 'layers', title: 'Backend', text: 'The browser session, or the first backend in your list that can do the job, such as Drupal or Drush.' },
];

const REASONS = [
  {
    icon: 'flask',
    title: 'Tested against itself',
    text: 'Every step runs in the library\'s own suite on a real Drupal site, and the suite tests the failures as well as the passes.',
  },
  {
    icon: 'text',
    title: 'Steps that read alike',
    text: 'Every step follows the same wording rules, so the next trait you pick up reads like the last one.',
  },
  {
    icon: 'shield',
    title: 'No clashes with your code',
    text: 'Every method starts with the name of its trait, such as cookieGetAll(), so none collides with a method of your context.',
  },
];

const STEPS = [
  {
    title: 'Install the package',
    text: 'Composer brings Behat, Mink and everything else the steps run on.',
    command: INSTALL_COMMAND,
    name: 'install command',
  },
  {
    title: 'Register a context',
    text: (
      <>
        Add <code>WebContext</code>, or <code>DrupalContext</code> for a Drupal site, and the extension to your <code>behat.php</code>.{' '}
        <Link to="/quick-start#2-enable-the-extension">See the whole file</Link>.
      </>
    ),
  },
  {
    title: 'Run a scenario',
    text: 'Write a scenario with the shipped steps, then run Behat.',
    command: RUN_COMMAND,
    name: 'Behat command',
  },
];

const DOCS = [
  { icon: 'info', title: 'Introduction', text: 'What Behat Steps is and how it fits together.', to: '/introduction' },
  { icon: 'download', title: 'Installation', text: 'The package, its optional dependencies and the browser drivers.', to: '/installation' },
  { icon: 'zap', title: 'Quick start', text: 'From an empty behat.php to a first scenario.', to: '/quick-start' },
  { icon: 'book', title: 'Usage', text: 'The 3 entry points, trait options and the cleanup after each scenario.', to: '/guides/usage' },
  { icon: 'sliders', title: 'Configuration', text: 'Suites, extension options, backends, tags and environment variables.', to: '/guides/configuration' },
  { icon: 'package', title: 'Extending', text: 'Steps, extension points and backends of your own.', to: '/extending' },
  { icon: 'refresh', title: 'Migrating from 3.x', text: 'Upgrade by hand or with an AI coding agent.', to: '/migrating' },
  { icon: 'layers', title: 'Architecture', text: 'The 3 layers and the flows between them, with diagrams.', to: '/architecture' },
];

// Hides the blocks below the fold and shows each one as it scrolls into view.
// Without JavaScript, or for a reader who prefers reduced motion, every block
// stays visible.
export const useReveal = (root) => {
  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || typeof IntersectionObserver === 'undefined') {
      return undefined;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries
          .filter((entry) => entry.isIntersecting)
          .forEach((entry) => {
            entry.target.dataset.revealed = 'true';
            observer.unobserve(entry.target);
          });
      },
      { rootMargin: '0px 0px -10% 0px' },
    );

    root.current.querySelectorAll('[data-reveal]').forEach((block) => {
      // A block already in view stays as it is, so nothing on screen flickers.
      if (block.getBoundingClientRect().top < window.innerHeight) {
        return;
      }

      block.dataset.revealed = 'false';
      observer.observe(block);
    });

    return () => observer.disconnect();
  }, [root]);
};

// The 4 bars of the logo, rising from left to right, with the first one
// tilted as it is there.
const Bars = ({ className }) => (
  <svg className={clsx(styles.barsArt, className)} viewBox="0 0 240 200" aria-hidden="true" focusable="false">
    <g transform="rotate(-6 26 131)">
      <rect className={styles.barBlue} x="6" y="62" width="40" height="138" rx="14" />
    </g>
    <rect className={styles.barGreen} x="66" y="44" width="40" height="156" rx="14" />
    <rect className={styles.barOrange} x="126" y="22" width="40" height="178" rx="14" />
    <rect className={styles.barYellow} x="186" y="0" width="40" height="200" rx="14" />
  </svg>
);

const SectionHead = ({ eyebrow, title, lead, centered = false }) => (
  <div className={clsx(styles.sectionHead, centered && styles.centered)} data-reveal="">
    <p className={styles.eyebrow}>{eyebrow}</p>
    <h2 className={styles.sectionTitle}>{title}</h2>
    {lead && <p className={styles.lead}>{lead}</p>}
  </div>
);

const Hero = ({ steps }) => (
  <section className={styles.hero}>
    <div className={styles.wrap}>
      <div className={styles.heroGrid}>
        <div className={styles.heroText}>
          <p className={styles.badge}>For any website and every Drupal site</p>
          <h1 className={styles.heroTitle}>
            <span className={styles.titleLine}>The Behat steps you need,</span>{' '}
            <span className={styles.titleLine}>
              <span className={styles.underline}>already written.</span>
            </span>
          </h1>
          <p className={styles.heroLead}>
            Behat Steps is a library of {steps} tested steps for pages, forms, APIs and Drupal sites. Write readable scenarios on day one, and build steps of your own on the same helpers when you need them.
          </p>
          <div className={styles.actions}>
            <Link className={clsx(styles.button, styles.primary)} to="/installation">
              Get started
              <Icon name="arrowRight" className={styles.buttonIcon} />
            </Link>
            <Link className={clsx(styles.button, styles.ghost)} to="/introduction">
              Read the docs
            </Link>
          </div>
        </div>
        <div className={styles.heroMedia}>
          <Bars className={styles.heroBars} />
          <FeatureFile files={FEATURE_FILES} label="Example feature files" />
        </div>
      </div>
      <div className={styles.install}>
        <p className={styles.installLabel}>Install with Composer</p>
        <CopyCommand command={INSTALL_COMMAND} name="install command" />
        <p className={styles.installNote}>
          <Icon name="check" className={styles.noteIcon} />
          Needs PHP {PHP_VERSIONS[0]} or newer. Composer installs Behat and Mink with it.
        </p>
      </div>
    </div>
  </section>
);

const Stats = ({ counts }) => {
  const stats = [
    { value: counts.webSteps.entries + counts.drupalSteps.entries, label: 'Steps, each with an example' },
    { value: counts.webSteps.pages + counts.drupalSteps.pages, label: 'Traits for web and Drupal' },
    { value: counts.webHelpers.entries + counts.drupalHelpers.entries, label: 'Helpers for steps of your own' },
    { value: '2018', label: 'Released since' },
  ];

  return (
    <section className={styles.stats}>
      <div className={styles.wrap}>
        <dl className={styles.statsGrid} data-reveal="children">
          {stats.map(({ value, label }, index) => (
            <div key={label} className={clsx(styles.stat, accent(index))}>
              <dt className={styles.statLabel}>{label}</dt>
              <dd className={styles.statValue}>{value}</dd>
            </div>
          ))}
        </dl>
        <div className={styles.worksWith} data-reveal="">
          <p className={styles.worksWithLabel} id="works-with">
            Works with
          </p>
          <ul className={styles.chips} aria-labelledby="works-with">
            {WORKS_WITH.map((name, index) => (
              <li key={name} className={clsx(styles.chip, accent(index))}>
                {name}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  );
};

const Card = ({ icon, title, text, to, index }) => (
  <article className={clsx(styles.card, accent(index))}>
    <span className={styles.iconTile}>
      <Icon name={icon} />
    </span>
    <h3 className={styles.cardTitle}>
      {to ? (
        <Link to={to} className={styles.stretchedLink}>
          {title}
        </Link>
      ) : (
        title
      )}
    </h3>
    <p className={styles.cardText}>{text}</p>
  </article>
);

const Home = () => {
  const root = useRef(null);
  const counts = usePluginData('reference-counts');

  useReveal(root);

  return (
    <Layout
      title="Reusable Behat steps for PHP and Drupal projects"
      description="Behat Steps is a collection of reusable Behat steps for any website and every Drupal site, with an example for each step and the helpers behind them."
    >
      <main ref={root} className={styles.home}>
        <Hero steps={counts.webSteps.entries + counts.drupalSteps.entries} />
        <Stats counts={counts} />

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="What you get" title="Steps and helpers for the whole suite" lead="Every step comes with an example, and every helper with its signature and a summary, in references generated from the code." />
            <div className={styles.featureGrid} data-reveal="children">
              {features(counts).map((feature, index) => (
                <Card key={feature.title} {...feature} index={index} />
              ))}
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead centered eyebrow="How it fits together" title="From a line of Gherkin to your site" lead="Each line of a scenario goes through the same 3 layers, so a step you write yourself can call the same helpers as the shipped ones." />
            <ol className={styles.flow} data-reveal="children">
              {FLOW.map((node, index) => (
                <li key={node.title} className={clsx(styles.flowNode, accent(index))}>
                  <span className={styles.iconTile}>
                    <Icon name={node.icon} />
                  </span>
                  <h3 className={styles.cardTitle}>{node.title}</h3>
                  <p className={styles.cardText}>{node.text}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="Built to be trusted" title="Why teams pick Behat Steps" />
            <div className={styles.threeGrid} data-reveal="children">
              {REASONS.map((reason, index) => (
                <Card key={reason.title} {...reason} index={index} />
              ))}
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="Get going" title="Up and running in 3 steps" />
            <ol className={styles.steps} data-reveal="children">
              {STEPS.map((step, index) => (
                <li key={step.title} className={clsx(styles.step, accent(index))}>
                  <span className={styles.stepNumber} aria-hidden="true">
                    {index + 1}
                  </span>
                  <h3 className={styles.stepTitle}>{step.title}</h3>
                  <p className={styles.cardText}>{step.text}</p>
                  {step.command && <CopyCommand compact command={step.command} name={step.name} className={styles.stepCommand} />}
                </li>
              ))}
            </ol>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="Keep exploring" title="Jump into the docs" />
            <div className={styles.docsGrid} data-reveal="children">
              {DOCS.map((doc, index) => (
                <Link key={doc.title} to={doc.to} className={clsx(styles.docCard, accent(index))}>
                  <span className={clsx(styles.iconTile, styles.iconTileSmall)}>
                    <Icon name={doc.icon} />
                  </span>
                  <span className={styles.docBody}>
                    <span className={styles.docTitle}>
                      {doc.title}
                      <Icon name="arrowUpRight" className={styles.docArrow} />
                    </span>
                    <span className={styles.cardText}>{doc.text}</span>
                  </span>
                </Link>
              ))}
            </div>
          </div>
        </section>

        <section className={clsx(styles.section, styles.ctaSection)}>
          <div className={styles.wrap}>
            <div className={styles.cta} data-reveal="">
              <Bars className={styles.ctaBars} />
              <h2 className={styles.ctaTitle}>Write scenarios your whole team can read.</h2>
              <p className={styles.ctaLead}>
                Free and open source, and tested on every change against Drupal {listOf(DRUPAL_VERSIONS)} on Behat {listOf(BEHAT_VERSIONS)}.
              </p>
              <CopyCommand command={INSTALL_COMMAND} name="install command" className={styles.ctaCommand} />
              <div className={clsx(styles.actions, styles.ctaActions)}>
                <Link className={clsx(styles.button, styles.primary)} to="/installation">
                  Get started
                  <Icon name="arrowRight" className={styles.buttonIcon} />
                </Link>
                <Link className={clsx(styles.button, styles.ghost)} href={GITHUB_URL}>
                  <Icon name="github" className={styles.buttonIcon} />
                  Star on GitHub
                  {NEW_TAB}
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
};

export default Home;

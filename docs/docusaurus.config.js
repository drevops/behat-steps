// @ts-check
// @see https://docusaurus.io/docs/api/docusaurus-config

import path from 'node:path';
import {darkCodeTheme, lightCodeTheme} from './src/codeThemes.js';
import repositoryLinks from './src/remark/repositoryLinks.js';

// The release workflow sets the version. Other builds, such as pull request
// previews, are not indexed.
const isRelease = Boolean(process.env.RELEASE_VERSION);

const REPOSITORY_URL = 'https://github.com/drevops/behat-steps';

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Behat Steps',
  tagline: 'A collection of Behat steps for PHP and Drupal projects',
  favicon: 'img/favicon.png',
  noIndex: !isRelease,

  // The release workflow sets both from the GitHub Pages settings, so a
  // custom domain needs no change here.
  url: process.env.DOCS_URL || 'https://behat-steps.drevops.com',
  baseUrl: process.env.DOCS_BASE_URL || '/',

  // GitHub Pages serves '<page>.html' at '/<page>', without the redirect that
  // a '<page>/index.html' directory page needs.
  trailingSlash: false,

  organizationName: 'drevops',
  projectName: 'behat-steps',

  onBrokenLinks: 'throw',
  onBrokenAnchors: 'throw',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          path: 'content',
          editUrl: `${REPOSITORY_URL}/edit/main/docs/`,
          // Runs before Docusaurus resolves links, which would reject a link
          // to a Markdown file outside the content directory.
          beforeDefaultRemarkPlugins: [
            [repositoryLinks, {
              repositoryRoot: path.resolve(__dirname, '..'),
              contentDir: path.resolve(__dirname, 'content'),
              repositoryUrl: REPOSITORY_URL,
              ref: process.env.RELEASE_VERSION || 'main',
            }],
          ],
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  markdown: {
    // A '.md' page is CommonMark, so the generated references keep their raw
    // HTML and render as they do on GitHub.
    format: 'detect',
    hooks: {
      onBrokenMarkdownLinks: 'throw',
    },
  },

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import("@easyops-cn/docusaurus-search-local").PluginOptions} */
      ({
        // @see https://github.com/easyops-cn/docusaurus-search-local#theme-options
        docsDir: 'content',
        docsRouteBasePath: '/',
        indexBlog: false,
        hashed: true,
        highlightSearchTermsOnTargetPage: true,
        explicitSearchResultPath: true,
        // The 'noindex' robots meta of a non-release build is meant for search
        // engines, and would otherwise leave the site's own index empty.
        forceIgnoreNoIndex: true,
      }),
    ],
  ],

  themeConfig:
  /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      image: 'img/social-card.png',
      metadata: [
        {name: 'keywords', content: 'behat, bdd, gherkin, mink, drupal, php, testing, step definitions'},
      ],
      docs: {
        sidebar: {
          hideable: true,
          autoCollapseCategories: true,
        },
      },
      navbar: {
        title: 'Behat Steps',
        logo: {
          alt: 'Behat Steps logo',
          src: 'img/logo.png',
        },
        items: [
          {to: '/steps', label: 'Steps', position: 'left'},
          {to: '/helpers', label: 'Helpers', position: 'left'},
          {
            href: 'https://packagist.org/packages/drevops/behat-steps',
            label: 'Packagist',
            position: 'right',
            title: 'View the package on Packagist',
          },
          {
            href: REPOSITORY_URL,
            label: 'GitHub',
            position: 'right',
            title: 'View source on GitHub',
          },
          {
            type: 'search',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              {label: 'Installation', to: '/installation'},
              {label: 'Quick start', to: '/quick-start'},
              {label: 'Steps', to: '/steps'},
              {label: 'Helpers', to: '/helpers'},
            ],
          },
          {
            title: 'Project',
            items: [
              {label: 'GitHub', href: REPOSITORY_URL},
              {label: 'Releases', href: `${REPOSITORY_URL}/releases`},
              {label: 'Issues', href: `${REPOSITORY_URL}/issues`},
              {label: 'Packagist', href: 'https://packagist.org/packages/drevops/behat-steps'},
            ],
          },
          {
            title: 'Community',
            items: [
              {label: 'Slack', href: 'https://drupal.slack.com/archives/C4T2JHG9K'},
              {label: 'Vortex', href: 'https://www.vortextemplate.com'},
            ],
          },
        ],
        copyright: `Behat Steps version: ${process.env.RELEASE_VERSION || 'development'}<br/>Drupal is a <a class="footer__copyright-link" href="https://www.drupal.org/about/trademark">registered trademark</a> of <a class="footer__copyright-link" href="https://dri.es/">Dries Buytaert</a>.<br/>Copyright ©${new Date().getFullYear()} <a class="footer__copyright-link" href="https://www.drevops.com/">DrevOps®</a>. Built with <a class="footer__copyright-link" href="https://docusaurus.io/">Docusaurus</a>.`,
      },
      prism: {
        theme: lightCodeTheme,
        darkTheme: darkCodeTheme,
        additionalLanguages: ['bash', 'gherkin', 'json', 'php'],
      },
      colorMode: {
        defaultMode: 'light',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

export default config;

<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Filter\TagFilter;
use Behat\Config\GherkinOptions;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension;
use DrevOps\BehatPhpServer\PhpServerContext;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use DVDoug\Behat\CodeCoverage\Extension as CodeCoverageExtension;

$suite = (new Suite('default', ['drivers' => ['drupal', 'drush', 'blackbox']]))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(FeatureContext::class)
  ->addContext(BehatCliContext::class)
  ->addContext(MinkContext::class)
  ->addContext(ScreenshotContext::class)
  ->addContext(PhpServerContext::class, [
    'webroot' => '%paths.base%/tests/behat/fixtures',
    'protocol' => 'http',
    'host' => '0.0.0.0',
    'port' => 8888,
    'debug' => FALSE,
  ]);

$default = (new Profile('default', ['autoload' => ['%paths.base%/tests/behat/bootstrap']]))
  // Disable the Gherkin cache during development.
  ->withGherkinOptions((new GherkinOptions(['cache' => '']))->withFilter(new TagFilter('~@skipped')))
  ->withSuite($suite)
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://nginx:8080',
    'files_path' => '%paths.base%/tests/behat/fixtures',
    'browser_name' => 'chrome',
    'javascript_session' => 'selenium2',
    'sessions' => [
      'browserkit_http' => ['browserkit_http' => NULL],
      'selenium2' => [
        'selenium2' => [
          'wd_host' => 'http://chrome:4444/wd/hub',
          'capabilities' => [
            'browser' => 'chrome',
            'extra_capabilities' => [
              // '--disable-gpu' is required where no GPU is available, such as
              // containers and CI runners. The rest increase stability and speed.
              'goog:chromeOptions' => ['args' => ['--disable-gpu', '--disable-extensions', '--disable-infobars', '--disable-popup-blocking', '--disable-translate', '--no-first-run', '--test-type']],
            ],
          ],
        ],
      ],
    ],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'blackbox' => NULL,
    // Behat runs from within "build", so both roots are relative to it.
    'drupal' => ['drupal_root' => 'web'],
    'drush' => [
      'root' => 'web',
      // Drush resolves a request URI for every command and does not
      // bootstrap without one.
      'global_options' => '--uri=http://nginx:8080',
    ],
    'selectors' => [
      'messages' => ['default' => '.messages', 'error' => '.messages.messages--error', 'success' => '.messages.messages--status', 'warning' => '.messages.messages--warning'],
    ],
    'regions' => ['content' => '#content', 'sidebar' => '#sidebar', 'footer' => '#footer'],
    'mappings' => ['paths' => ['User Login' => '/user/login', 'User Registration' => '/user/register']],
  ]))
  ->withExtension(new Extension(BehatScreenshotExtension::class, [
    'dir' => '%paths.base%/.logs/screenshots',
    'purge' => FALSE,
    'on_failed' => TRUE,
    'always_fullscreen' => TRUE,
    'info_types' => ['url', 'feature', 'step', 'datetime'],
  ]));

// A build that cannot install the coverage extension runs without it.
if (class_exists(CodeCoverageExtension::class)) {
  $default->withExtension(new Extension(CodeCoverageExtension::class, [
    'filter' => ['include' => ['directories' => ['%paths.base%/src' => NULL]]],
    'reports' => [
      'text' => ['showColors' => TRUE, 'showOnlySummary' => TRUE],
      'html' => ['target' => '%paths.base%/.logs/coverage/behat/.coverage-html'],
      'cobertura' => ['target' => '%paths.base%/.logs/coverage/behat/cobertura.xml'],
      'php' => ['target' => '%paths.base%/.logs/coverage/behat/phpcov.php'],
    ],
  ]));
}

// Drives headless Chrome directly over the DevTools Protocol, with no Selenium
// server. Run with "behat -p chrome_headless". It inherits the "default"
// profile and swaps only the JavaScript session to "chrome", a session on the
// driver that ChromeExtension registers.
$chrome_headless = (new Profile('chrome_headless'))
  ->withExtension(new Extension(ChromeExtension::class))
  ->withExtension(new Extension(MinkExtension::class, ['javascript_session' => 'chrome', 'sessions' => ['chrome' => ['chrome' => ['api_url' => 'http://chrome_headless:9222']]]]));

return (new Config())->withProfile($default)->withProfile($chrome_headless);

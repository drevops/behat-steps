<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\ServiceContainer\Driver;

use Drupal\Tests\DrupalTestBrowser;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\MinkExtension\ServiceContainer\Driver\BrowserKitFactory as UpstreamBrowserKitFactory;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use DrupalFinder\DrupalFinderComposerRuntime;
use GuzzleHttp\Client;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Builds the 'browserkit_http' driver on top of Drupal's own test browser.
 *
 * Mink's own factory drives a bare BrowserKit client. Drupal ships
 * 'Drupal\Tests\DrupalTestBrowser', which understands the site's session
 * cookies and its test-run headers, so a scenario reaches the same request
 * pipeline Drupal's functional tests use.
 *
 * The class is not autoloadable - it lives under a Drupal root that Composer
 * does not map - so the root is located and the file included before the
 * definition names it. The root comes from Composer's own record of where
 * 'drupal/core' was installed, which does not depend on the working directory
 * Behat happens to run from.
 *
 * @see \Behat\MinkExtension\ServiceContainer\Driver\BrowserKitFactory
 */
class BrowserKitFactory extends UpstreamBrowserKitFactory {

  /**
   * Path of Drupal's test browser relative to the Drupal root.
   */
  protected const TEST_BROWSER_PATH = '/core/tests/Drupal/Tests/DrupalTestBrowser.php';

  /**
   * Class name of Drupal's test browser.
   */
  protected const TEST_BROWSER_CLASS = DrupalTestBrowser::class;

  /**
   * Guzzle request options a configured value merges over.
   *
   * Redirects stay off so a step can assert on the redirecting response
   * itself, and cookies stay on so a login survives across requests. Both are
   * load-bearing for the step vocabulary, so configuring one option does not
   * drop the others.
   */
  protected const DEFAULT_REQUEST_OPTIONS = [
    'allow_redirects' => FALSE,
    'cookies' => TRUE,
  ];

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $config
   *   Driver configuration.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   When no Drupal installation is found, or it ships no test browser.
   */
  public function buildDriver(array $config): Definition {
    $this->requireTestBrowser($this->locateDrupalRoot());

    $request_options = array_replace(self::DEFAULT_REQUEST_OPTIONS, $config['guzzle_request_options'] ?? []);

    $client = (new Definition(self::TEST_BROWSER_CLASS))
      ->addMethodCall('setClient', [new Definition(Client::class, [$request_options])]);

    return new Definition(BrowserKitDriver::class, [$client, '%mink.base_url%']);
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    // @formatter:off
    // phpcs:disable
    $builder
      ->children()
        ->arrayNode('guzzle_request_options')
          ->prototype('variable')->end()
          ->info('Guzzle request options. See "\GuzzleHttp\RequestOptions".')
        ->end()
      ->end();
    // phpcs:enable
    // @formatter:on
  }

  /**
   * Returns the Drupal root, or throws when there is none.
   *
   * @return string
   *   Absolute path to the Drupal root.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   When no Drupal installation is found.
   */
  protected function locateDrupalRoot(): string {
    $root = $this->resolveDrupalRoot();

    if ($root === NULL || $root === '') {
      throw new BootstrapException('No Drupal installation found. The "browserkit_http" driver runs against a local Drupal codebase, so "drupal/core" must be installed alongside the suite.');
    }

    return $root;
  }

  /**
   * Returns the path Composer recorded for 'drupal/core', less 'core'.
   *
   * @return string|null
   *   Absolute path to the Drupal root, or NULL when none is installed.
   */
  protected function resolveDrupalRoot(): ?string {
    return (new DrupalFinderComposerRuntime())->getDrupalRoot();
  }

  /**
   * Loads Drupal's test browser from the given root.
   *
   * @param string $drupal_root
   *   Absolute path to the Drupal root.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   When the root ships no test browser.
   */
  protected function requireTestBrowser(string $drupal_root): void {
    if ($this->testBrowserIsLoaded()) {
      return;
    }

    $path = $drupal_root . self::TEST_BROWSER_PATH;

    if (!is_file($path)) {
      throw new BootstrapException(sprintf('Drupal at "%s" ships no test browser at "%s".', $drupal_root, self::TEST_BROWSER_PATH));
    }

    require_once $path;
  }

  /**
   * Whether Drupal's test browser is already loaded.
   *
   * A second 'require_once' keys on the file path, so a class loaded from a
   * different path would be redeclared rather than skipped.
   */
  protected function testBrowserIsLoaded(): bool {
    return class_exists(self::TEST_BROWSER_CLASS);
  }

}

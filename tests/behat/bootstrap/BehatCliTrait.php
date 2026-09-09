<?php

/**
 * @file
 * Trait to test Behat script by using Behat cli.
 *
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */

declare(strict_types=1);

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;

/**
 * Trait BehatCliTrait.
 *
 * Additional shortcut steps for BehatCliContext.
 */
trait BehatCliTrait {

  /**
   * Traits every generated context composes, on top of the ones under test.
   *
   * @var array<int, string>
   */
  protected const BEHAT_CLI_BASELINE_TRAITS = [
    'Generic\PathTrait',
    'Drupal\ContentTrait',
    'Drupal\UserTrait',
  ];

  #[BeforeScenario]
  public function behatCliBeforeScenario(BeforeScenarioScope $scope): void {
    $this->behatCliCopyFixtures();

    $traits = [];

    // Scan scenario tags and extract trait names from tags starting with
    // 'trait:'. For example, @trait:PathTrait or @trait:Drupal\\UserTrait.
    foreach ($scope->getScenario()->getTags() as $tag) {
      if (str_starts_with($tag, 'trait:')) {
        $tags = trim(substr($tag, strlen('trait:')));
        $tags = explode(',', $tags);
        $tags = array_map(fn(string $value): string => trim(str_replace('\\\\', '\\', $value)), $tags);
        $traits = array_merge($traits, $tags);
        break;
      }
    }

    $traits = array_filter($traits);
    $traits = array_unique($traits);

    // Only create FeatureContext.php if there is at least one '@trait:' tag.
    if (empty($traits)) {
      return;
    }

    $this->behatCliWriteFeatureContextFile($traits);
  }

  #[BeforeStep]
  public function behatCliBeforeStep(): void {
    // Drupal Extension >= ^5 is coupled with Drupal core's DrupalTestBrowser.
    // This requires Drupal root to be discoverable when running Behat from a
    // random directory using Drupal Finder.
    //
    // Set environment variables for Drupal Finder.
    // This requires Drupal Finder version > 1.2 at commit:
    // @see https://github.com/webflo/drupal-finder/commit/2663b117878f4a45ca56df028460350c977f92c0
    $this->iSetEnvironmentVariable('DRUPAL_FINDER_DRUPAL_ROOT', '/app/build/web');
    $this->iSetEnvironmentVariable('DRUPAL_FINDER_COMPOSER_ROOT', '/app/build');
    $this->iSetEnvironmentVariable('DRUPAL_FINDER_VENDOR_DIR', '/app/build/vendor');
  }

  /**
   * Create FeatureContext.php file.
   *
   * @param array $traits
   *   Optional array of trait classes.
   *
   * @return string
   *   Path to written file.
   */
  public function behatCliWriteFeatureContextFile(array $traits = []): string {
    $tokens = [
      '{{USE_DECLARATION}}' => '',
      '{{USE_IN_CLASS}}' => '',
    ];

    // Navigation and session steps appear in nearly every generated scenario
    // as setup for the trait under test, so the baseline carries them. A
    // baseline trait that is itself under test is composed once.
    $qualified_traits = [];

    foreach (array_merge(static::BEHAT_CLI_BASELINE_TRAITS, $traits) as $trait) {
      // A tag names the trait's context and short name, as in
      // 'Drupal\ModuleTrait'. A tag with no context names a generic trait.
      $qualified_traits[] = str_contains((string) $trait, '\\') ? $trait : 'Generic\\' . $trait;
    }

    foreach (array_unique($qualified_traits) as $qualified) {
      // Two contexts can hold the same short name, so each import carries a
      // context-qualified alias and one tag can name both.
      $alias = str_replace('\\', '_', (string) $qualified);

      $tokens['{{USE_DECLARATION}}'] .= sprintf('use DrevOps\\BehatSteps\\Steps\\%s as %s;' . PHP_EOL, $qualified, $alias);
      $tokens['{{USE_IN_CLASS}}'] .= sprintf('use %s;' . PHP_EOL, $alias);
    }

    $content = <<<'EOL'
<?php

use DrevOps\BehatSteps\Behat\Context\RawContext;
{{USE_DECLARATION}}

class FeatureContext extends RawContext {
  {{USE_IN_CLASS}}

  use FeatureContextTrait;

  /**
   * @Given I throw test exception with message :message
   */
  public function throwTestException($message) {
    throw new \RuntimeException($message);
  }

  /**
   * Log an error after the last step result has been composed.
   *
   * @AfterScenario @test-watchdog-teardown
   */
  public function testSetWatchdogErrorInTeardown() {
    $this->assertDrupal();

    \Drupal::logger('php')->log('warning', 'test');
  }

}
EOL;

    $content = strtr($content, $tokens);
    $content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);

    $filename = 'features/bootstrap/FeatureContext.php';
    $this->createFileInWorkingDir($filename, $content);

    $feature_context_trait_content = file_get_contents(__DIR__ . '/FeatureContextTrait.php');
    if ($feature_context_trait_content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to access file "%s"', __DIR__ . '/FeatureContextTrait.php'));
    }
    $feature_context_trait = 'features/bootstrap/FeatureContextTrait.php';
    $this->createFileInWorkingDir($feature_context_trait, $feature_context_trait_content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'FeatureContext.php');
    }

    return $filename;
  }

  #[Given('/^scenario steps(?: tagged with "([^"]*)")?:$/')]
  public function behatCliWriteScenarioSteps(PyStringNode $content, $tags = ''): void {
    $content = strtr((string) $content, ["'''" => '"""']);

    // Make sure that indentation in provided content is accurate.
    $content_lines = explode(PHP_EOL, $content);
    foreach ($content_lines as $k => $content_line) {
      $content_lines[$k] = str_repeat(' ', 4) . trim($content_line);
    }
    $content = implode(PHP_EOL, $content_lines);

    $tokens = [
      '{{SCENARIO_CONTENT}}' => $content,
      '{{ADDITIONAL_TAGS}}' => $tags,
    ];

    $content = <<<'EOL'
Feature: Stub feature';
  @api {{ADDITIONAL_TAGS}}
  Scenario: Stub scenario title
{{SCENARIO_CONTENT}}
EOL;

    $content = strtr($content, $tokens);
    $content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);

    $filename = 'features/stub.feature';
    $this->createFileInWorkingDir($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Feature Stub');
    }
  }

  #[Given('some behat configuration')]
  public function behatCliWriteBehatYml(): void {
    $content = <<<'EOL'
default:
  suites:
    default:
      contexts:
        - FeatureContext
        - Behat\MinkExtension\Context\MinkContext
        - DrevOps\BehatScreenshotExtension\Context\ScreenshotContext
        - DrevOps\BehatPhpServer\PhpServerContext:
            webroot: '%paths.base%/tests/behat/fixtures'
            protocol: http
            host: 0.0.0.0
            port: 8888
            debug: true
  extensions:
    Behat\MinkExtension:
      browserkit_http: ~
      base_url: http://nginx:8080
      files_path: '%paths.base%/tests/behat/fixtures'
      browser_name: chrome
      javascript_session: selenium2
      selenium2:
        wd_host: "http://chrome:4444/wd/hub"
        capabilities:
          browser: chrome
          extra_capabilities:
            "goog:chromeOptions":
              args:
                - '--disable-gpu'            # Disables hardware acceleration required in containers and cloud-based instances (like CI runners) where GPU is not available.
                # Options to increase stability and speed.
                - '--disable-extensions'     # Disables all installed Chrome extensions. Useful in testing environments to avoid interference from extensions.
                - '--disable-infobars'       # Hides the infobar that Chrome displays for various notifications, like warnings when opening multiple tabs.
                - '--disable-popup-blocking' # Disables the popup blocker, allowing all popups to appear. Useful in testing scenarios where popups are expected.
                - '--disable-translate'      # Disables the built-in translation feature, preventing Chrome from offering to translate pages.
                - '--no-first-run'           # Skips the initial setup screen that Chrome typically shows when running for the first time.
                - '--test-type'              # Disables certain security features and UI components that are unnecessary for automated testing, making Chrome more suitable for test environments.

    DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
      api_driver: drupal
      drupal:
        drupal_root: /app/build/web
      selectors:
        messages:
          default: '.messages'
          error: '.messages.messages--error'
          success: '.messages.messages--status'
          warning: '.messages.messages--warning'

    # Capture HTML and JPG screenshots on demand and on failure.
    DrevOps\BehatScreenshotExtension:
      dir: '%paths.base%/.logs/screenshots'
      purge: false # Change to 'true' (no quotes) to purge screenshots on each run.
      on_failed: true
      always_fullscreen: true
      info_types:
        - url
        - feature
        - step
        - datetime
EOL;

    if (static::behatCliIsCoverageEnabled()) {
      // Generate unique coverage filename for this subprocess to avoid conflicts.
      $coverage_id = md5($this->workingDir);
      $coverage_content = <<<EOL

    DVDoug\Behat\CodeCoverage\Extension:
      filter:
        include:
          directories:
            /app/src: ~
      reports:
        text:
          showColors: true
          showOnlySummary: true
        php:
          target: /app/.logs/coverage/behat_cli/phpcov/{$coverage_id}.php
EOL;
      $content .= $coverage_content;
    }

    $filename = 'behat.yml';
    $this->createFileInWorkingDir($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  #[Then('it should fail with an error:')]
  public function behatCliAssertFailWithError(PyStringNode $message): void {
    $this->itShouldPassOrFailWith('fail', $message);
    // Enforce assertion exceptions: ExpectationException and its
    // ElementNotFoundException subclass where a Mink session is available,
    // AssertionException where it is not. Non-assertion failures should be
    // thrown as \RuntimeException.
    $output = $this->getOutput();
    $has_valid_exception = str_contains((string) $output, ' (Behat\Mink\Exception\ExpectationException)')
      || str_contains((string) $output, ' (Behat\Mink\Exception\ElementNotFoundException)')
      || str_contains((string) $output, ' (DrevOps\BehatSteps\Exception\AssertionException)');
    if (!$has_valid_exception) {
      throw new \RuntimeException('The output does not contain an assertion exception string as expected.');
    }
    if (str_contains((string) $output, ' (RuntimeException)')) {
      throw new \RuntimeException('The output contains "(RuntimeException)" string but it should not.');
    }
  }

  #[Then('it should fail with an exception:')]
  public function behatCliAssertFailWithException(PyStringNode $message): void {
    $this->itShouldPassOrFailWith('fail', $message);
    // Enforce \RuntimeException for all non-assertion failures. Assertion
    // failures should be thrown as an assertion exception.
    if (!str_contains($this->getOutput(), ' (RuntimeException)')) {
      throw new \RuntimeException('The output does not contain an "(RuntimeException)" string as expected.');
    }
    if (str_contains($this->getOutput(), ' (Exception)')) {
      throw new \RuntimeException('The output contains "(Exception)" string but it should not.');
    }
  }

  #[Then('it should fail with a :exception exception:')]
  public function behatCliAssertFailWithCustomException(string $exception, PyStringNode $message): void {
    $this->itShouldPassOrFailWith('fail', $message);
    if (!str_contains($this->getOutput(), ' (' . $exception . ')')) {
      throw new \RuntimeException(sprintf('The output does not contain an "(%s)" string as expected.', $exception));
    }
  }

  /**
   * Checks whether last command output does not contain provided string.
   *
   * @param \Behat\Gherkin\Node\PyStringNode $text
   *   PyString text instance.
   */
  #[Then('the output should not contain:')]
  public function theOutputShouldNotContain(PyStringNode $text): void {
    if (str_contains($this->getOutput(), $this->getExpectedOutput($text))) {
      throw new \RuntimeException(sprintf('Output contains "%s" but should not.', $this->getExpectedOutput($text)));
    }
  }

  /**
   * Helper to print file comments.
   */
  protected static function behatCliPrintFileContents(string $filename, string $title = '') {
    if (!is_readable($filename)) {
      throw new \RuntimeException(sprintf('Unable to access file "%s"', $filename));
    }

    $content = file_get_contents($filename);

    print sprintf('-------------------- %s START --------------------', $title) . PHP_EOL;
    print $filename . PHP_EOL;
    print_r($content);
    print PHP_EOL;
    print sprintf('-------------------- %s FINISH --------------------', $title) . PHP_EOL;
  }

  /**
   * Helper to check if debug mode is enabled.
   */
  protected static function behatCliIsDebug(): bool {
    // Change to TRUE to see debug messages for this trait.
    return FALSE;
  }

  /**
   * Helper to check if code coverage is enabled.
   */
  protected static function behatCliIsCoverageEnabled(): bool {
    return ini_get('pcov.enabled') === '1' && !empty(ini_get('pcov.directory'));
  }

  /**
   * Copy fixtures to the working directory.
   */
  protected function behatCliCopyFixtures() {
    // Copy fixtures to the working directory.
    $fixture_path = 'tests/behat/fixtures';
    // @note Hardcoded path to the fixture directory.
    $fixture_path_abs = '/app' . DIRECTORY_SEPARATOR . $fixture_path;
    if (is_dir($fixture_path_abs)) {
      $dst = $this->workingDir . DIRECTORY_SEPARATOR . $fixture_path;
      mkdir($dst, 0777, TRUE);
      // Copy fixtures from the webroot to the working directory.
      foreach (glob($fixture_path_abs . '/*') as $file) {
        // @note Only copy files for speed.
        if (is_file($file)) {
          $filename = basename($file);
          copy($file, $dst . DIRECTORY_SEPARATOR . $filename);
        }
      }
    }
  }

}

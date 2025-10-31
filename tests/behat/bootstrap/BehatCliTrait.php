<?php

/**
 * @file
 * Trait to test Behat script by using Behat cli.
 *
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */

declare(strict_types=1);

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use PHPUnit\Framework\Assert;

/**
 * Trait BehatCliTrait.
 *
 * Additional shortcut steps for BehatCliContext.
 */
trait BehatCliTrait {

  /**
   * @BeforeScenario
   */
  public function behatCliBeforeScenario(BeforeScenarioScope $scope): void {
    $this->behatCliCopyFixtures();

    $traits = [];

    // Scan scenario tags and extract trait names from tags starting with
    // 'trait:'. For example, @trait:PathTrait or @trait:D7\\UserTrait.
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

  /**
   * @BeforeStep
   */
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
    foreach ($traits as $trait) {
      // Check if trait contains slash to determine if it's in a subdirectory.
      $trait_parts = explode('\\', (string) $trait);
      $trait_name = end($trait_parts);
      $trait_namespace = implode('\\', array_slice($trait_parts, 0, -1));

      // Check if the trait is in a subdirectory (indicated by namespace parts)
      if (!empty($trait_namespace)) {
        // The trait name already includes namespace.
        $trait_class = '\\DrevOps\\BehatSteps\\' . $trait;
        $tokens['{{USE_DECLARATION}}'] .= sprintf('use DrevOps\\BehatSteps\\%s;' . PHP_EOL, $trait);
      }
      else {
        // First try to find the trait in the base namespace.
        $trait_class = '\\DrevOps\\BehatSteps\\' . $trait;
        $context_dir = NULL;

        // Check if trait exists in the base namespace.
        if (class_exists($trait_class)) {
          // Get the file path to determine if it's in a subdirectory.
          $reflection = new \ReflectionClass($trait_class);
          $file_path = $reflection->getFileName();

          if ($file_path) {
            // Found in the base namespace.
            $tokens['{{USE_DECLARATION}}'] .= sprintf('use DrevOps\\BehatSteps\\%s;' . PHP_EOL, $trait);
          }
        }
        else {
          // Not found in base namespace, let's check subdirectories
          // Get a list of directories under src/.
          $base_dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
          $dirs = array_filter(glob($base_dir . DIRECTORY_SEPARATOR . '*'), is_dir(...));

          // Convert directory names to potential namespace parts.
          foreach ($dirs as $dir) {
            $context_dir = basename($dir);
            $context_trait_class = sprintf('\\DrevOps\\BehatSteps\\%s\\%s', $context_dir, $trait);

            if (class_exists($context_trait_class)) {
              // Found in a subdirectory.
              $trait_class = $context_trait_class;
              $tokens['{{USE_DECLARATION}}'] .= sprintf('use DrevOps\\BehatSteps\\%s\\%s;' . PHP_EOL, $context_dir, $trait);
              break;
            }
          }

          // If not found in any subdirectory, default to base namespace.
          if (!class_exists($trait_class)) {
            $tokens['{{USE_DECLARATION}}'] .= sprintf('use DrevOps\\BehatSteps\\%s;' . PHP_EOL, $trait);
          }
        }
      }
      $trait_name__parts = explode('\\', (string) $trait);
      $trait_name = end($trait_name__parts);
      $tokens['{{USE_IN_CLASS}}'] .= sprintf('use %s;' . PHP_EOL, $trait_name);
    }

    $content = <<<'EOL'
<?php

use Drupal\DrupalExtension\Context\DrupalContext;
{{USE_DECLARATION}}

class FeatureContext extends DrupalContext {
  {{USE_IN_CLASS}}

  use FeatureContextTrait;

  /**
   * @Given I throw test exception with message :message
   */
  public function throwTestException($message) {
    throw new \RuntimeException($message);
  }

  /**
   * @Given set Drupal7 watchdog error level :level
   * @Given set Drupal7 watchdog error level :level of type :type
   */
  public function setWatchdogErrorDrupal7($level, $type = 'php') {
    watchdog($type, 'test', [], $level);
  }

  /**
   * @Given set watchdog error level :level
   * @Given set watchdog error level :level of type :type
   */
  public function testSetWatchdogError($level, $type = 'php') {
    \Drupal::logger($type)->log($level, 'test');
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

  /**
   * @Given /^scenario steps(?: tagged with "([^"]*)")?:$/
   */
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

  /**
   * @Given some behat configuration
   */
  public function behatCliWriteBehatYml(): void {
    $content = <<<'EOL'
default:
  suites:
    default:
      contexts:
        - FeatureContext
        - Drupal\DrupalExtension\Context\MinkContext
        - DrevOps\BehatPhpServer\PhpServerContext:
            webroot: '%paths.base%/tests/behat/fixtures'
            protocol: http
            host: 0.0.0.0
            port: 8888
            debug: true
  extensions:
    Drupal\MinkExtension:
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

    Drupal\DrupalExtension:
      api_driver: drupal
      drupal:
        drupal_root: /app/build/web
EOL;

    $filename = 'behat.yml';
    $this->createFileInWorkingDir($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  /**
   * @Then it should fail with an error:
   */
  public function behatCliAssertFailWithError(PyStringNode $message): void {
    $this->itShouldPassOrFailWith('fail', $message);
    // Enforce \Exception for all assertion exceptions. Non-assertion
    // exceptions should be thrown as \RuntimeException.
    Assert::assertStringContainsString(' (Exception)', $this->getOutput());
    Assert::assertStringNotContainsString(' (RuntimeException)', $this->getOutput());
  }

  /**
   * @Then it should fail with an exception:
   */
  public function behatCliAssertFailWithException(PyStringNode $message): void {
    $this->itShouldPassOrFailWith('fail', $message);
    // Enforce \RuntimeException for all non-assertion exceptions. Assertion
    // exceptions should be thrown as \Exception.
    Assert::assertStringContainsString(' (RuntimeException)', $this->getOutput());
    Assert::assertStringNotContainsString(' (Exception)', $this->getOutput());
  }

  /**
   * @Then it should fail with a :exception exception:
   */
  public function behatCliAssertFailWithCustomException(string $exception, PyStringNode $message): void {
    $this->itShouldPassOrFailWith('fail', $message);
    // Enforce \RuntimeException for all non-assertion exceptions. Assertion
    // exceptions should be thrown as \Exception.
    Assert::assertStringContainsString(' (' . $exception . ')', $this->getOutput());
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

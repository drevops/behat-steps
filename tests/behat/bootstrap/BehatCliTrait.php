<?php

/**
 * @file
 * Trait to test Behat script by using Behat cli.
 *
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */

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
  public function behatCliBeforeScenario(BeforeScenarioScope $scope) {
    $traits = [];

    // Scan scenario tags and extract trait names from tags starting with
    // 'trait:'. For example, @trait:PathTrait or @trait:D7\\UserTrait.
    foreach ($scope->getScenario()->getTags() as $tag) {
      if (strpos($tag, 'trait:') === 0) {
        $tags = trim(substr($tag, strlen('trait:')));
        $tags = explode(',', $tags);
        $tags = array_map(function ($value) {
          return trim(str_replace('\\\\', '\\', $value));
        }, $tags);
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
  public function behatCliBeforeStep() {
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
  public function behatCliWriteFeatureContextFile(array $traits = []) {
    $tokens = [
      '{{USE_DECLARATION}}' => '',
      '{{USE_IN_CLASS}}' => '',
    ];
    foreach ($traits as $trait) {
      $tokens['{{USE_DECLARATION}}'] .= sprintf('use DrevOps\\BehatSteps\\%s;' . PHP_EOL, $trait);
      $trait_name__parts = explode('\\', $trait);
      $trait_name = end($trait_name__parts);
      $tokens['{{USE_IN_CLASS}}'] .= sprintf('use %s;' . PHP_EOL, $trait_name);
    }

    $content = <<<'EOL'
<?php

use Drupal\DrupalExtension\Context\DrupalContext;
{{USE_DECLARATION}}

class FeatureContext extends DrupalContext {
  {{USE_IN_CLASS}}
  
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
  public function setWatchdogErrorDrupal9($level, $type = 'php') {
    \Drupal::logger($type)->log($level, 'test');
  }
      
}
EOL;

    $content = strtr($content, $tokens);
    $content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/bootstrap/FeatureContext.php';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'FeatureContext.php');
    }

    return $filename;
  }

  /**
   * @Given /^scenario steps(?: tagged with "([^"]*)")?:$/
   */
  public function behatCliWriteScenarioSteps(PyStringNode $content, $tags = '') {
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

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/stub.feature';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Feature Stub');
    }
  }

  /**
   * @Given some behat configuration
   */
  public function behatCliWriteBehatYml() {
    $content = <<<'EOL'
default:
  suites:
    default:
      contexts:
        - FeatureContext
        - Drupal\DrupalExtension\Context\MinkContext
  extensions:
    Drupal\MinkExtension:
      browserkit_http: ~
      selenium2: ~
      base_url: http://nginx:8080      
      browser_name: chrome
      selenium2:
        wd_host: "http://chrome:4444/wd/hub"
        capabilities: { "browser": "chrome", "version": "*", "marionette": true, "extra_capabilities": { "chromeOptions": { "w3c": false } } }
      javascript_session: selenium2
      
    Drupal\DrupalExtension:
      api_driver: drupal
      drupal:
        drupal_root: /app/build/web
EOL;

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'behat.yml';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  /**
   * @Then it should fail with an error:
   */
  public function behatCliAssertFailWithError(PyStringNode $message) {
    $this->itShouldFail('fail');
    Assert::assertStringContainsString(trim((string) $message), $this->getOutput());
    // Enforce \Exception for all assertion exceptions. Non-assertion
    // exceptions should be thrown as \RuntimeException.
    Assert::assertStringContainsString(' (Exception)', $this->getOutput());
    Assert::assertStringNotContainsString(' (RuntimeException)', $this->getOutput());
  }

  /**
   * @Then it should fail with an exception:
   */
  public function behatCliAssertFailWithException(PyStringNode $message) {
    $this->itShouldFail('fail');
    Assert::assertStringContainsString(trim((string) $message), $this->getOutput());
    // Enforce \RuntimeException for all non-assertion exceptions. Assertion
    // exceptions should be thrown as \Exception.
    Assert::assertStringContainsString(' (RuntimeException)', $this->getOutput());
    Assert::assertStringNotContainsString(' (Exception)', $this->getOutput());
  }

  /**
   * Helper to print file comments.
   */
  protected static function behatCliPrintFileContents($filename, $title = '') {
    if (!is_readable($filename)) {
      throw new \RuntimeException(sprintf('Unable to access file "%s"', $filename));
    }

    $content = file_get_contents($filename);

    print "-------------------- $title START --------------------" . PHP_EOL;
    print $filename . PHP_EOL;
    print_r($content);
    print PHP_EOL;
    print "-------------------- $title FINISH --------------------" . PHP_EOL;
  }

  /**
   * Helper to check if debug mode is enabled.
   */
  protected static function behatCliIsDebug() {
    // Change to TRUE to see debug messages for this trait.
    return FALSE;
  }

}

<?php

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
      $tokens['{{USE_DECLARATION}}'] .= sprintf('use IntegratedExperts\\BehatSteps\\%s;' . PHP_EOL, $trait);
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
   * @Given set watchdog error level :level
   */
  public function setWatchdogError($level) {
    watchdog('php', 'test', [], $level);
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
    Behat\MinkExtension:
      goutte: ~
      selenium2: ~
      base_url: http://nginx:8080
    Drupal\DrupalExtension:
      api_driver: drupal
      drupal:
        drupal_root: /app/build/docroot
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
    Assert::assertContains(trim((string) $message), $this->getOutput());
    // Enforce \Exception for all assertion exceptions. Non-assertion
    // exceptions should be thrown as \RuntimeException.
    Assert::assertContains(' (Exception)', $this->getOutput());
    Assert::assertNotContains(' (RuntimeException)', $this->getOutput());
  }

  /**
   * @Then it should fail with an exception:
   */
  public function behatCliAssertFailWithException(PyStringNode $message) {
    $this->itShouldFail('fail');
    Assert::assertContains(trim((string) $message), $this->getOutput());
    // Enforce \RuntimeException for all non-assertion exceptions. Assertion
    // exceptions should be thrown as \Exception.
    Assert::assertContains(' (RuntimeException)', $this->getOutput());
    Assert::assertNotContains(' (Exception)', $this->getOutput());
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

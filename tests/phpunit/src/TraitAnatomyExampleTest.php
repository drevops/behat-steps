<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Extension\Config\ConfigSchemaReader;
use DrevOps\BehatSteps\Extension\Config\Option;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests the example trait the architecture docs walk through.
 *
 * The page shows the trait only as a code block, so the test loads it from
 * the page and composes it onto 'WebRawContext' as a project would.
 */
#[CoversNothing]
class TraitAnatomyExampleTest extends UnitTestCase {

  /**
   * The page holding the example, relative to the repository root.
   */
  protected const PAGE = 'docs/content/architecture/anatomy-of-a-trait.md';

  /**
   * The declaration that identifies the example among the page's code blocks.
   */
  protected const DECLARATION = 'trait ConsentBannerTrait {';

  /**
   * The context the test composes the example onto.
   */
  protected const CONTEXT = 'ConsentBannerExampleContext';

  public function testTheExampleDeclaresItsOptions(): void {
    $options = (new ConfigSchemaReader())->read($this->loadExample())['consent_banner'] ?? [];

    $this->assertSame(['enabled', 'selector', 'accept_text', 'auto_accept'], array_keys($options));
    $this->assertSame([TRUE, '.consent-banner', 'Accept', FALSE], array_values(array_map(static fn(Option $option): mixed => $option->default, $options)));
    $this->assertSame(['consent-accepted' => TRUE], $options['auto_accept']->tags);
  }

  public function testTheExampleReadsItsOptions(): void {
    $class = $this->loadExample();

    $this->assertSame('.consent-banner', $this->callExample(new $class(), 'consentBannerGetSelector'));
    $this->assertSame('Accept', $this->callExample(new $class(), 'consentBannerGetAcceptText'));
    $this->assertFalse($this->callExample(new $class(), 'consentBannerGetAutoAccept'));
    $this->assertSame('#cookie-notice', $this->callExample(new $class(['consent_banner' => ['selector' => '#cookie-notice']]), 'consentBannerGetSelector'));
  }

  public function testTheExampleRegistersItsStepsAndHooks(): void {
    $kinds = [Given::class, When::class, Then::class, BeforeScenario::class, AfterStep::class];
    $registered = [];

    foreach (static::reflect($this->loadExample())->getMethods() as $method) {
      foreach ($method->getAttributes() as $attribute) {
        if (in_array($attribute->getName(), $kinds, TRUE)) {
          $registered[$method->getName()] = [$attribute->getName(), $attribute->getArguments()[0] ?? NULL];
        }
      }
    }

    ksort($registered);

    $this->assertSame([
      'consentBannerAccept' => [When::class, 'I accept the consent banner'],
      'consentBannerAfterStep' => [AfterStep::class, NULL],
      'consentBannerAssertExists' => [Then::class, 'the consent banner should be on the page'],
      'consentBannerAssertNotExists' => [Then::class, 'the consent banner should not be on the page'],
      'consentBannerBeforeScenario' => [BeforeScenario::class, NULL],
      'consentBannerEnsureAccepted' => [Given::class, 'the consent banner has been accepted'],
    ], $registered);
  }

  /**
   * Load the example trait from the page, composed onto a context.
   *
   * @return class-string<\DrevOps\BehatSteps\WebRawContext>
   *   The context composing the example.
   */
  protected function loadExample(): string {
    if (!class_exists(static::CONTEXT, FALSE)) {
      preg_match_all('/^```php\n(.*?)^```$/ms', (string) file_get_contents(dirname(__DIR__, 3) . '/' . static::PAGE), $matches);
      $blocks = array_values(array_filter($matches[1], static fn(string $block): bool => str_contains($block, static::DECLARATION)));

      $this->assertCount(1, $blocks, sprintf('%s holds 1 code block declaring the example trait.', static::PAGE));

      require_once $this->writeFixture('ConsentBannerTrait.php', $blocks[0]);
      require_once $this->writeFixture(static::CONTEXT . '.php', sprintf("<?php\n\nclass %s extends \\DrevOps\\BehatSteps\\WebRawContext {\n\n  use ConsentBannerTrait;\n\n}\n", static::CONTEXT));
    }

    /** @var class-string<\DrevOps\BehatSteps\WebRawContext> $class */
    $class = static::CONTEXT;

    return $class;
  }

  /**
   * Call a public method of the example on a context.
   *
   * @param object $context
   *   The context composing the example.
   * @param string $method
   *   The method to call, which takes no arguments.
   *
   * @return mixed
   *   What the method returns.
   */
  protected function callExample(object $context, string $method): mixed {
    return static::reflect($context)->getMethod($method)->invoke($context);
  }

}

<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\RedirectTrait;
use Drupal\redirect\Entity\Redirect;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for creating and finding redirects through 'RedirectTrait'.
 */
#[CoversTrait(RedirectTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class RedirectTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'link', 'path_alias', 'redirect'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
  }

  /**
   * Tests that a redirect stores what 'Redirect::setSource()' would store.
   *
   * @param string $from
   *   The source path.
   * @param string $to
   *   The destination.
   * @param int $status_code
   *   The HTTP status code.
   * @param string $expected_uri
   *   The destination URI the redirect stores.
   */
  #[DataProvider('dataProviderCreateStoresTheRedirect')]
  public function testCreateStoresTheRedirect(string $from, string $to, int $status_code, string $expected_uri): void {
    $created = $this->context->redirectCreate($from, $to, $status_code);

    $this->assertTrue($created->isSaved());

    $redirect = Redirect::load($created->getValue('rid'));
    $this->assertInstanceOf(Redirect::class, $redirect);
    $this->assertSame(['path' => 'old/about', 'query' => []], $redirect->getSource());
    $this->assertSame($expected_uri, $redirect->getRedirect()['uri']);
    $this->assertSame($status_code, (int) $redirect->getStatusCode());
    $this->assertTrue($this->context->redirectExists($from, $to, $status_code), 'The lookup finds the redirect it created.');
  }

  public static function dataProviderCreateStoresTheRedirect(): array {
    return [
      'an internal destination' => ['/old/about', '/about', 302, 'internal:/about'],
      'an external destination' => ['old/about', 'https://example.com/promo', 301, 'https://example.com/promo'],
      'a destination already marked internal' => ['/old/about', 'internal:/contact', 307, 'internal:/contact'],
    ];
  }

}

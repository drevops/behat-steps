<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redirect\Entity\Redirect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the redirect capability methods on Core.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreRedirectMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'link', 'path_alias', 'redirect'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');

    $this->core = new Core($this->root);
  }

  /**
   * Tests that a redirect stores what the module's own setters would store.
   *
   * @param string $source
   *   The source path.
   * @param string $destination
   *   The destination.
   * @param string $expected_uri
   *   The destination URI the redirect stores.
   */
  #[DataProvider('dataProviderCreateRedirectStoresTheNormalizedValues')]
  public function testCreateRedirectStoresTheNormalizedValues(string $source, string $destination, string $expected_uri): void {
    $created = $this->core->createRedirect($source, $destination, 302);

    $this->assertSame('redirect', $created->getEntityType());
    $this->assertTrue($created->isSaved(), 'createRedirect marked the stub saved.');

    $redirect = Redirect::load($created->getValue('rid'));
    $this->assertInstanceOf(Redirect::class, $redirect);
    $this->assertSame(['path' => 'old/about', 'query' => []], $redirect->getSource());
    $this->assertSame($expected_uri, $redirect->getRedirect()['uri']);
    $this->assertSame(302, (int) $redirect->getStatusCode());
  }

  public static function dataProviderCreateRedirectStoresTheNormalizedValues(): \Iterator {
    yield 'an internal destination' => ['/old/about', '/about', 'internal:/about'];
    yield 'a source and a destination without a leading slash' => ['old/about', 'about', 'internal:/about'];
    yield 'a destination already under the internal scheme' => ['/old/about', 'internal:/contact', 'internal:/contact'];
    yield 'an external destination' => ['/old/about', 'https://example.com/promo', 'https://example.com/promo'];
    yield 'a protocol-relative destination' => ['/old/about', '//example.com/promo', '//example.com/promo'];
    yield 'a destination with another scheme' => ['/old/about', 'mailto:jane@example.com', 'mailto:jane@example.com'];
  }

  public function testCreateRedirectDefaultsToPermanentRedirect(): void {
    $created = $this->core->createRedirect('/old', '/new');

    $redirect = Redirect::load($created->getValue('rid'));
    $this->assertInstanceOf(Redirect::class, $redirect);
    $this->assertSame(301, (int) $redirect->getStatusCode());
  }

  public function testFindRedirectIdsMatchesTheNormalizedValues(): void {
    $first = (string) $this->core->createRedirect('/shared', '/about', 301)->getValue('rid');
    $second = (string) $this->core->createRedirect('other', 'https://example.com/promo', 302)->getValue('rid');

    $this->assertSame([$first], $this->core->findRedirectIds('shared'), 'The source matches without its leading slash.');
    $this->assertSame([$first], $this->core->findRedirectIds('/shared'), 'The source matches with its leading slash.');
    $this->assertSame([$first], $this->core->findRedirectIds('/shared', 'about'), 'The destination matches without its leading slash.');
    $this->assertSame([$first], $this->core->findRedirectIds('/shared', 'internal:/about'), 'The destination matches under its scheme.');
    $this->assertSame([$second], $this->core->findRedirectIds('/other', 'https://example.com/promo'), 'An external destination matches as written.');
    $this->assertSame([$second], $this->core->findRedirectIds('/other', NULL, 302));
    $this->assertSame([], $this->core->findRedirectIds('/shared', '/about', 302), 'The status code narrows the match.');
    $this->assertSame([], $this->core->findRedirectIds('/shared', '/elsewhere'), 'The destination narrows the match.');
    $this->assertSame([], $this->core->findRedirectIds('/missing'));
  }

  public function testDeleteRedirectRemovesTheRedirectAndToleratesMissingOne(): void {
    $rid = $this->core->createRedirect('/old', '/new')->getValue('rid');

    $this->core->deleteRedirect(new EntityStub('redirect', NULL, ['rid' => $rid]));
    $this->assertNull(Redirect::load($rid));

    $this->core->deleteRedirect(new EntityStub('redirect', NULL, ['rid' => $rid]));
    $this->core->deleteRedirect(new EntityStub('redirect', NULL, ['rid' => 999]));
    $this->assertSame([], $this->core->findRedirectIds('/old'));
  }

  public function testDeleteRedirectTakesSavedStub(): void {
    $created = $this->core->createRedirect('/old', '/new');

    $this->core->deleteRedirect($created);

    $this->assertNull(Redirect::load($created->getValue('rid')));
  }

  public function testDeleteRedirectRejectsStubWithoutId(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve an entity of type "redirect" from a stub without the id key "rid" set.');

    $this->core->deleteRedirect(new EntityStub('redirect'));
  }

}

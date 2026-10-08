<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Helper\Drupal;

use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\RegistryExposingContext;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding the newest of several config entities.
 */
#[CoversTrait(EntityLifecycleTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class EntityLifecycleTraitNewestKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user'];

  /**
   * The context under test.
   */
  protected RegistryExposingContext $context;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->context = new RegistryExposingContext();
  }

  /**
   * Tests which of 3 same-label menus is the newest.
   *
   * @param array<int, string> $registered
   *   The ids of the menus the scenario created, in creation order.
   * @param string $expected
   *   The id of the menu expected to be the newest.
   */
  #[DataProvider('dataProviderFindNewest')]
  public function testFindNewest(array $registered, string $expected): void {
    $menus = $this->createMenus(['shared', 'shared_2', 'shared_10']);

    foreach ($registered as $id) {
      $this->context->entityLifecycleRegister($menus[$id]);
    }

    $this->assertSame($expected, $this->context->entityLifecycleFindNewest($menus)?->id());
  }

  public static function dataProviderFindNewest(): array {
    return [
      'none created by the scenario' => [[], 'shared_10'],
      'one created by the scenario' => [['shared_2'], 'shared_2'],
      'several created by the scenario' => [['shared_10', 'shared'], 'shared'],
    ];
  }

  public function testFindNewestMatchesSavedStub(): void {
    $menus = $this->createMenus(['shared', 'shared_2']);

    $stub = new EntityStub('menu', 'menu', []);
    $stub->markSaved($menus['shared']);
    $this->context->testSetCreatedStubs([$stub]);

    $this->assertSame('shared', $this->context->entityLifecycleFindNewest($menus)?->id());
  }

  public function testFindNewestSkipsAnEntityOfAnotherTypeWithTheSameId(): void {
    $menus = $this->createMenus(['shared', 'shared_2']);

    $date_format = DateFormat::create(['id' => 'shared', 'label' => 'Shared', 'pattern' => 'Y']);
    $date_format->save();
    $this->context->entityLifecycleRegister($date_format);

    $this->assertSame('shared_2', $this->context->entityLifecycleFindNewest($menus)?->id());
  }

  public function testFindNewestReturnsNullForNoEntities(): void {
    $this->assertNull($this->context->entityLifecycleFindNewest([]));
  }

  /**
   * Creates menus that share 1 label.
   *
   * @param array<int, string> $ids
   *   The menu ids.
   *
   * @return array<string, \Drupal\system\MenuInterface>
   *   The menus, keyed by id.
   */
  protected function createMenus(array $ids): array {
    $menus = [];

    foreach ($ids as $id) {
      $menu = Menu::create(['id' => $id, 'label' => 'Shared']);
      $menu->save();

      $menus[$id] = $menu;
    }

    return $menus;
  }

}

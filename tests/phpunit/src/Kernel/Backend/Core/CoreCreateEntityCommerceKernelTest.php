<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_store\Entity\Store;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test exercising 'createEntity()' on a 'commerce_product' stub.
 *
 * A stub sets 'commerce_product.variations', a base entity_reference field
 * targeting 'commerce_product_variation'. The backend must resolve each
 * referenced variation and attach it to the product on save.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreCreateEntityCommerceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'views',
    'path',
    'path_alias',
    'address',
    'datetime',
    'entity',
    'inline_entity_form',
    'state_machine',
    'commerce',
    'commerce_price',
    'commerce_store',
  ];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // On Drupal 12 the 'text_with_summary' field type is provided by its own
    // module rather than by 'text'. The commerce_product configuration uses
    // that type, so the module is enabled before the configuration that reads
    // it.
    if (\Drupal::service('extension.list.module')->exists('text_with_summary')) {
      $this->enableModules(['text_with_summary']);
    }

    $this->enableModules(['commerce_product']);

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('commerce_currency');
    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    $this->installConfig(['system', 'user', 'filter', 'commerce_store', 'commerce_product']);

    // Import USD so the price-backed product variation type can resolve
    // its currency; required even when the stub does not set a price.
    $this->container->get('commerce_price.currency_importer')->import('USD');

    // Create a default store so products have a resolvable owner context.
    Store::create([
      'type' => 'online',
      'name' => 'Default',
      'mail' => 'admin@example.com',
      'default_currency' => 'USD',
      'address' => ['country_code' => 'US'],
    ])->save();

    $this->core = new Core($this->root);
  }

  /**
   * Tests 'createEntity()' resolves 'commerce_product.variations'.
   */
  public function testCreateEntityExpandsProductVariationsBaseField(): void {
    $variation_stub = new EntityStub('commerce_product_variation', 'default', [
      'sku' => 'SKU-001',
      'title' => 'Test variation',
    ]);
    $this->core->createEntity($variation_stub);

    $this->assertNotEmpty(
      $variation_stub->getValue('variation_id'),
      'createEntity populated commerce_product_variation.variation_id on the stub.',
    );

    $product_stub = new EntityStub('commerce_product', 'default', [
      'title' => 'Test product',
      'variations' => [$variation_stub->getValue('variation_id')],
    ]);
    $this->core->createEntity($product_stub);

    $this->assertNotEmpty(
      $product_stub->getValue('product_id'),
      'createEntity populated commerce_product.product_id on the stub.',
    );

    $product = Product::load((int) $product_stub->getValue('product_id'));
    $this->assertInstanceOf(Product::class, $product);

    $variation_ids = array_map(intval(...), $product->getVariationIds());
    $this->assertContains(
      (int) $variation_stub->getValue('variation_id'),
      $variation_ids,
      'product.variations base entity_reference resolved to the variation id.',
    );
  }

}

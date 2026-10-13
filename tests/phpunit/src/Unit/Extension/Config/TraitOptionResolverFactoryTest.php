<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Config;

use DrevOps\BehatSteps\Extension\Config\ConfigSchemaReader;
use DrevOps\BehatSteps\Extension\Config\TagOverrideResolver;
use DrevOps\BehatSteps\Extension\Config\TraitOptionResolverFactory;
use DrevOps\BehatSteps\Extension\Registry\ScenarioTagRegistry;
use DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures\ConfigurableContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the factory that wires a context's option resolver.
 */
#[CoversClass(TraitOptionResolverFactory::class)]
class TraitOptionResolverFactoryTest extends UnitTestCase {

  public function testItResolvesAgainstTheContextItIsGiven(): void {
    $resolver = (new TraitOptionResolverFactory())->create(ConfigurableContext::class, ['sample' => ['label' => 'from the argument']], ['sample' => ['limit' => 12]]);

    $this->assertSame('from the argument', $resolver->string('sample', 'label'));
    $this->assertSame(12, $resolver->int('sample', 'limit'));
  }

  public function testTheResolverReadsTheRegistryItWasBuiltWith(): void {
    $registry = new ScenarioTagRegistry();
    $factory = new TraitOptionResolverFactory(new ConfigSchemaReader(), $registry, new TagOverrideResolver());

    $resolver = $factory->create(ConfigurableContext::class, [], []);
    $registry->setTags(['sample-off']);

    $this->assertFalse($resolver->bool('sample', 'enabled'));
  }

  public function testItDefaultsToCollaboratorsOfItsOwn(): void {
    $resolver = (new TraitOptionResolverFactory())->create(ConfigurableContext::class, [], []);

    $this->assertTrue($resolver->bool('sample', 'enabled'));
  }

}

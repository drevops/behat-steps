<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Alias\AuthorAlias;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\FakeUser;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'AuthorAlias' creation alias.
 */
#[CoversClass(AuthorAlias::class)]
#[Group('aliases')]
class AuthorAliasTest extends UnitTestCase {

  public function testMetadataAccessors(): void {
    $alias = new AuthorAlias(static fn(): ?object => NULL);

    $this->assertInstanceOf(PreCreateAliasInterface::class, $alias);
    $this->assertSame('author', $alias->getName());
    $this->assertSame('node', $alias->getEntityType());
    $this->assertNotSame('', $alias->getDescription());
  }

  public function testApplyToStubResolvesKnownUser(): void {
    $alias = new AuthorAlias(static fn(string $name): object => new FakeUser(42));

    $stub = new EntityStub('node', 'article', ['title' => 'Hello', 'author' => 'alice']);

    $alias->applyToStub($stub);

    $this->assertSame(42, $stub->getValue('uid'));
    $this->assertFalse($stub->hasValue('author'));
    $this->assertSame('Hello', $stub->getValue('title'));
  }

  public function testApplyToStubThrowsOnUnknownUser(): void {
    $alias = new AuthorAlias(static fn(): ?object => NULL);

    $stub = new EntityStub('node', 'article', ['author' => 'auther']);

    try {
      $alias->applyToStub($stub);
      $this->fail('Expected CreationAliasResolutionException.');
    }
    catch (CreationAliasResolutionException $exception) {
      $this->assertStringContainsString('"auther"', $exception->getMessage());
      $this->assertTrue($stub->hasValue('author'), 'Stub must still carry the alias when resolution fails.');
      $this->assertFalse($stub->hasValue('uid'), 'No uid should be written when resolution fails.');
    }
  }

  /**
   * Tests that values are coerced to strings before lookup.
   *
   * @param mixed $author
   *   The raw 'author' value placed on the stub.
   * @param string $expected_lookup
   *   The string the closure is expected to receive.
   */
  #[DataProvider('dataProviderApplyToStubCoercesValueToString')]
  public function testApplyToStubCoercesValueToString(mixed $author, string $expected_lookup): void {
    $received = NULL;
    $alias = new AuthorAlias(static function (string $name) use (&$received): object {
      $received = $name;

      return new FakeUser(1);
    });

    $stub = new EntityStub('node', 'article', ['author' => $author]);

    $alias->applyToStub($stub);

    $this->assertSame($expected_lookup, $received);
  }

  /**
   * Data provider for 'testApplyToStubCoercesValueToString()'.
   *
   * @return \Iterator<string, array<int, mixed>>
   *   Cases of stub value, expected closure input.
   */
  public static function dataProviderApplyToStubCoercesValueToString(): \Iterator {
    yield 'plain string' => ['alice', 'alice'];
    yield 'integer-like string' => ['7', '7'];
    yield 'integer coerced' => [7, '7'];
  }

  /**
   * Tests that empty or null 'author' values throw a clear error.
   *
   * The resolver throws before the lookup, so the message reports the empty
   * alias rather than a missing user.
   *
   * @param mixed $author
   *   The empty-ish 'author' value placed on the stub.
   */
  #[DataProvider('dataProviderApplyToStubThrowsOnEmptyAuthor')]
  public function testApplyToStubThrowsOnEmptyAuthor(mixed $author): void {
    $alias = new AuthorAlias(static fn(): object => new FakeUser(1));

    $stub = new EntityStub('node', 'article', ['author' => $author]);

    $this->expectException(CreationAliasResolutionException::class);
    $this->expectExceptionMessageMatches("/'author' creation alias is set but empty/");

    $alias->applyToStub($stub);
  }

  /**
   * Data provider for 'testApplyToStubThrowsOnEmptyAuthor()'.
   *
   * @return \Iterator<string, array<int, mixed>>
   *   Cases of empty-ish 'author' value.
   */
  public static function dataProviderApplyToStubThrowsOnEmptyAuthor(): \Iterator {
    yield 'empty string' => [''];
    yield 'null' => [NULL];
  }

  /**
   * Tests that an 'author' that resolves to no valid user id is rejected.
   *
   * @param mixed $author
   *   The 'author' value placed on the stub.
   * @param object|null $user
   *   The object the lookup returns.
   * @param string $expected_message
   *   The message the alias is expected to throw with.
   */
  #[DataProvider('dataProviderApplyToStubRejectsUnresolvableAuthor')]
  public function testApplyToStubRejectsUnresolvableAuthor(mixed $author, ?object $user, string $expected_message): void {
    $alias = new AuthorAlias(static fn(): ?object => $user);

    $stub = new EntityStub('node', 'article', ['author' => $author]);

    try {
      $alias->applyToStub($stub);
      $this->fail('Expected CreationAliasResolutionException.');
    }
    catch (CreationAliasResolutionException $exception) {
      $this->assertSame($expected_message, $exception->getMessage());
      $this->assertTrue($stub->hasValue('author'), 'Stub must still carry the alias when resolution fails.');
      $this->assertFalse($stub->hasValue('uid'), 'No uid should be written when resolution fails.');
    }
  }

  public static function dataProviderApplyToStubRejectsUnresolvableAuthor(): \Iterator {
    yield 'value that cannot be read as a username' => [['alice'], NULL, "Cannot create node because the 'author' creation alias is array, which cannot be read as a username."];
    yield 'user without an id accessor' => ['alice', new \stdClass(), "Cannot create node because the 'author' lookup returned an object without an 'id()' method while resolving \"alice\"."];
    yield 'user with an id of 0' => ['alice', new FakeUser(0), "Cannot create node because the user resolved from 'author' = \"alice\" has an invalid id."];
    yield 'user with an id that is not a number' => ['alice', new FakeUser('abc'), "Cannot create node because the user resolved from 'author' = \"alice\" has an invalid id."];
  }

}

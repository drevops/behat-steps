<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use DrevOps\BehatSteps\Backend\Drupal\Drush14\Commands;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistry;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingDrushBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Drush 14 commands.
 */
#[CoversClass(Commands::class)]
#[Group('backends')]
#[Group('drush')]
class Drush14CommandsTest extends UnitTestCase {

  public function testDeleteUserCancelsTheAccountWhenUserCancelWorks(): void {
    $registry = new ExperimentalRegistry();
    $backend = $this->createBackend($registry);

    $backend->deleteUser(new EntityStub('user', NULL, ['name' => 'alice', 'uid' => 7]));

    $this->assertSame(['user:cancel'], array_column($backend->invocations, 'command'));
    $this->assertSame([], $registry->getNotices());
  }

  /**
   * Tests that a failed 'user:cancel' falls back to 'entity:delete'.
   *
   * @param array<string, mixed> $values
   *   The values of the user stub.
   * @param array<int, string> $expected_commands
   *   The commands the delete is expected to run, in order.
   */
  #[DataProvider('dataProviderDeleteUserFallsBackToEntityDelete')]
  public function testDeleteUserFallsBackToEntityDelete(array $values, array $expected_commands): void {
    $registry = new ExperimentalRegistry();
    $backend = $this->createBackend($registry);
    $backend->drushFailures = ['user:cancel' => 1];
    $backend->drushResponses = ['user:information' => '{"7":{"uid":"7","name":"alice"}}'];

    $backend->deleteUser(new EntityStub('user', NULL, $values));

    $this->assertSame($expected_commands, array_column($backend->invocations, 'command'));
    $entity_delete = end($backend->invocations);
    $this->assertIsArray($entity_delete);
    $this->assertSame(['user', '7'], $entity_delete['arguments']);
    $this->assertSame(['yes' => NULL], $entity_delete['options']);
    $this->assertSame([Commands::USER_DELETE_NOTICE], array_keys($registry->getNotices()));
    $this->assertSame(1, $registry->getNotices()[Commands::USER_DELETE_NOTICE]['count']);
  }

  public static function dataProviderDeleteUserFallsBackToEntityDelete(): \Iterator {
    yield 'a stub with a uid' => [['name' => 'alice', 'uid' => 7], ['user:cancel', 'user:information', 'entity:delete']];
    yield 'a stub with only a name' => [['name' => 'alice'], ['user:cancel', 'user:information', 'user:information', 'entity:delete']];
  }

  public function testDeleteUserRethrowsTheUserCancelFailureInStrictMode(): void {
    $registry = new ExperimentalRegistry(TRUE);
    $backend = $this->createBackend($registry);
    $backend->drushFailures = ['user:cancel' => 1];

    try {
      $backend->deleteUser(new EntityStub('user', NULL, ['name' => 'alice', 'uid' => 7]));
      $this->fail('The "user:cancel" failure was absorbed in strict mode.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Drush command "user:cancel" exited with code 1.', $exception->getMessage());
    }

    $this->assertNotContains('entity:delete', array_column($backend->invocations, 'command'));
    $this->assertSame([], $registry->getNotices());
  }

  public function testDeleteUserTakesNoFallbackForMissingUser(): void {
    $registry = new ExperimentalRegistry();
    $backend = $this->createBackend($registry);
    $backend->drushFailures = ['user:cancel' => 1];
    $backend->drushExitCode = 1;

    $backend->deleteUser(new EntityStub('user', NULL, ['name' => 'alice', 'uid' => 7]));

    $this->assertSame(['user:cancel', 'user:information'], array_column($backend->invocations, 'command'));
    $this->assertSame([], $registry->getNotices());
  }

  public function testDeleteUserRejectsAccountWithoutPrintedId(): void {
    $backend = $this->createBackend(new ExperimentalRegistry());
    $backend->drushFailures = ['user:cancel' => 1];
    $backend->drushResponses = ['user:information' => '{"7":{"name":"alice"}}'];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush did not print the id of the user "alice".');

    $backend->deleteUser(new EntityStub('user', NULL, ['name' => 'alice']));
  }

  /**
   * Creates a backend running the Drush 14 commands with the given registry.
   */
  protected function createBackend(ExperimentalRegistry $registry): RecordingDrushBackend {
    $backend = new RecordingDrushBackend('alias');
    $backend->fakeVersion = 14;
    $backend->setExperimentalRegistry($registry);

    return $backend;
  }

}

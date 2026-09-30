<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\UserTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for loading users through 'UserTrait'.
 */
#[CoversTrait(UserTrait::class)]
#[Group('behat')]
class UserTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
  }

  /**
   * Tests that the matching users are loaded, keyed by ID.
   */
  public function testLoadMultipleLoadsTheMatchingUsers(): void {
    $first = $this->createUser('first', 1);
    $second = $this->createUser('second', 1);
    $this->createUser('blocked', 0);

    $users = $this->context->userLoadMultiple(['status' => '1']);

    $this->assertLoadedSet([$first, $second], $users, UserInterface::class);
  }

  /**
   * Tests that an empty array is returned when no user matches.
   */
  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createUser('first', 1);

    $this->assertSame([], $this->context->userLoadMultiple(['name' => 'missing']));
  }

  /**
   * Creates and saves a user.
   */
  protected function createUser(string $name, int $status): UserInterface {
    $user = User::create(['name' => $name, 'status' => $status]);
    $user->save();

    return $user;
  }

}

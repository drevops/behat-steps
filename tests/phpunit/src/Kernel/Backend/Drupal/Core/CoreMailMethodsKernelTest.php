<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for mail-related methods on Core via the backend.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreMailMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // 'system.mail_original', the record of the handlers the collector
    // replaced, has no schema. Drupal 11 and 12 type the flag differently, so
    // an override of the property loads on only 1 of them.
    $this->strictConfigSchema = FALSE;

    parent::setUp();

    $this->installConfig(['system']);
    $this->core = new Core($this->root);
  }

  public function testMailStartCollectingSwapsInterface(): void {
    $this->core->mailStartCollecting();

    $interface = \Drupal::configFactory()->getEditable('system.mail')->get('interface');
    $this->assertSame('test_mail_collector', $interface['default'] ?? NULL);
  }

  public function testMailStartCollectingSwapsOnlyTheNamedTypes(): void {
    $this->config('system.mail')->set('interface', ['default' => 'php_mail', 'contact' => 'php_mail'])->save();

    $this->core->mailStartCollecting(['contact']);

    $this->assertSame(['default' => 'php_mail', 'contact' => 'test_mail_collector'], $this->config('system.mail')->get('interface'));
    $this->assertSame(['contact' => 'php_mail'], $this->config('system.mail_original')->get('interface'), 'Only the replaced handler is recorded.');
  }

  public function testMailStopCollectingRestoresTheRecordedHandlersAndDeletesTheRecord(): void {
    $this->config('system.mail')->set('interface', ['default' => 'php_mail', 'contact' => 'php_mail', 'other' => 'php_mail'])->save();

    $this->core->mailStartCollecting(['default', 'contact']);
    $this->core->mailStopCollecting();

    $this->assertSame(['default' => 'php_mail', 'contact' => 'php_mail', 'other' => 'php_mail'], $this->config('system.mail')->get('interface'));
    $this->assertTrue($this->config('system.mail_original')->isNew(), 'The record is deleted once the handlers are restored.');
  }

  public function testMailStartCollectingKeepsTheRecordOfAnEarlierRun(): void {
    // A run that stopped before restoring the handler leaves the collector in
    // place and the real handler in the record.
    $this->config('system.mail')->set('interface', ['default' => 'test_mail_collector'])->save();
    $this->config('system.mail_original')->set('interface', ['default' => 'php_mail'])->save();

    $this->core->mailStartCollecting();
    $this->core->mailStopCollecting();

    $this->assertSame(['default' => 'php_mail'], $this->config('system.mail')->get('interface'));
  }

  public function testMailStopCollectingRemovesTypeTheSiteNeverHad(): void {
    $this->config('system.mail')->set('interface', ['default' => 'php_mail'])->save();

    $this->core->mailStartCollecting(['contact']);
    $this->assertSame('test_mail_collector', $this->config('system.mail')->get('interface.contact'));

    $this->core->mailStopCollecting();

    $this->assertSame(['default' => 'php_mail'], $this->config('system.mail')->get('interface'));
  }

  public function testMailStopCollectingRestoresTheCollectorTheSiteAlreadyHeld(): void {
    $this->config('system.mail')->set('interface', ['default' => 'test_mail_collector'])->save();

    $this->core->mailStartCollecting();
    $this->assertSame(['default' => 'test_mail_collector'], $this->config('system.mail_original')->get('interface'), 'The handler the site held is recorded as it stands.');

    $this->core->mailStopCollecting();

    $this->assertSame(['default' => 'test_mail_collector'], $this->config('system.mail')->get('interface'));
    $this->assertTrue($this->config('system.mail_original')->isNew());
  }

  public function testMailStopCollectingDoesNothingWithoutRecord(): void {
    $this->config('system.mail')->set('interface', ['default' => 'php_mail'])->save();

    $this->core->mailStopCollecting();

    $this->assertSame(['default' => 'php_mail'], $this->config('system.mail')->get('interface'));
  }

  public function testMailStartCollectingRejectsNoHandlerTypes(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Collecting mail takes at least 1 handler type.');

    $this->core->mailStartCollecting([]);
  }

  public function testMailGetKeepsCancelledMail(): void {
    \Drupal::state()->set('system.test_mail_collector', [
      ['to' => 'sent@example.com', 'send' => TRUE],
      ['to' => 'cancelled@example.com', 'send' => FALSE],
    ]);

    $mail = $this->core->mailGet();

    $this->assertCount(2, $mail);
    $this->assertSame('cancelled@example.com', $mail[1]['to']);
  }

  public function testMailLifecycleRoundTrip(): void {
    $this->core->mailStartCollecting();

    $is_sent = $this->core->mailSend('Body text', 'Subject line', 'to@example.com', 'en');
    $this->assertTrue($is_sent);

    $mail = $this->core->mailGet();
    $this->assertCount(1, $mail);
    $this->assertSame('to@example.com', $mail[0]['to']);
    $this->assertSame('Subject line', $mail[0]['subject'] ?? $mail[0]['params']['context']['subject'] ?? NULL);

    $this->core->mailClear();
    $this->assertSame([], $this->core->mailGet());

    // 'mailStopCollecting()' must not throw and must leave the collector in a
    // reset state.
    $this->core->mailStopCollecting();
    $this->assertSame([], $this->core->mailGet());
  }

  public function testMailSendCarriesAttachments(): void {
    $this->core->mailStartCollecting();

    $attachments = [
      [
        'filecontent' => 'PDF bytes',
        'filename' => 'document.pdf',
        'filemime' => 'application/pdf',
      ],
    ];
    $is_sent = $this->core->mailSend('Body text', 'Subject line', 'to@example.com', 'en', $attachments);
    $this->assertTrue($is_sent);

    $mail = $this->core->mailGet();
    $this->assertCount(1, $mail);
    $this->assertSame($attachments, $mail[0]['params']['attachments']);
  }

  public function testMailSendWithEmptyAttachmentsOmitsKey(): void {
    $this->core->mailStartCollecting();

    $is_sent = $this->core->mailSend('Body text', 'Subject line', 'to@example.com', 'en', []);
    $this->assertTrue($is_sent);

    $mail = $this->core->mailGet();
    $this->assertCount(1, $mail);
    $this->assertArrayNotHasKey('attachments', $mail[0]['params']);
  }

  public function testMailCollectionRedirectsMailsystemSenders(): void {
    \Drupal::service('module_installer')->install(['mailsystem']);

    $config = \Drupal::configFactory()->getEditable('mailsystem.settings');
    $config->set('defaults.sender', 'php_mail')
      ->set('defaults.formatter', 'php_mail')
      ->save();
    $original = $config->get();

    $this->core->mailStartCollecting();

    $swapped = \Drupal::configFactory()->get('mailsystem.settings')->get();
    $this->assertSame('test_mail_collector', $swapped['defaults']['sender']);

    $this->core->mailStopCollecting();

    $restored = \Drupal::configFactory()->get('mailsystem.settings')->get();
    $this->assertSame($original['defaults']['sender'], $restored['defaults']['sender']);
  }

  public function testMailCollectionRestoresTheMailsystemSenderTheSiteAlreadyHeld(): void {
    \Drupal::service('module_installer')->install(['mailsystem']);
    $this->config('mailsystem.settings')->set('defaults.sender', 'test_mail_collector')->save();

    $this->core->mailStartCollecting();
    $this->assertSame(['sender' => 'test_mail_collector'], $this->config('system.mail_original')->get('mailsystem'), 'The sender the site held is recorded as it stands.');

    $this->core->mailStopCollecting();

    $this->assertSame('test_mail_collector', $this->config('mailsystem.settings')->get('defaults.sender'));
  }

}

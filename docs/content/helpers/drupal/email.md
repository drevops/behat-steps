---
title: "Drupal\\EmailTrait helpers"
sidebar_label: "EmailTrait"
sidebar_position: 10
description: "Test Drupal email functionality with content verification."
custom_edit_url: null
---

# Drupal\EmailTrait helpers

[Source](../../../../src/Steps/Drupal/EmailTrait.php), [Steps](../../steps/drupal/email.md)

> Test Drupal email functionality with content verification.

<details>
  <summary><code>public function emailCollectorIsEnabled(string $type = 'default'): bool</code></summary>

<br/>
Check whether this scenario's collector has replaced a handler type
<br/><br/>

</details>

<details>
  <summary><code>public function emailDisableCollector(): void</code></summary>

<br/>
Restore the mail system of every handler type, and clear the collector
<br/><br/>

</details>

<details>
  <summary><code>public function emailEnableCollector(): void</code></summary>

<br/>
Switch every handler type to the test mail collector, and clear it
<br/><br/>

</details>

<details>
  <summary><code>public static function emailExtractLinks(string $string): array</code></summary>

<br/>
Extract all links from provided string
<br/><br/>

</details>

<details>
  <summary><code>public function emailFindLinkContaining(string $partial_url): ?string</code></summary>

<br/>
Find the first link in a collected email whose URL contains a value
<br/><br/>

</details>

<details>
  <summary><code>public function emailFindMessage(string $field, PyStringNode $string, bool $is_exact = FALSE): ?array</code></summary>

<br/>
Find an email message whose field contains a value
<br/><br/>

</details>

<details>
  <summary><code>public function emailFindMessageBySubject(string $subject, bool $is_partial = FALSE): ?array</code></summary>

<br/>
Find the first collected email by exact or partial subject
<br/><br/>

</details>

<details>
  <summary><code>public function emailGetCollectedMessages(): array</code></summary>

<br/>
Get email messages collected during the test
<br/><br/>

</details>

<details>
  <summary><code>public function emailGetMessageBySubject(string $subject, bool $is_partial = FALSE): array</code></summary>

<br/>
Get the first collected email by exact or partial subject
<br/><br/>

</details>

<details>
  <summary><code>public function emailGetMessagesToAddress(string $address): array</code></summary>

<br/>
Get the collected emails sent to an address
<br/><br/>

</details>

<details>
  <summary><code>public function emailGetMessagesWithSubject(string $subject): array</code></summary>

<br/>
Get the collected emails with a subject
<br/><br/>

</details>

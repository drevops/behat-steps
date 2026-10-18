---
title: "Shell commands - Web helpers"
sidebar_label: "Shell commands"
sidebar_position: 26
description: "Run local shell commands and assert on their result."
custom_edit_url: null
---

# Shell commands

`DrevOps\BehatSteps\Steps\Web\CommandTrait` - [Source](../../../src/Steps/Web/CommandTrait.php), [Steps](../web-steps/command.md)

> Run local shell commands and assert on their result.

<details>
  <summary><code>public function commandExecute(string $command): array</code></summary>

<br/>
Run a shell command and capture its result
<br/><br/>

</details>

<details>
  <summary><code>public function commandGetTimeout(): int</code></summary>

<br/>
The maximum time, in seconds, a command may run before it is terminated
<br/><br/>

</details>

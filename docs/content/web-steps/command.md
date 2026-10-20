---
title: "Shell commands - Web steps"
sidebar_label: "Shell commands"
sidebar_position: 25
description: "Run local shell commands and assert on their result."
custom_edit_url: null
---

# Shell commands

`DrevOps\BehatSteps\Steps\Web\CommandTrait` - [Source](../../../src/Steps/Web/CommandTrait.php), [Example](../../../tests/behat/features/command.feature), [Helpers](../../../HELPERS.md#commandtrait)

>  Run local shell commands and assert on their result.
>  - Run a shell command and capture its output, error output, and exit code.
>  - Assert that the command succeeded or failed.
>  - Assert the exit code, standard output, and error output.
>  - Assert how long the command took to complete.
>  
>  Commands run through the system shell with the privileges of the process
>  that runs the tests. The command string is passed to the shell verbatim and
>  is subject to shell expansion, so untrusted input must never be
>  interpolated into it.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `command.timeout` | integer | `300` | - | The longest time, in seconds, a command can run before the step stops it and fails. A value of 0 or less stops every command at once. |

## Steps

<details>
  <summary><code>@When I run the command :command</code></summary>

<br/>
Run a shell command
<br/><br/>

```gherkin
When I run the command "php -v"
When I run the command "./vendor/bin/phpunit --version"
```

</details>

<details>
  <summary><code>@Then the command should succeed</code></summary>

<br/>
Assert that the last command succeeded
<br/><br/>

```gherkin
When I run the command "php -v"
Then the command should succeed
```

</details>

<details>
  <summary><code>@Then the command should fail</code></summary>

<br/>
Assert that the last command failed
<br/><br/>

```gherkin
When I run the command "phpcs --standard=NonExisting"
Then the command should fail
```

</details>

<details>
  <summary><code>@Then the command exit code should be :code</code></summary>

<br/>
Assert that the last command exited with a specific code
<br/><br/>

```gherkin
When I run the command "php -r 'exit(3);'"
Then the command exit code should be 3
```

</details>

<details>
  <summary><code>@Then the command output should contain :value</code></summary>

<br/>
Assert that the command output contains a string
<br/><br/>

```gherkin
When I run the command "echo hello"
Then the command output should contain "hello"
```

</details>

<details>
  <summary><code>@Then the command output should not contain :value</code></summary>

<br/>
Assert that the command output does not contain a string
<br/><br/>

```gherkin
When I run the command "echo hello"
Then the command output should not contain "goodbye"
```

</details>

<details>
  <summary><code>@Then the command output should be :value</code></summary>

<br/>
Assert that the command output equals a string
<br/><br/>

```gherkin
When I run the command "echo hello"
Then the command output should be "hello"
```

</details>

<details>
  <summary><code>@Then the command error output should contain :value</code></summary>

<br/>
Assert that the command error output contains a string
<br/><br/>

```gherkin
When I run the command "ls /nonexistent"
Then the command error output should contain "No such file"
```

</details>

<details>
  <summary><code>@Then the command should complete in less than :seconds second(s)</code></summary>

<br/>
Assert that the command completed in less than a number of seconds
<br/><br/>

```gherkin
When I run the command "echo hello"
Then the command should complete in less than 5 seconds
```

</details>

<details>
  <summary><code>@Then the command should complete in more than :seconds second(s)</code></summary>

<br/>
Assert that the command completed in more than a number of seconds
<br/><br/>

```gherkin
When I run the command "sleep 2"
Then the command should complete in more than 1 second
```

</details>

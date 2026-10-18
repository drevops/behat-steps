---
title: "RequestHeadersTrait helpers"
sidebar_label: "RequestHeadersTrait"
sidebar_position: 28
description: "Holds the request headers shared by the traits that issue HTTP requests."
custom_edit_url: null
---

# RequestHeadersTrait helpers

[Source](../../../../src/Steps/Helper/Web/RequestHeadersTrait.php)

> Holds the request headers shared by the traits that issue HTTP requests.

<details>
  <summary><code>public function requestHeadersSet(string $name, string $value): void</code></summary>

<br/>
Set a request header for subsequent requests
<br/><br/>

```
$this->requestHeadersSet('X-Acme-Token', 'secret');
```

</details>

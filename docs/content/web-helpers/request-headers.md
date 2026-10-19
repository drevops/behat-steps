---
title: "Request headers - Web helpers"
sidebar_label: "Request headers"
sidebar_position: 22
description: "Holds the request headers shared by the traits that issue HTTP requests."
custom_edit_url: null
---

# Request headers

`DrevOps\BehatSteps\Steps\Helper\Web\RequestHeadersTrait` - [Source](../../../src/Steps/Helper/Web/RequestHeadersTrait.php)

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

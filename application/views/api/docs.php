<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * صفحة الوثائق — تطبع من المواصفة، ولا يكتب فيها اسم نقطة بيد.
 *
 * ثلاثة أعمدة: تنقل، وشرح، وشيفرة على أرضية داكنة. وهو التخطيط الذي
 * ثبت في وثائق Stripe وScribe لأنه يجيب سؤالي القارئ معا: «ما هذا؟»
 * على اليسار و«كيف أناديه؟» على اليمين بلا تمرير بينهما.
 *
 * **والصفحة إنجليزية والوسم `ltr`** — بخلاف بقية المنصة. القارئ هنا مطور
 * يبني عميلا، ولغة وثائق الواجهات إنجليزية بالعرف. والقيم داخل الأمثلة
 * تبقى عربية لأنها ما يرده الخادم فعلا.
 *
 * والأصول محلية كلها: لا CDN ولا خط خارجي. وثيقة لا تفتح بلا إنترنت
 * خارجي هي وثيقة تسقط يوم يسقط ذلك النطاق.
 */

$base = rtrim(base_url(), '/');

/* ---------------------------------------------------------------------
 * مولدات الأمثلة — من المواصفة لا مكتوبة.
 * ------------------------------------------------------------------- */

/** مثال الجسم إن وجد. */
$body_of = function ($op) {
    if (empty($op['requestBody']['content']['application/json']['example'])) return null;
    return $op['requestBody']['content']['application/json']['example'];
};

$is_multipart = function ($op) {
    return isset($op['requestBody']['content']['multipart/form-data']);
};

$json_pretty = function ($v) {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
};

/** المسار مع وسائطه مستبدلة بقيمة نموذجية، فالمثال ينسخ ويعمل. */
$demo_path = function ($path, $op) {
    foreach ((isset($op['parameters']) ? $op['parameters'] : array()) as $p) {
        if ($p['in'] === 'path') $path = str_replace('{' . $p['name'] . '}', '58', $path);
    }
    return $path;
};

$needs_auth = function ($op) {
    return !(isset($op['security']) && $op['security'] === array());
};

/** cURL. */
$sample_curl = function ($path, $method, $op) use ($base, $body_of, $json_pretty, $demo_path, $needs_auth, $is_multipart) {
    $m   = strtoupper($method);
    $url = $base . $demo_path($path, $op);

    $lines = array('curl -X ' . $m . ' "' . $url . '" \\');
    $lines[] = '  -H "Accept: application/json" \\';
    if ($needs_auth($op)) $lines[] = '  -H "Authorization: Bearer $ACCESS_TOKEN" \\';

    if ($is_multipart($op)) {
        $lines[] = '  -F "user_image=@/path/to/photo.jpg"';
    } elseif (($b = $body_of($op)) !== null) {
        $lines[] = '  -H "Content-Type: application/json" \\';
        $lines[] = "  -d '" . $json_pretty($b) . "'";
    } else {
        $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1], " \\");
    }
    return implode("\n", $lines);
};

/** Dart — حزمة http. */
$sample_http = function ($path, $method, $op) use ($base, $body_of, $json_pretty, $demo_path, $needs_auth, $is_multipart) {
    $m    = strtolower($method);
    $url  = $base . $demo_path($path, $op);
    $out  = array();

    if ($is_multipart($op)) {
        $out[] = "final req = http.MultipartRequest(";
        $out[] = "  'POST', Uri.parse('" . $url . "'),";
        $out[] = ")";
        $out[] = "  ..headers['Authorization'] = 'Bearer \$accessToken'";
        $out[] = "  ..files.add(await http.MultipartFile.fromPath('user_image', filePath));";
        $out[] = "";
        $out[] = "final res  = await http.Response.fromStream(await req.send());";
        $out[] = "final body = jsonDecode(utf8.decode(res.bodyBytes));";
        return implode("\n", $out);
    }

    $out[] = "final res = await http." . $m . "(";
    $out[] = "  Uri.parse('" . $url . "'),";
    $out[] = "  headers: {";
    $out[] = "    'Accept': 'application/json',";
    if ($needs_auth($op))       $out[] = "    'Authorization': 'Bearer \$accessToken',";
    if ($body_of($op) !== null) $out[] = "    'Content-Type': 'application/json',";
    $out[] = "  },";
    if (($b = $body_of($op)) !== null) {
        $out[] = "  body: jsonEncode(" . str_replace("\n", "\n  ", $json_pretty($b)) . "),";
    }
    $out[] = ");";
    $out[] = "";
    $out[] = "// Always decode through utf8 — the payload carries Arabic.";
    $out[] = "final body = jsonDecode(utf8.decode(res.bodyBytes));";
    return implode("\n", $out);
};

/** Dart — Dio. */
$sample_dio = function ($path, $method, $op) use ($body_of, $json_pretty, $demo_path, $is_multipart) {
    $m    = ucfirst(strtolower($method));
    $path = $demo_path($path, $op);

    if ($is_multipart($op)) {
        return implode("\n", array(
            "final form = FormData.fromMap({",
            "  'user_image': await MultipartFile.fromFile(filePath),",
            "});",
            "",
            "final res = await dio.post('" . $path . "', data: form);",
        ));
    }

    $args = array("'" . $path . "'");
    if (($b = $body_of($op)) !== null) {
        $args[] = "data: " . str_replace("\n", "\n  ", $json_pretty($b));
    }

    return "final res = await dio.request" . $m . "(\n  " . implode(",\n  ", $args) . ",\n);";
};

/** JavaScript. */
$sample_js = function ($path, $method, $op) use ($base, $body_of, $json_pretty, $demo_path, $needs_auth, $is_multipart) {
    $url = $base . $demo_path($path, $op);
    $out = array();

    if ($is_multipart($op)) {
        $out[] = "const form = new FormData();";
        $out[] = "form.append('user_image', file);";
        $out[] = "";
        $out[] = "const res = await fetch('" . $url . "', {";
        $out[] = "  method: 'POST',";
        $out[] = "  headers: { Authorization: `Bearer \${accessToken}` },";
        $out[] = "  body: form,";
        $out[] = "});";
        return implode("\n", $out);
    }

    $out[] = "const res = await fetch('" . $url . "', {";
    $out[] = "  method: '" . strtoupper($method) . "',";
    $out[] = "  headers: {";
    $out[] = "    Accept: 'application/json',";
    if ($needs_auth($op))       $out[] = "    Authorization: `Bearer \${accessToken}`,";
    if ($body_of($op) !== null) $out[] = "    'Content-Type': 'application/json',";
    $out[] = "  },";
    if (($b = $body_of($op)) !== null) {
        $out[] = "  body: JSON.stringify(" . str_replace("\n", "\n  ", $json_pretty($b)) . "),";
    }
    $out[] = "});";
    $out[] = "";
    $out[] = "const body = await res.json();";
    return implode("\n", $out);
};

/**
 * Markdown مصغر — العناوين والجداول والقوائم والشيفرة والغامق والروابط.
 *
 * ولا تحمل مكتبة لأجل هذا: الوصف يكتب في المواصفة بيدنا، فالنحو المستعمل
 * فيه معروف محصور — ومحلل يفهم ما نكتبه فعلا أصغر وأأمن من محلل عام.
 * وكل نص يمر بـ`html_escape()` أولا، فلا وسم يعبر من المواصفة إلى الصفحة.
 */
$md = function ($text) {
    $inline = function ($s) {
        $s = html_escape($s);
        $s = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $s);
        $s = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '<a href="$2">$1</a>', $s);
        return $s;
    };

    $out   = array();
    $lines = explode("\n", (string) $text);
    $mode  = null;                      // null | p | ul | table | code
    $buf   = array();                   // أسطر الفقرة الجارية

    /**
     * الفقرة تجمع أسطرها ثم تنسق مرة واحدة.
     *
     * وهذا ليس ترتيبا: `**غامق**` يلتف على سطرين في هذه المواصفة كثيرا،
     * وتنسيق كل سطر وحده يترك النجمتين نصا ظاهرا على الصفحة. فالوصل قبل
     * التنسيق لا بعده.
     */
    $close = function () use (&$mode, &$out, &$buf, $inline) {
        if ($mode === 'p') {
            $out[] = '<p class="md-p">' . $inline(implode(' ', $buf)) . '</p>';
            $buf = array();
        }
        if ($mode === 'ul')    $out[] = '</ul>';
        if ($mode === 'table') $out[] = '</tbody></table></div>';
        if ($mode === 'code')  $out[] = '</code></pre>';
        $mode = null;
    };

    foreach ($lines as $line) {
        $t = rtrim($line);

        if ($mode === 'code') {
            if (strpos($t, '```') === 0) { $close(); continue; }
            $out[] = html_escape($line);
            continue;
        }

        if (strpos($t, '```') === 0) { $close(); $mode = 'code'; $out[] = '<pre class="md-pre"><code>'; continue; }

        if ($t === '') { $close(); continue; }

        if (preg_match('/^(#{2,4})\s+(.*)$/u', $t, $m)) {
            $close();
            $lvl = strlen($m[1]) + 1;
            $out[] = '<h' . $lvl . ' class="md-h">' . $inline($m[2]) . '</h' . $lvl . '>';
            continue;
        }

        /* الجدول: سطر الفاصل يهمل، والأول ترويسة. */
        if (strpos($t, '|') === 0) {
            $cells = array_map('trim', explode('|', trim($t, '|')));
            if (preg_match('/^[\s|:-]+$/', $t)) continue;

            if ($mode !== 'table') {
                $close();
                $mode  = 'table';
                $out[] = '<div class="md-tablewrap"><table class="md-table"><thead><tr>';
                foreach ($cells as $c) $out[] = '<th>' . $inline($c) . '</th>';
                $out[] = '</tr></thead><tbody>';
                continue;
            }
            $out[] = '<tr>';
            foreach ($cells as $c) $out[] = '<td>' . $inline($c) . '</td>';
            $out[] = '</tr>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.*)$/u', $t, $m)) {
            if ($mode !== 'ul') { $close(); $mode = 'ul'; $out[] = '<ul class="md-ul">'; }
            $out[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.*)$/u', $t, $m)) {
            if ($mode !== 'ul') { $close(); $mode = 'ul'; $out[] = '<ul class="md-ul md-ol">'; }
            $out[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }

        if ($mode !== 'p') { $close(); $mode = 'p'; }
        $buf[] = $t;
    }
    $close();

    return implode("\n", $out);
};

/** ترتيب النقاط حسب الوسوم المعلنة، فيقرأ القارئ ما رتب له. */
$grouped = array();
foreach ($spec['paths'] as $path => $ops) {
    foreach ($ops as $method => $op) {
        $tag = isset($op['tags'][0]) ? $op['tags'][0] : 'Other';
        $grouped[$tag][] = array('path' => $path, 'method' => $method, 'op' => $op);
    }
}
$ordered = array();
foreach ($spec['tags'] as $t) {
    if (!empty($grouped[$t['name']])) {
        $ordered[] = array('tag' => $t, 'items' => $grouped[$t['name']]);
        unset($grouped[$t['name']]);
    }
}
foreach ($grouped as $name => $items) {
    $ordered[] = array('tag' => array('name' => $name, 'description' => ''), 'items' => $items);
}

/** معرف مستقر لكل نقطة — يصلح مرساة ويصلح هدف بحث. */
$slug = function ($path, $method) {
    return strtolower($method) . '-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $path), '-');
};
?><!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo html_escape($spec['info']['title']); ?> — API Reference</title>
<meta name="description" content="<?php echo html_escape($spec['info']['summary']); ?>">
<meta name="robots" content="noindex, follow">
<link rel="icon" href="<?php echo base_url('assets/taqdar/brand/favicon.ico'); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/taqdar/css/apidocs.css'); ?>?v=1">
</head>
<body>

<a class="skip" href="#content">Skip to content</a>

<button class="navtoggle" id="navtoggle" aria-label="Toggle navigation" aria-expanded="false">
    <span></span><span></span><span></span>
</button>

<!-- ================= SIDEBAR ================= -->
<aside class="side" id="side">
    <div class="side__head">
        <a class="brand" href="<?php echo $base; ?>">
            <span class="brand__mark">تقدر</span>
            <span class="brand__text">
                <strong>API Reference</strong>
                <em>v<?php echo html_escape($spec['info']['version']); ?></em>
            </span>
        </a>
    </div>

    <div class="side__search">
        <input type="search" id="navsearch" placeholder="Search endpoints…" aria-label="Search endpoints"
               autocomplete="off" spellcheck="false">
    </div>

    <nav class="side__nav" aria-label="API sections">
        <a class="navlink navlink--top" href="#introduction">Introduction</a>
        <a class="navlink navlink--top" href="#authentication">Authenticating requests</a>
        <a class="navlink navlink--top" href="#errors">Errors &amp; status codes</a>
        <a class="navlink navlink--top" href="#ratelimits">Rate limits</a>
        <a class="navlink navlink--top" href="#flutter">Flutter integration</a>

        <?php foreach ($ordered as $group): ?>
            <p class="navgroup"><?php echo html_escape($group['tag']['name']); ?></p>
            <?php foreach ($group['items'] as $it): ?>
                <a class="navlink" href="#<?php echo $slug($it['path'], $it['method']); ?>"
                   data-search="<?php echo html_escape(strtolower($it['op']['summary'] . ' ' . $it['path'])); ?>">
                    <span class="verb verb--<?php echo strtolower($it['method']); ?>"><?php echo strtoupper($it['method']); ?></span>
                    <span class="navlink__t"><?php echo html_escape($it['op']['summary']); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="side__foot">
        <a href="<?php echo base_url('api/docs/collection.json?download=1'); ?>">Postman collection</a>
        <a href="<?php echo base_url('api/docs/openapi.json'); ?>">OpenAPI 3.1 spec</a>
    </div>
</aside>

<!-- ================= MAIN ================= -->
<main class="main" id="content">

<div class="shell">

    <!-- ---------- Introduction ---------- -->
    <section class="sec" id="introduction">
        <div class="prose">
            <h1>Taqdar API</h1>
            <?php echo $md($spec['info']['description']); ?>
        </div>
        <div class="code">
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Base URL</span></div>
                <pre class="panel__pre"><code class="lang-bash"><?php echo html_escape($base); ?></code></pre>
            </div>
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Health check</span></div>
                <pre class="panel__pre"><code class="lang-bash">curl <?php echo html_escape($base); ?>/api/v1</code></pre>
            </div>
        </div>
    </section>

    <!-- ---------- Authentication ---------- -->
    <section class="sec" id="authentication">
        <div class="prose">
            <h2>Authenticating requests</h2>
            <?php echo $md($spec['components']['securitySchemes']['bearerAuth']['description']); ?>

            <h3 class="md-h">The token lifecycle</h3>
            <ul class="md-ul">
                <li><strong>Log in</strong> once with e-mail and password. You get an access token and a refresh token.</li>
                <li><strong>Use</strong> the access token on every request for 15 minutes.</li>
                <li><strong>Refresh</strong> when a request fails with <code>401</code> and <code>code: "token_expired"</code>. Both tokens rotate; store the new pair.</li>
                <li><strong>Log out</strong> to revoke this device, or <code>logout-all</code> to revoke every device.</li>
            </ul>
            <p class="md-p">
                Store the refresh token in <code>flutter_secure_storage</code> — Keychain on iOS,
                EncryptedSharedPreferences on Android. <code>SharedPreferences</code> is plain text on
                a rooted device, and a refresh token is a 30-day key to the account.
            </p>

            <div class="note note--warn">
                <strong>Never refresh twice at once.</strong> A refresh token presented a second time
                is treated as a leak and the whole chain is revoked. If several requests fail with
                <code>401</code> together, funnel them through a single refresh — see the
                <a href="#flutter">Flutter integration</a> section for a working interceptor.
            </div>
        </div>
        <div class="code">
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Authorization header</span></div>
                <pre class="panel__pre"><code class="lang-bash">Authorization: Bearer tqa_9f3cAb7dQ1sK...</code></pre>
            </div>
        </div>
    </section>

    <!-- ---------- Errors ---------- -->
    <section class="sec" id="errors">
        <div class="prose">
            <h2>Errors &amp; status codes</h2>
            <p class="md-p">
                Every failure uses the same envelope. <code>message</code> is Arabic and safe to display
                verbatim; <code>code</code> is a stable ASCII key. <strong>Branch on <code>code</code></strong> —
                message wording changes, codes do not.
            </p>

            <div class="md-tablewrap"><table class="md-table">
                <thead><tr><th>Status</th><th>When</th><th>What the app should do</th></tr></thead>
                <tbody>
                <tr><td><code>200</code></td><td>Success</td><td>Read <code>data</code>.</td></tr>
                <tr><td><code>304</code></td><td><code>If-None-Match</code> matched</td><td>Keep your cached copy. Body is empty.</td></tr>
                <tr><td><code>401</code></td><td>Token missing, expired or revoked</td><td><code>token_expired</code> → refresh and retry. Anything else → sign out.</td></tr>
                <tr><td><code>403</code></td><td>Authenticated but not permitted</td><td>Show the message. Retrying will not help.</td></tr>
                <tr><td><code>404</code></td><td>Not found, or not yours</td><td>Treat as gone. The two cases are deliberately identical.</td></tr>
                <tr><td><code>405</code></td><td>Wrong HTTP verb</td><td>A bug in the client. Check <code>Allow</code>.</td></tr>
                <tr><td><code>409</code></td><td>State conflict</td><td>Refresh the screen; the resource moved on without you.</td></tr>
                <tr><td><code>422</code></td><td>Validation failed</td><td>Map <code>errors</code> onto your form fields.</td></tr>
                <tr><td><code>429</code></td><td>Rate limited</td><td>Wait <code>Retry-After</code> seconds. Do not loop.</td></tr>
                <tr><td><code>500</code></td><td>Server fault</td><td>Show a generic message and report <code>X-Request-Id</code>.</td></tr>
                </tbody>
            </table></div>

            <p class="md-p">
                Every response carries <code>X-Request-Id</code>. Log it. When something goes wrong in
                production it is the one value that lets support find the exact request in the server logs.
            </p>
        </div>
        <div class="code">
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Validation error (422)</span></div>
                <pre class="panel__pre"><code class="lang-json"><?php echo html_escape($json_pretty(array(
                    'message' => 'راجع البيانات المدخلة.',
                    'code'    => 'validation_failed',
                    'errors'  => array(
                        'email' => array('صيغة البريد غير صحيحة — مثال: name@example.com'),
                    ),
                ))); ?></code></pre>
            </div>
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Dart — one place to parse both shapes</span></div>
                <pre class="panel__pre"><code class="lang-dart">class ApiException implements Exception {
  final String message;   // Arabic, show as-is
  final String code;      // branch on this
  final Map&lt;String, List&lt;String&gt;&gt; errors;
  final int status;

  ApiException(this.message, this.code, this.errors, this.status);

  factory ApiException.from(int status, Map&lt;String, dynamic&gt; b) {
    final raw = (b['errors'] as Map?) ?? const {};
    return ApiException(
      b['message'] ?? 'حدث خطأ غير متوقع.',
      b['code'] ?? 'error',
      raw.map((k, v) =&gt; MapEntry(k as String, List&lt;String&gt;.from(v))),
      status,
    );
  }

  bool get isExpiredToken =&gt; code == 'token_expired';
}</code></pre>
            </div>
        </div>
    </section>

    <!-- ---------- Rate limits ---------- -->
    <section class="sec" id="ratelimits">
        <div class="prose">
            <h2>Rate limits</h2>
            <p class="md-p">
                Limits are enforced per account (or per IP before you have a token) and every response —
                not just a rejection — carries the current state, so a well-behaved client can slow itself
                down before it is told to.
            </p>

            <div class="md-tablewrap"><table class="md-table">
                <thead><tr><th>Bucket</th><th>Limit</th><th>Window</th></tr></thead>
                <tbody>
                <tr><td>Login attempts</td><td>10</td><td>15 min, per e-mail + IP</td></tr>
                <tr><td>Reads (<code>GET</code>)</td><td>120</td><td>1 min, per account</td></tr>
                <tr><td>Writes</td><td>30</td><td>1 min, per account</td></tr>
                <tr><td>Unauthenticated</td><td>60</td><td>1 min, per IP</td></tr>
                <tr><td>Data export</td><td>5</td><td>1 hour, per account</td></tr>
                </tbody>
            </table></div>

            <p class="md-p">
                Failed logins are additionally throttled at the platform level: five wrong passwords for
                one account, or twenty-five from one address, locks further attempts for fifteen minutes.
                This is independent of the request limit above.
            </p>

            <h3 class="md-h">Caching</h3>
            <p class="md-p">
                Every <code>GET</code> returns an <code>ETag</code>. Send it back as
                <code>If-None-Match</code> and an unchanged resource answers <code>304</code> with no body.
                On the profile screen — mastery map, certificates, ninety days of activity — that is the
                difference between a full payload and a few hundred bytes on every revisit.
            </p>
        </div>
        <div class="code">
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Response headers</span></div>
                <pre class="panel__pre"><code class="lang-bash">X-RateLimit-Limit: 120
X-RateLimit-Remaining: 117
X-RateLimit-Reset: 1787654400
X-Request-Id: 4f2a9c1b7e0d3a68
ETag: "9c1f8b2e4a7d..."</code></pre>
            </div>
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Conditional request</span></div>
                <pre class="panel__pre"><code class="lang-bash">curl <?php echo html_escape($base); ?>/api/v1/student/profile \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H 'If-None-Match: "9c1f8b2e4a7d..."'

# HTTP/1.1 304 Not Modified</code></pre>
            </div>
        </div>
    </section>

    <!-- ---------- Flutter ---------- -->
    <section class="sec" id="flutter">
        <div class="prose">
            <h2>Flutter integration</h2>
            <p class="md-p">
                A Dio interceptor that attaches the token, refreshes once on expiry, and replays the
                original request. The lock matters: without it, three screens loading at once produce
                three parallel refreshes, the second one trips reuse detection, and the user is signed
                out for no reason.
            </p>
            <h3 class="md-h">Recommended packages</h3>
            <ul class="md-ul">
                <li><code>dio</code> — interceptors, <code>FormData</code>, cancellation.</li>
                <li><code>flutter_secure_storage</code> — the refresh token belongs here, not in <code>SharedPreferences</code>.</li>
                <li><code>flutter_inappwebview</code> — opens the Tap payment page in-app.</li>
            </ul>
            <div class="note">
                <strong>Generate your models.</strong> Point <code>openapi-generator</code> at
                <a href="<?php echo base_url('api/docs/openapi.json'); ?>">the spec</a> with the
                <code>dart-dio</code> generator and you get typed models and a client for free —
                and they stay in step with the server instead of drifting by hand.
            </div>
        </div>
        <div class="code">
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">api_client.dart</span></div>
                <pre class="panel__pre"><code class="lang-dart">import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TaqdarApi {
  static const _base = '<?php echo html_escape($base); ?>';
  final _store = const FlutterSecureStorage();
  late final Dio dio;

  String? _access;
  Completer&lt;void&gt;? _refreshing;   // one refresh at a time — never two

  TaqdarApi() {
    dio = Dio(BaseOptions(
      baseUrl: _base,
      headers: {'Accept': 'application/json', 'Accept-Language': 'ar'},
      // Handle non-2xx ourselves so the envelope is parsed in one place.
      validateStatus: (s) =&gt; s != null &amp;&amp; s &lt; 500,
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (o, h) {
        if (_access != null) o.headers['Authorization'] = 'Bearer $_access';
        h.next(o);
      },
      onResponse: (r, h) async {
        final body = r.data;
        if (r.statusCode == 401 &amp;&amp; body?['code'] == 'token_expired') {
          if (await _refresh()) {
            // Replay the original request with the new token.
            r.requestOptions.headers['Authorization'] = 'Bearer $_access';
            return h.resolve(await dio.fetch(r.requestOptions));
          }
        }
        if (r.statusCode != null &amp;&amp; r.statusCode! &gt;= 400) {
          return h.reject(DioException(
            requestOptions: r.requestOptions,
            response: r,
            error: ApiException.from(r.statusCode!, body),
          ));
        }
        h.next(r);
      },
    ));
  }

  Future&lt;bool&gt; _refresh() async {
    // A refresh already in flight: wait for it instead of starting another.
    if (_refreshing != null) {
      await _refreshing!.future;
      return _access != null;
    }
    _refreshing = Completer&lt;void&gt;();
    try {
      final rt = await _store.read(key: 'refresh_token');
      if (rt == null) return false;

      final res = await Dio().post('$_base/api/v1/auth/refresh',
          data: {'refresh_token': rt});
      if (res.statusCode != 200) { await signOut(); return false; }

      final t = res.data['data']['token'];
      _access = t['access_token'];
      await _store.write(key: 'refresh_token', value: t['refresh_token']);
      return true;
    } catch (_) {
      await signOut();
      return false;
    } finally {
      _refreshing!.complete();
      _refreshing = null;
    }
  }

  Future&lt;void&gt; login(String email, String password) async {
    final res = await dio.post('/api/v1/auth/login', data: {
      'email': email,
      'password': password,
      'device_name': 'Flutter client',
      'platform': Platform.isIOS ? 'ios' : 'android',
    });
    final t = res.data['data']['token'];
    _access = t['access_token'];
    await _store.write(key: 'refresh_token', value: t['refresh_token']);
  }

  Future&lt;void&gt; signOut() async {
    _access = null;
    await _store.delete(key: 'refresh_token');
  }
}</code></pre>
            </div>
            <div class="panel">
                <div class="panel__bar"><span class="panel__title">Money — never use a double</span></div>
                <pre class="panel__pre"><code class="lang-dart">class Money {
  final int amount;        // halalas — the source of truth
  final String formatted;  // '399.00 ر.س' — ready to render

  const Money(this.amount, this.formatted);

  factory Money.fromJson(Map&lt;String, dynamic&gt; j) =&gt;
      Money(j['amount'] as int, j['formatted'] as String);

  // Sum in minor units. Adding 399.00 + 199.50 as doubles drifts;
  // adding 39900 + 19950 as ints does not.
  Money operator +(Money o) =&gt; Money(amount + o.amount, '');
}</code></pre>
            </div>
        </div>
    </section>

    <!-- ---------- Endpoints ---------- -->
    <?php foreach ($ordered as $group): ?>
        <section class="sec sec--tag">
            <div class="prose">
                <h2 id="tag-<?php echo html_escape(strtolower(str_replace(' ', '-', $group['tag']['name']))); ?>">
                    <?php echo html_escape($group['tag']['name']); ?>
                </h2>
                <?php if (!empty($group['tag']['description'])): ?>
                    <p class="md-p tag-desc"><?php echo html_escape($group['tag']['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="code"></div>
        </section>

        <?php foreach ($group['items'] as $it):
            $path = $it['path']; $method = $it['method']; $op = $it['op'];
            $id   = $slug($path, $method);
        ?>
        <section class="sec sec--ep" id="<?php echo $id; ?>">
            <div class="prose">
                <h3 class="ep__h">
                    <?php echo html_escape($op['summary']); ?>
                    <?php if (!$needs_auth($op)): ?>
                        <span class="chip chip--open">public</span>
                    <?php else: ?>
                        <span class="chip chip--auth">requires authentication</span>
                    <?php endif; ?>
                </h3>

                <div class="ep__route">
                    <span class="verb verb--<?php echo strtolower($method); ?>"><?php echo strtoupper($method); ?></span>
                    <code class="ep__path"><?php echo html_escape($path); ?></code>
                    <button class="copy" data-copy="<?php echo html_escape($base . $path); ?>" title="Copy full URL">copy</button>
                </div>

                <?php echo $md($op['description'] ?? ''); ?>

                <?php
                $path_params  = array();
                $query_params = array();
                foreach ((isset($op['parameters']) ? $op['parameters'] : array()) as $p) {
                    if ($p['in'] === 'path') $path_params[] = $p; else $query_params[] = $p;
                }
                ?>

                <?php if ($path_params): ?>
                    <h4 class="md-h">URL parameters</h4>
                    <div class="md-tablewrap"><table class="md-table params">
                        <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($path_params as $p): ?>
                            <tr>
                                <td><code><?php echo html_escape($p['name']); ?></code> <span class="req">required</span></td>
                                <td class="t"><?php echo html_escape($p['schema']['type']); ?></td>
                                <td><?php echo html_escape($p['description'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>

                <?php if ($query_params): ?>
                    <h4 class="md-h">Query parameters</h4>
                    <div class="md-tablewrap"><table class="md-table params">
                        <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($query_params as $p): ?>
                            <tr>
                                <td>
                                    <code><?php echo html_escape($p['name']); ?></code>
                                    <?php if (!empty($p['required'])): ?><span class="req">required</span><?php endif; ?>
                                </td>
                                <td class="t">
                                    <?php
                                    echo html_escape($p['schema']['type'] ?? 'string');
                                    if (isset($p['schema']['default'])) {
                                        echo '<br><span class="dim">default: ' . html_escape((string) $p['schema']['default']) . '</span>';
                                    }
                                    if (isset($p['schema']['enum'])) {
                                        echo '<br><span class="dim">' . html_escape(implode(' · ', $p['schema']['enum'])) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo html_escape($p['description'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>

                <?php
                $bschema = $op['requestBody']['content']['application/json']['schema']['properties'] ?? null;
                $breq    = $op['requestBody']['content']['application/json']['schema']['required'] ?? array();
                if ($bschema):
                ?>
                    <h4 class="md-h">Body parameters</h4>
                    <div class="md-tablewrap"><table class="md-table params">
                        <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($bschema as $name => $s): ?>
                            <tr>
                                <td>
                                    <code><?php echo html_escape($name); ?></code>
                                    <?php if (in_array($name, $breq, true)): ?><span class="req">required</span><?php endif; ?>
                                </td>
                                <td class="t">
                                    <?php
                                    $ty = $s['type'] ?? 'string';
                                    echo html_escape(is_array($ty) ? implode('|', $ty) : $ty);
                                    if (isset($s['maxLength'])) echo '<br><span class="dim">max ' . (int) $s['maxLength'] . '</span>';
                                    if (isset($s['minLength'])) echo '<br><span class="dim">min ' . (int) $s['minLength'] . '</span>';
                                    if (isset($s['enum']))      echo '<br><span class="dim">' . html_escape(implode(' · ', $s['enum'])) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php echo html_escape($s['description'] ?? ''); ?>
                                    <?php if (isset($s['example'])): ?>
                                        <span class="dim">e.g. <code><?php echo html_escape((string) $s['example']); ?></code></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php elseif ($is_multipart($op)): ?>
                    <h4 class="md-h">Body parameters</h4>
                    <div class="md-tablewrap"><table class="md-table params">
                        <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                        <tbody>
                        <tr>
                            <td><code>user_image</code> <span class="req">required</span></td>
                            <td class="t">file</td>
                            <td>JPG, PNG or WebP. Max 2 MB. Sent as <code>multipart/form-data</code>.</td>
                        </tr>
                        </tbody>
                    </table></div>
                <?php endif; ?>

                <h4 class="md-h">Responses</h4>
                <div class="md-tablewrap"><table class="md-table params">
                    <thead><tr><th>Code</th><th>Meaning</th></tr></thead>
                    <tbody>
                    <?php foreach ($op['responses'] as $code => $r): ?>
                        <tr>
                            <td><code class="st st--<?php echo ((int) $code < 300) ? 'ok' : (((int) $code < 500) ? 'warn' : 'bad'); ?>"><?php echo html_escape((string) $code); ?></code></td>
                            <td><?php echo html_escape($r['description'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>

            <div class="code">
                <div class="panel">
                    <div class="panel__bar">
                        <div class="tabs" role="tablist">
                            <button class="tab is-on" data-lang="bash" role="tab">cURL</button>
                            <button class="tab" data-lang="http" role="tab">Dart</button>
                            <button class="tab" data-lang="dio"  role="tab">Dio</button>
                            <button class="tab" data-lang="js"   role="tab">JS</button>
                        </div>
                        <button class="copy copy--panel" title="Copy">copy</button>
                    </div>
                    <pre class="panel__pre" data-pane="bash"><code class="lang-bash"><?php echo html_escape($sample_curl($path, $method, $op)); ?></code></pre>
                    <pre class="panel__pre" data-pane="http" hidden><code class="lang-dart"><?php echo html_escape($sample_http($path, $method, $op)); ?></code></pre>
                    <pre class="panel__pre" data-pane="dio"  hidden><code class="lang-dart"><?php echo html_escape($sample_dio($path, $method, $op)); ?></code></pre>
                    <pre class="panel__pre" data-pane="js"   hidden><code class="lang-js"><?php echo html_escape($sample_js($path, $method, $op)); ?></code></pre>
                </div>

                <?php
                /* أمثلة الردود: الناجح أولا ثم أول خطأ يستحق العرض. */
                $shown = 0;
                foreach ($op['responses'] as $code => $r):
                    $ex = $r['content']['application/json']['example'] ?? null;
                    if ($ex === null) continue;
                    if ((int) $code >= 300 && $shown > 1) continue;
                    $shown++;
                ?>
                    <div class="panel">
                        <div class="panel__bar">
                            <span class="panel__title">
                                <span class="st st--<?php echo ((int) $code < 300) ? 'ok' : (((int) $code < 500) ? 'warn' : 'bad'); ?>"><?php echo html_escape((string) $code); ?></span>
                                Example response
                            </span>
                            <button class="copy copy--panel" title="Copy">copy</button>
                        </div>
                        <pre class="panel__pre"><code class="lang-json"><?php echo html_escape($json_pretty($ex)); ?></code></pre>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <footer class="foot">
        <div class="prose">
            <p class="md-p dim">
                <?php echo html_escape($spec['info']['title']); ?> v<?php echo html_escape($spec['info']['version']); ?> ·
                Generated from the OpenAPI specification ·
                <a href="<?php echo base_url('api/docs/openapi.json'); ?>">openapi.json</a> ·
                <a href="<?php echo base_url('api/docs/collection.json?download=1'); ?>">Postman collection</a>
            </p>
        </div>
        <div class="code"></div>
    </footer>

</div>
</main>

<script src="<?php echo base_url('assets/taqdar/js/apidocs.js'); ?>?v=1"></script>
</body>
</html>

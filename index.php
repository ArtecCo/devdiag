<?php
declare(strict_types=1);

/**
 * Araha Device & Network Diagnostics
 * Single-file PHP application. Browser-only measurements are performed client-side.
 * No database, no visitor tracking, and no diagnostic result is stored.
 */

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(self), payment=(), usb=(), serial=(), bluetooth=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'upload-test') {
    // Measures upload throughput without writing the payload to disk.
    $max = 8 * 1024 * 1024;
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length < 1 || $length > $max) json_response(['ok' => false, 'error' => 'Upload test must be between 1 byte and 8 MB.'], 413);
    $body = file_get_contents('php://input', false, null, 0, $max + 1);
    if ($body === false || strlen($body) !== $length) json_response(['ok' => false, 'error' => 'Upload body could not be read completely.'], 400);
    json_response(['ok' => true, 'bytes' => strlen($body), 'received_at' => gmdate('c')]);
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ipValid = filter_var($ip, FILTER_VALIDATE_IP) !== false;
$ipInfo = null;
$ipError = null;
if ($ipValid && function_exists('curl_init')) {
    $ch = curl_init('https://ipwho.is/' . rawurlencode($ip));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'Araha-Diagnostics/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (is_string($raw) && $status === 200) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && ($decoded['success'] ?? false) === true) {
            $connection = $decoded['connection'] ?? [];
            $timezone = $decoded['timezone'] ?? [];
            $ipInfo = [
                'ip' => $decoded['ip'] ?? $ip,
                'type' => $decoded['type'] ?? null,
                'continent' => $decoded['continent'] ?? null,
                'continent_code' => $decoded['continent_code'] ?? null,
                'country' => $decoded['country'] ?? null,
                'country_code' => $decoded['country_code'] ?? null,
                'region' => $decoded['region'] ?? null,
                'region_code' => $decoded['region_code'] ?? null,
                'city' => $decoded['city'] ?? null,
                'latitude' => $decoded['latitude'] ?? null,
                'longitude' => $decoded['longitude'] ?? null,
                'postal' => $decoded['postal'] ?? null,
                'calling_code' => $decoded['calling_code'] ?? null,
                'capital' => $decoded['capital'] ?? null,
                'borders' => $decoded['borders'] ?? null,
                'flag' => $decoded['flag']['emoji'] ?? null,
                'isp' => $connection['isp'] ?? null,
                'org' => $connection['org'] ?? null,
                'asn' => $connection['asn'] ?? null,
                'domain' => $connection['domain'] ?? null,
                'timezone_id' => $timezone['id'] ?? null,
                'timezone_utc' => $timezone['utc'] ?? null,
                'timezone_current_time' => $timezone['current_time'] ?? null,
            ];
        } else $ipError = 'The IP intelligence provider did not return a successful result.';
    } else $ipError = 'IP intelligence lookup failed or timed out.';
} elseif (!$ipValid) $ipError = 'The visitor address could not be validated.';
else $ipError = 'PHP cURL is unavailable; IP intelligence lookup is disabled.';

$serverSnapshot = [
    'remote_address' => $ipValid ? $ip : null,
    'request_time_utc' => gmdate('c'),
    'https' => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    'http_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? null,
    'http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'remote_port' => isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null,
    'accept' => $_SERVER['HTTP_ACCEPT'] ?? null,
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
    'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'dnt_header' => $_SERVER['HTTP_DNT'] ?? null,
    'gpc_header' => $_SERVER['HTTP_SEC_GPC'] ?? null,
    'forwarding_headers_untrusted' => [
        'forwarded' => $_SERVER['HTTP_FORWARDED'] ?? null,
        'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        'x_real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
        'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
    ],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
<meta name="googlebot" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="theme-color" content="#080a0e">
<title>Araha | Device & Network Diagnostics</title>
<style>
:root{color-scheme:dark;--bg:#080a0e;--panel:#10141b;--panel2:#151b24;--line:#252e3a;--text:#e8edf4;--muted:#91a0b2;--accent:#69d3c1;--accent2:#8ab4ff;--good:#62d6a4;--warn:#f2c66d;--bad:#f28c8c;--radius:14px;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 15% -15%,#17302e 0,transparent 38%),var(--bg);color:var(--text);line-height:1.5}
button,select{font:inherit}button{cursor:pointer}.wrap{max-width:1500px;margin:auto;padding:26px 22px 60px}
header{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:24px}.brand{display:flex;align-items:center;gap:13px}.mark{width:42px;height:42px;border:1px solid #376b64;border-radius:12px;display:grid;place-items:center;color:var(--accent);font-weight:800;font-size:20px;background:#10201f}.eyebrow{color:var(--accent);font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.brand h1{font-size:21px;margin:2px 0 0;letter-spacing:-.03em}.header-actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:1px solid var(--line);background:#171e28;color:var(--text);padding:9px 13px;border-radius:9px;font-size:13px;font-weight:650}.btn:hover{border-color:#547c78;background:#1a2929}.btn.primary{background:#17433e;border-color:#28685f;color:#dffaf3}.btn:disabled{opacity:.45;cursor:not-allowed}
.hero{display:grid;grid-template-columns:1.5fr 1fr;gap:16px;margin-bottom:18px}.hero-card,.card{background:linear-gradient(145deg,rgba(21,27,36,.98),rgba(13,17,23,.98));border:1px solid var(--line);border-radius:var(--radius);padding:19px;min-width:0}.hero-card h2{font-size:16px;margin:0 0 8px}.hero-card p{color:var(--muted);font-size:13px;margin:0}.ip-big{font-size:clamp(25px,4vw,40px);font-weight:750;letter-spacing:-.04em;margin:8px 0 4px;overflow-wrap:anywhere}.subline{font-size:12px;color:var(--muted)}.status-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:13px}.pill{border:1px solid var(--line);border-radius:99px;padding:4px 9px;font-size:11px;color:var(--muted)}.pill.ok{color:var(--good);border-color:#285746}.pill.warn{color:var(--warn);border-color:#67532b}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{background:#0d1219;border:1px solid var(--line);padding:12px;border-radius:10px;min-width:0}.metric small{display:block;color:var(--muted);font-size:11px}.metric strong{display:block;font-size:15px;margin-top:4px;overflow-wrap:anywhere}
.tabs{display:flex;gap:7px;overflow:auto;padding:4px 0 14px;margin:0 0 4px;scrollbar-width:thin}.tab{white-space:nowrap;background:transparent;color:var(--muted);border:1px solid var(--line);padding:8px 12px;border-radius:9px;font-size:12px}.tab[aria-selected=true]{background:#18302e;color:var(--accent);border-color:#376b64}
.panel{display:none}.panel.active{display:block}.section-head{display:flex;justify-content:space-between;align-items:end;gap:12px;margin:8px 0 13px}.section-head h2{font-size:17px;margin:0}.section-head p{font-size:12px;color:var(--muted);margin:3px 0 0}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.card h3{font-size:13px;margin:0 0 12px;color:#dce7ef}.kv{display:grid;grid-template-columns:minmax(105px,.8fr) minmax(0,1.2fr);gap:7px 12px;font-size:12px}.kv .k{color:var(--muted)}.kv .v{overflow-wrap:anywhere;text-align:right;color:#e2e9f1}.empty{color:var(--muted);font-size:12px}.value-list{display:flex;flex-wrap:wrap;gap:6px}.tag{font-size:11px;padding:4px 7px;border:1px solid var(--line);border-radius:6px;color:#bdcbd8}.actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.result{font-size:12px;color:var(--muted);margin-top:9px;white-space:pre-wrap;overflow-wrap:anywhere}.notice{border:1px solid #65532e;background:#211d13;color:#e8d4a5;border-radius:10px;padding:11px 13px;font-size:12px;margin:0 0 15px}.fine{font-size:11px;color:var(--muted)}.progress{height:5px;background:#252d37;border-radius:9px;overflow:hidden;margin-top:9px}.progress span{display:block;height:100%;width:0;background:var(--accent);transition:width .2s}
footer{border-top:1px solid var(--line);margin-top:26px;padding-top:15px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;color:var(--muted);font-size:11px}
@media(max-width:1000px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.summary-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:680px){.wrap{padding:17px 13px 40px}header{align-items:flex-start;flex-direction:column}.hero{grid-template-columns:1fr}.grid{grid-template-columns:1fr}.summary-grid{grid-template-columns:repeat(2,1fr)}.section-head{align-items:flex-start;flex-direction:column}.kv{grid-template-columns:1fr 1.2fr}}
.display-overlay{position:fixed;inset:0;z-index:99999;background:#000;display:flex;align-items:center;justify-content:center;color:#fff}.display-overlay .controls{position:fixed;bottom:18px;display:flex;gap:8px;align-items:center}.display-overlay button{background:#111;color:#fff;border:1px solid #888;padding:10px 14px;border-radius:8px}.display-overlay .pattern-label{position:fixed;top:18px;left:18px;background:#111b;padding:7px 10px;border-radius:6px;font-size:12px}.display-overlay.gradient{background:linear-gradient(90deg,#000,#fff)}.display-overlay.checker{background:conic-gradient(#fff 25%,#000 0 50%,#fff 0 75%,#000 0);background-size:80px 80px}\n@media print{body{background:#fff;color:#111}.wrap{max-width:none;padding:0}.hero-card,.card,.metric{background:#fff;border:1px solid #bbb;color:#111;break-inside:avoid}.hero-card p,.subline,.kv .k,.fine,footer{color:#444}.tabs,.header-actions,.actions,.notice{display:none!important}.panel{display:block!important;break-before:page}.panel:first-of-type{break-before:auto}.ip-big{color:#111}.pill{color:#333}}
</style>
</head>
<body>
<div class="wrap">
<header>
 <div class="brand"><div class="mark">A</div><div><div class="eyebrow">Araha diagnostics</div><h1>Device & Network Intelligence</h1></div></div>
 <div class="header-actions"><button class="btn" id="refreshBtn">↻ Refresh data</button><button class="btn" id="copyBtn">Copy report</button><button class="btn" id="jsonBtn">Export JSON</button><button class="btn primary" onclick="window.print()">Print report</button></div>
</header>
<div class="hero">
 <div class="hero-card"><div class="eyebrow">Public address observed by this site</div><div class="ip-big" id="publicIp"><?= htmlspecialchars($ipValid ? $ip : 'Unavailable', ENT_QUOTES, 'UTF-8') ?></div><div class="subline" id="ipLocation"><?= $ipInfo ? htmlspecialchars(trim(($ipInfo['city'] ?? '') . ', ' . ($ipInfo['region'] ?? '') . ', ' . ($ipInfo['country'] ?? ''), ', '), ENT_QUOTES, 'UTF-8') : 'Location lookup unavailable' ?></div><div class="status-row"><span class="pill <?= $serverSnapshot['https'] ? 'ok' : 'warn' ?>"><?= $serverSnapshot['https'] ? 'HTTPS connection' : 'Insecure HTTP context' ?></span><span class="pill" id="onlinePill">Checking connectivity…</span><span class="pill" id="privacyPill">Privacy signals pending</span></div></div>
 <div class="hero-card"><h2>Quick device snapshot</h2><p>Values are read from this browser and may be reduced, rounded, spoofed or withheld by browser privacy controls.</p><div class="summary-grid" style="margin-top:13px"><div class="metric"><small>Browser</small><strong id="sumBrowser">Detecting…</strong></div><div class="metric"><small>Platform</small><strong id="sumPlatform">Detecting…</strong></div><div class="metric"><small>Display</small><strong id="sumDisplay">Detecting…</strong></div><div class="metric"><small>Logical CPU</small><strong id="sumCpu">Detecting…</strong></div></div></div>
</div>
<div class="notice"><strong>Privacy & accuracy:</strong> This page does not request location, camera, microphone or motion access automatically. Those tests run only when you press their buttons. IP geolocation is approximate. Some browser APIs are unavailable by design. Forwarded-IP headers are not trusted as proof of your address.</div>
<nav class="tabs" role="tablist" aria-label="Diagnostic sections">
 <button class="tab" role="tab" aria-selected="true" data-tab="overview">Overview</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="network">Network lab</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="device">Device lab</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="browser">Browser lab</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="privacy">Privacy & security</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="performance">Performance lab</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="location">Location & sensors</button>
 <button class="tab" role="tab" aria-selected="false" data-tab="advanced">Extended tests</button>
</nav>
<section class="panel active" id="overview">
 <div class="section-head"><div><h2>Diagnostic overview</h2><p>Collected automatically where browser permissions and platform support allow.</p></div></div>
 <div class="grid">
  <article class="card"><h3>IP intelligence</h3><div id="ipDetails" class="kv"></div></article>
  <article class="card"><h3>Browser & operating system</h3><div id="browserDetails" class="kv"></div></article>
  <article class="card"><h3>Display & locale</h3><div id="displayDetails" class="kv"></div></article>
  <article class="card"><h3>Network connection hints</h3><div id="networkDetails" class="kv"></div></article>
  <article class="card"><h3>Hardware hints</h3><div id="hardwareDetails" class="kv"></div></article>
  <article class="card"><h3>Runtime capabilities</h3><div id="capabilityDetails" class="kv"></div></article>
 </div>
</section>
<section class="panel" id="network">
 <div class="section-head"><div><h2>Network laboratory</h2><p>Connectivity, public address families, latency, DNS-over-HTTPS reachability and throughput.</p></div></div>
 <div class="grid">
  <article class="card"><h3>Address family checks</h3><div class="kv" id="addressChecks"></div><div class="actions"><button class="btn" data-run="addresses">Test IPv4 / IPv6</button><button class="btn" data-run="webrtc">Inspect WebRTC candidates</button></div><div class="result" id="addressResult"></div></article>
  <article class="card"><h3>Latency & endpoint reachability</h3><div class="kv" id="latencyChecks"></div><div class="actions"><button class="btn" data-run="latency">Run latency test</button></div><div class="result" id="latencyResult"></div></article>
  <article class="card"><h3>DNS resolver checks</h3><p class="fine">Tests public DNS-over-HTTPS endpoints from the browser. This does not reveal every resolver used by the operating system and is not a full DNS leak test.</p><div class="kv" id="dnsChecks"></div><div class="actions"><button class="btn" data-run="dns">Test DoH endpoints</button></div><div class="result" id="dnsResult"></div></article>
  <article class="card"><h3>Download throughput</h3><p class="fine">Downloads a temporary test payload from Cloudflare. Data usage applies.</p><div class="kv"><div class="k">Test size</div><div class="v">5 MB</div><div class="k">Result</div><div class="v" id="downloadValue">Not tested</div></div><div class="progress"><span id="downloadProgress"></span></div><div class="actions"><button class="btn" data-run="download">Run download test</button></div><div class="result" id="downloadResult"></div></article>
  <article class="card"><h3>Upload throughput</h3><p class="fine">Sends up to 4 MB to this PHP endpoint. The payload is measured in memory and immediately discarded.</p><div class="kv"><div class="k">Test size</div><div class="v">4 MB</div><div class="k">Result</div><div class="v" id="uploadValue">Not tested</div></div><div class="actions"><button class="btn" data-run="upload">Run upload test</button></div><div class="result" id="uploadResult"></div></article>
  <article class="card"><h3>WebRTC / NAT hints</h3><p class="fine">May contact a public STUN server and reveal network candidate addresses to this page. Run only if you consent.</p><div id="webrtcResult" class="result">Not tested.</div><div class="actions"><button class="btn" data-run="webrtc">Run WebRTC inspection</button></div></article>
 </div>
</section>
<section class="panel" id="device">
 <div class="section-head"><div><h2>Device laboratory</h2><p>Hardware, display, input, battery, storage and media-device capabilities.</p></div></div>
 <div class="grid">
  <article class="card"><h3>Screen & rendering</h3><div class="kv" id="screenDetails"></div></article>
  <article class="card"><h3>Hardware & input</h3><div class="kv" id="deviceDetails"></div></article>
  <article class="card"><h3>Battery</h3><div class="kv" id="batteryDetails"></div><div class="actions"><button class="btn" data-run="battery">Refresh battery</button></div></article>
  <article class="card"><h3>Storage estimate</h3><div class="kv" id="storageDetails"></div><div class="actions"><button class="btn" data-run="storage">Refresh storage estimate</button></div></article>
  <article class="card"><h3>Media devices</h3><p class="fine">Enumerates device labels/counts where the browser allows. Does not start camera or microphone streams.</p><div class="kv" id="mediaDetails"></div><div class="actions"><button class="btn" data-run="media">Inspect media devices</button></div></article>
  <article class="card"><h3>Motion & orientation</h3><div class="kv" id="sensorDetails"></div><div class="actions"><button class="btn" data-run="sensors">Request sensor permission / sample</button></div><div class="result" id="sensorResult"></div></article>
 </div>
</section>
<section class="panel" id="browser">
 <div class="section-head"><div><h2>Browser laboratory</h2><p>Browser engine clues, APIs, media formats, graphics, fonts and feature detection.</p></div></div>
 <div class="grid">
  <article class="card"><h3>Browser identity</h3><div class="kv" id="browserLabDetails"></div></article>
  <article class="card"><h3>API support</h3><div class="value-list" id="apiTags"></div></article>
  <article class="card"><h3>Image format support</h3><div class="kv" id="imageFormats"></div></article>
  <article class="card"><h3>Audio & video codecs</h3><div class="kv" id="codecDetails"></div></article>
  <article class="card"><h3>WebGL information</h3><div class="kv" id="webglDetails"></div></article>
  <article class="card"><h3>Font availability</h3><div class="value-list" id="fontDetails"></div></article>
  <article class="card"><h3>Canvas fingerprint sample</h3><p class="fine">Creates a local rendering sample and hash. This is not uploaded or used to identify you.</p><div class="kv" id="canvasDetails"></div><div class="actions"><button class="btn" data-run="canvas">Generate local canvas hash</button></div></article>
  <article class="card"><h3>Audio rendering sample</h3><p class="fine">Offline audio rendering only. No microphone access and no audio is uploaded.</p><div class="kv" id="audioDetails"></div><div class="actions"><button class="btn" data-run="audio">Generate local audio sample</button></div></article>
 </div>
</section>
<section class="panel" id="privacy">
 <div class="section-head"><div><h2>Privacy & security</h2><p>Signals exposed by the browser. These are indicators, not a security certification.</p></div></div>
 <div class="grid">
  <article class="card"><h3>Privacy signals</h3><div class="kv" id="privacyDetails"></div></article>
  <article class="card"><h3>Permission states</h3><div class="kv" id="permissionDetails"></div><div class="actions"><button class="btn" data-run="permissions">Refresh permission states</button></div></article>
  <article class="card"><h3>Secure context & isolation</h3><div class="kv" id="securityDetails"></div></article>
  <article class="card"><h3>Storage & tracking controls</h3><div class="kv" id="trackingDetails"></div></article>
  <article class="card"><h3>Automation / privacy indicators</h3><div class="kv" id="automationDetails"></div></article>
  <article class="card"><h3>Request header snapshot</h3><p class="fine">Selected request headers seen by PHP. Proxy-provided forwarding values are explicitly marked untrusted.</p><div class="kv" id="headerDetails"></div></article>
 </div>
</section>
<section class="panel" id="performance">
 <div class="section-head"><div><h2>Performance laboratory</h2><p>Lightweight local benchmarks. Results vary with device load, thermal state and browser throttling.</p></div></div>
 <div class="grid">
  <article class="card"><h3>JavaScript execution</h3><div class="kv"><div class="k">Result</div><div class="v" id="jsBenchValue">Not run</div></div><div class="actions"><button class="btn" data-run="jsbench">Run JS benchmark</button></div><div class="result" id="jsBenchResult"></div></article>
  <article class="card"><h3>Animation frame cadence</h3><div class="kv"><div class="k">Measured refresh hint</div><div class="v" id="fpsValue">Not measured</div></div><div class="actions"><button class="btn" data-run="fps">Measure frame cadence</button></div><div class="result" id="fpsResult"></div></article>
  <article class="card"><h3>Memory hints</h3><div class="kv" id="memoryDetails"></div></article>
  <article class="card"><h3>Performance timing</h3><div class="kv" id="timingDetails"></div></article>
 </div>
</section>
<section class="panel" id="location">
 <div class="section-head"><div><h2>Location & sensors</h2><p>IP-based location is approximate. GPS and motion sampling require explicit action and browser permission.</p></div></div>
 <div class="grid">
  <article class="card"><h3>IP-based location</h3><div class="kv" id="ipGeoDetails"></div></article>
  <article class="card"><h3>Precise geolocation</h3><p class="fine">Requests your device location only after you click. Coordinates are displayed locally and included only if you export the report.</p><div class="kv" id="gpsDetails"></div><div class="actions"><button class="btn" data-run="gps">Request GPS location</button></div><div class="result" id="gpsResult"></div></article>
  <article class="card"><h3>Clock & timezone consistency</h3><div class="kv" id="clockDetails"></div></article>
 </div>
</section>

<section class="panel" id="advanced">
 <div class="section-head"><div><h2>Extended diagnostics</h2><p>Interactive input, display, audio/video, security-response, stability and benchmark tests. Run only the tests you need.</p></div></div>
 <div class="grid">
  <article class="card"><h3>Independent public IP comparison</h3><p class="fine">Queries independent providers. Differences can indicate proxying, VPN routing, split tunnels or provider limitations.</p><div class="kv" id="multiIpDetails"></div><div class="actions"><button class="btn" data-extra="multiip">Compare public IP providers</button></div><div class="result" id="multiIpResult"></div></article>
  <article class="card"><h3>Connection stability, jitter & loss</h3><p class="fine">Repeated HTTP requests; not ICMP ping. Background throttling and endpoint policies affect results.</p><div class="kv" id="stabilityDetails"></div><div class="actions"><button class="btn" data-extra="stability">Run 20-sample stability test</button></div><div class="result" id="stabilityResult"></div></article>
  <article class="card"><h3>Response security headers & protocol</h3><p class="fine">Reads headers returned for this page and the browser's navigation protocol hint. It cannot inspect the complete TLS handshake.</p><div class="kv" id="headersDetails"></div><div class="actions"><button class="btn" data-extra="headers">Inspect response headers</button></div></article>
  <article class="card"><h3>Keyboard & pointer tester</h3><p class="fine">Focus this page and press keys, click, move, scroll or touch. Keystrokes are not recorded as text.</p><div class="kv" id="inputDetails"></div><div class="actions"><button class="btn" data-extra="inputreset">Reset input counters</button></div><div class="result" id="inputResult">Waiting for input…</div></article>
  <article class="card"><h3>Gamepad tester</h3><p class="fine">Connect a controller and press a button. Browser support and user activation vary.</p><div class="kv" id="gamepadDetails"></div><div class="actions"><button class="btn" data-extra="gamepad">Sample connected gamepads</button></div></article>
  <article class="card"><h3>Display visual test</h3><p class="fine">Full-screen solid colors and gradients help visually inspect dead pixels, uniformity and banding. This is a manual visual check, not automated pixel detection.</p><div class="actions"><button class="btn" data-extra="displaytest">Start display test</button></div><div id="displayTest" class="result">Use the next/previous controls and press Escape to exit.</div></article>
  <article class="card"><h3>Audio output test</h3><p class="fine">Plays a short tone only after you press the button. Keep volume low before starting.</p><div class="kv" id="toneDetails"></div><div class="actions"><button class="btn" data-extra="tone">Play 440 Hz tone</button><button class="btn" data-extra="toneSweep">Play short frequency sweep</button></div></article>
  <article class="card"><h3>Camera & microphone test</h3><p class="fine">Requests access only when started. A live preview is shown locally; streams are stopped when you press Stop or leave this section.</p><div class="actions"><button class="btn" data-extra="avstart">Start camera / microphone</button><button class="btn" data-extra="avstop">Stop devices</button></div><video id="avPreview" playsinline autoplay muted style="display:none;width:100%;max-height:220px;background:#000;border-radius:9px;margin-top:10px"></video><div class="result" id="avResult">Not started.</div></article>
  <article class="card"><h3>WebGPU & graphics capabilities</h3><p class="fine">Queries adapter availability and exposed adapter information. Browsers may intentionally redact details.</p><div class="kv" id="gpuDetails"></div><div class="actions"><button class="btn" data-extra="gpu">Inspect WebGPU adapter</button></div></article>
  <article class="card"><h3>CPU, DOM & storage benchmarks</h3><p class="fine">Short local benchmarks; they are affected by background load, power mode, thermal throttling and browser scheduling.</p><div class="kv" id="benchDetails"></div><div class="actions"><button class="btn" data-extra="bench">Run extended benchmarks</button></div></article>
  <article class="card"><h3>Accessibility & environment</h3><div class="kv" id="accessDetails"></div><div class="actions"><button class="btn" data-extra="access">Refresh accessibility signals</button></div></article>
  <article class="card"><h3>Media and image capability matrix</h3><div class="kv" id="mediaMatrix"></div><div class="actions"><button class="btn" data-extra="matrix">Refresh media capabilities</button></div></article>
  <article class="card"><h3>Local report history</h3><p class="fine">History is stored only in this browser on this device. Saving is manual; clearing browser site data removes it.</p><div class="kv" id="historyDetails"></div><div class="actions"><button class="btn" data-extra="saveReport">Save current report locally</button><button class="btn" data-extra="compareReport">Compare last saved report</button><button class="btn" data-extra="clearHistory">Clear local history</button></div><div class="result" id="historyResult"></div></article>
  <article class="card"><h3>Report formats & privacy</h3><p class="fine">Export structured JSON, a simple CSV summary, or a redacted shareable JSON copy. PDF is available through Print report.</p><div class="actions"><button class="btn" data-extra="csv">Export CSV summary</button><button class="btn" data-extra="share">Export redacted JSON</button></div><div class="result" id="reportResult"></div></article>
 </div>
 <div class="notice" style="margin-top:14px">Important: browser APIs cannot reliably reveal the Wi-Fi SSID, MAC address, serial number, exact hardware model, OS-level DNS configuration, router details, full certificate chain or physical display defects. VPN/proxy classification is heuristic. TLS certificate inspection and full HTTP/3 verification require a dedicated external diagnostic service or server-side network tooling.</div>
</section>

<footer><span>Araha Device & Network Diagnostics · Runs in your browser</span><span>No database · No analytics · No diagnostic history is stored</span></footer>
</div>
<script>
'use strict';
const PHP = <?= json_encode($serverSnapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const IP = <?= json_encode($ipInfo, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const IP_ERROR = <?= json_encode($ipError, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const D = {};
const $ = id => document.getElementById(id);
const val = v => v === undefined || v === null || v === '' ? 'Not exposed' : String(v);
const yes = v => v ? 'Yes' : 'No';
const put = (id, obj) => { const el=$(id); if(!el)return; el.innerHTML=Object.entries(obj).map(([k,v])=>'<div class="k">'+esc(k)+'</div><div class="v">'+esc(val(v))+'</div>').join(''); };
const esc = s => String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const tags = (id, arr) => { $(id).innerHTML=arr.map(x=>'<span class="tag">'+esc(x)+'</span>').join(''); };
const setResult = (id,s) => { if($(id)) $(id).textContent=s; };
const ua=navigator.userAgent||'';
function browserGuess(){let m=ua.match(/(Edg|Edge)\/([\d.]+)/)?['Edge',RegExp.$2]:ua.match(/(OPR)\/([\d.]+)/)?['Opera',RegExp.$2]:ua.match(/(Chrome)\/([\d.]+)/)?['Chrome',RegExp.$2]:ua.match(/(Firefox)\/([\d.]+)/)?['Firefox',RegExp.$2]:ua.match(/Version\/([\d.]+).*Safari/)?['Safari',RegExp.$1]:['Unknown',''];return m[0]+(m[1]?' '+m[1]:'');}
function osGuess(){if(/Windows NT 10|Windows NT 11/.test(ua))return'Windows';if(/Mac OS X/.test(ua))return'macOS';if(/Android/.test(ua))return'Android';if(/iPhone|iPad|iPod/.test(ua))return'iOS/iPadOS';if(/Linux/.test(ua))return'Linux';return navigator.platform||'Unknown';}
function deviceGuess(){if(/iPad|Tablet/.test(ua))return'Tablet';if(/Mobi|Android/.test(ua))return'Mobile';return'Desktop / laptop (inferred)';}
function networkInfo(){const c=navigator.connection||navigator.mozConnection||navigator.webkitConnection;return c?{type:c.type,effective_type:c.effectiveType,downlink_mbps:c.downlink,rtt_ms:c.rtt,data_saver:c.saveData}:{};}
function webglInfo(){try{const c=document.createElement('canvas'),g=c.getContext('webgl')||c.getContext('experimental-webgl');if(!g)return{supported:false};const ext=g.getExtension('WEBGL_debug_renderer_info');return{supported:true,version:g.getParameter(g.VERSION),vendor:g.getParameter(g.VENDOR),renderer:g.getParameter(g.RENDERER),unmasked_vendor:ext?g.getParameter(ext.UNMASKED_VENDOR_WEBGL):'Restricted',unmasked_renderer:ext?g.getParameter(ext.UNMASKED_RENDERER_WEBGL):'Restricted',max_texture_size:g.getParameter(g.MAX_TEXTURE_SIZE)};}catch(e){return{supported:false,error:e.message};}}
const webgl=webglInfo();
const screenData={width:screen.width,height:screen.height,available_width:screen.availWidth,available_height:screen.availHeight,pixel_ratio:devicePixelRatio,color_depth:screen.colorDepth,pixel_depth:screen.pixelDepth,orientation:screen.orientation?.type,viewport_width:innerWidth,viewport_height:innerHeight,visual_viewport_scale:visualViewport?.scale,fullscreen:!!document.fullscreenElement};
const platform=navigator.userAgentData?.platform||navigator.platform||'Unknown';
const browser=browserGuess(), os=osGuess();
D.collected_at=new Date().toISOString();
D.php=PHP; D.ip_intelligence=IP; D.browser={name:browser,os,device_guess:deviceGuess(),user_agent:ua,platform,vendor:navigator.vendor,language:navigator.language,languages:navigator.languages,hardware_concurrency:navigator.hardwareConcurrency,device_memory_gb:navigator.deviceMemory,max_touch_points:navigator.maxTouchPoints};
D.screen=screenData; D.network=networkInfo(); D.webgl=webgl;
D.runtime={cookie_enabled:navigator.cookieEnabled,java_enabled:typeof navigator.javaEnabled==='function'?navigator.javaEnabled():null,pdf_viewer_enabled:navigator.pdfViewerEnabled,webdriver:navigator.webdriver,online:navigator.onLine,service_worker:!!navigator.serviceWorker,wasm:typeof WebAssembly!=='undefined',shared_array_buffer:typeof SharedArrayBuffer!=='undefined',cross_origin_isolated:window.crossOriginIsolated,secure_context:isSecureContext};
D.privacy={dnt:navigator.doNotTrack,global_privacy_control:navigator.globalPrivacyControl??null,gpc_header:PHP.gpc_header,dnt_header:PHP.dnt_header,third_party_cookie_signal:navigator.cookieEnabled?'Cookies enabled (third-party policy not directly exposed)':'Cookies disabled'};
D.performance={time_origin:performance.timeOrigin,navigation_type:performance.getEntriesByType('navigation')[0]?.type,dom_content_loaded_ms:performance.getEntriesByType('navigation')[0]?.domContentLoadedEventEnd,load_event_ms:performance.getEntriesByType('navigation')[0]?.loadEventEnd};
function init(){
 $('sumBrowser').textContent=browser;$('sumPlatform').textContent=os;$('sumDisplay').textContent=screen.width+' × '+screen.height;$('sumCpu').textContent=val(navigator.hardwareConcurrency);
 $('onlinePill').textContent=navigator.onLine?'Browser reports online':'Browser reports offline';$('privacyPill').textContent=navigator.globalPrivacyControl?'GPC enabled':navigator.doNotTrack==='1'?'DNT enabled':'Privacy signal not detected';
 put('ipDetails',{IP:PHP.remote_address,Type:IP?.type,Country:IP?.country,Region:IP?.region,City:IP?.city,ISP:IP?.isp,Organization:IP?.org,ASN:IP?.asn,Domain:IP?.domain,Timezone:IP?.timezone_id});
 put('browserDetails',{Browser:browser,Operating_system:os,Device_guess:deviceGuess(),Platform:platform,Vendor:navigator.vendor,Language:navigator.language,Languages:navigator.languages?.join(', '),User_agent:ua});
 put('displayDetails',{Screen:screen.width+' × '+screen.height,Viewport:innerWidth+' × '+innerHeight,Pixel_ratio:devicePixelRatio,Color_depth:screen.colorDepth+' bit',Orientation:screen.orientation?.type,Timezone:Intl.DateTimeFormat().resolvedOptions().timeZone,Locale:Intl.DateTimeFormat().resolvedOptions().locale,Reduced_motion:matchMedia('(prefers-reduced-motion: reduce)').matches,Dark_mode:matchMedia('(prefers-color-scheme: dark)').matches,High_contrast:matchMedia('(prefers-contrast: more)').matches});
 put('networkDetails',{Online:navigator.onLine,Connection_type:D.network.type,Effective_type:D.network.effective_type,Downlink_hint_mbps:D.network.downlink,Rtt_hint_ms:D.network.rtt,Data_saver:D.network.saveData,WebRTC:!!window.RTCPeerConnection});
 put('hardwareDetails',{Logical_CPU_cores:navigator.hardwareConcurrency,Device_memory_GB:navigator.deviceMemory,Touch_points:navigator.maxTouchPoints,Device_category:deviceGuess(),Battery_API:!!navigator.getBattery,Gamepad_API:!!navigator.getGamepads,WebGL:webgl.supported});
 put('capabilityDetails',{WebAssembly:D.runtime.wasm,Service_worker:D.runtime.service_worker,WebSocket:!!window.WebSocket,WebRTC:!!window.RTCPeerConnection,WebGPU:!!navigator.gpu,WebCodecs:!!window.VideoDecoder,WebTransport:!!window.WebTransport,WebXR:!!navigator.xr,File_system_access:!!window.showOpenFilePicker,Clipboard:!!navigator.clipboard,Share:!!navigator.share});
 put('screenDetails',screenData);put('deviceDetails',{Device_guess:deviceGuess(),Touch_points:navigator.maxTouchPoints,Pointer_coarse:matchMedia('(pointer: coarse)').matches,Pointer_fine:matchMedia('(pointer: fine)').matches,Hover:matchMedia('(hover: hover)').matches,CPU_cores:navigator.hardwareConcurrency,Memory_hint_GB:navigator.deviceMemory,Gamepads:navigator.getGamepads?Array.from(navigator.getGamepads()).filter(Boolean).length:'Unsupported'});
 put('browserLabDetails',{Browser:browser,OS_guess:os,UA_platform:platform,UA_vendor:navigator.vendor,UA_data_available:!!navigator.userAgentData,PDF_viewer:navigator.pdfViewerEnabled,Webdriver:navigator.webdriver,JavaScript:yes(true),Cookies_enabled:navigator.cookieEnabled});
 put('privacyDetails',{Do_not_track:navigator.doNotTrack,Global_privacy_control:navigator.globalPrivacyControl,Secure_context:isSecureContext,Webdriver_flag:navigator.webdriver,Referrer_policy:document.querySelector('meta[name=referrer]')?.content,Third_party_cookie_control:'Not directly inspectable'});
 put('securityDetails',{Secure_context:isSecureContext,HTTPS_from_request:PHP.https,Origin_isolated:crossOriginIsolated,COOP_COEP_effect:'Cross-origin isolation: '+yes(crossOriginIsolated),Mixed_content_audit:'Not available to page JavaScript',TLS_certificate_details:'Not exposed to page JavaScript'});
 put('trackingDetails',{Cookies_enabled:navigator.cookieEnabled,Do_not_track:navigator.doNotTrack,Global_privacy_control:navigator.globalPrivacyControl,Local_storage:storageAvailable('localStorage'),Session_storage:storageAvailable('sessionStorage'),IndexedDB:!!window.indexedDB,Cache_API:!!window.caches,Service_worker:!!navigator.serviceWorker});
 put('automationDetails',{Navigator_webdriver:navigator.webdriver,User_agent_automation_tokens:/HeadlessChrome|PhantomJS|Selenium|Playwright|Puppeteer/i.test(ua),Chrome_runtime:!!window.chrome,Note:'These indicators are inconclusive and can be modified.'});
 put('headerDetails',{Accept:PHP.accept,Accept_language:PHP.accept_language,Accept_encoding:PHP.accept_encoding,DNT_header:PHP.dnt_header,GPC_header:PHP.gpc_header,Forwarded_header_untrusted:PHP.forwarding_headers_untrusted.forwarded,X_forwarded_for_untrusted:PHP.forwarding_headers_untrusted.x_forwarded_for,X_real_ip_untrusted:PHP.forwarding_headers_untrusted.x_real_ip});
 put('ipGeoDetails',{IP:IP?.ip,Country:IP?.country,Region:IP?.region,City:IP?.city,Postal:IP?.postal,Latitude:IP?.latitude,Longitude:IP?.longitude,ISP:IP?.isp,ASN:IP?.asn,Timezone:IP?.timezone_id,IP_lookup_error:IP_ERROR});
 put('clockDetails',{Local_time:new Date().toString(),UTC_time:new Date().toISOString(),Timezone:Intl.DateTimeFormat().resolvedOptions().timeZone,Timezone_offset_minutes:new Date().getTimezoneOffset(),Locale:Intl.DateTimeFormat().resolvedOptions().locale,Unix_time_ms:Date.now()});
 put('memoryDetails',{JS_heap_limit_mb:performance.memory?Math.round(performance.memory.jsHeapSizeLimit/1048576):'Not exposed',JS_heap_used_mb:performance.memory?Math.round(performance.memory.usedJSHeapSize/1048576):'Not exposed',Device_memory_hint_gb:navigator.deviceMemory,Logical_CPU_cores:navigator.hardwareConcurrency});
 put('timingDetails',{Navigation_type:D.performance.navigation_type,DOM_content_loaded_ms:D.performance.dom_content_loaded_ms,Load_event_ms:D.performance.load_event_ms,Time_origin:D.performance.time_origin});
 const formats={AVIF:'image/avif',WebP:'image/webp',WebP_lossless:'image/webp;lossless=true',SVG:'image/svg+xml',PNG:'image/png',JPEG:'image/jpeg'};
 put('imageFormats',Object.fromEntries(Object.entries(formats).map(([k,v])=>[k,(()=>{const c=document.createElement('canvas');try{return c.toDataURL(v).startsWith('data:'+v.split(';')[0])?'Supported':'Not supported';}catch(e){return'Unknown';}})()])));
 const video=document.createElement('video'),audio=document.createElement('audio');
 const codecs={'H.264':'video/mp4; codecs="avc1.42E01E"','H.265/HEVC':'video/mp4; codecs="hvc1"','VP8':'video/webm; codecs="vp8"','VP9':'video/webm; codecs="vp9"','AV1':'video/mp4; codecs="av01.0.05M.08"','Opus':'audio/ogg; codecs="opus"','AAC':'audio/mp4; codecs="mp4a.40.2"','MP3':'audio/mpeg'};
 put('codecDetails',Object.fromEntries(Object.entries(codecs).map(([k,v])=>[k,(v.startsWith('audio')?audio:video).canPlayType(v)||'No'])));
 put('webglDetails',webgl);
 tags('apiTags',['WebAssembly','WebSocket','WebRTC','WebGPU','WebCodecs','WebTransport','WebXR','Service Worker','IndexedDB','Cache API','Clipboard','Web Share','File System Access','Compression Streams','BroadcastChannel','ResizeObserver','IntersectionObserver','MutationObserver','Web Animations'].map(x=>x+': '+({'WebAssembly':!!window.WebAssembly,'WebSocket':!!window.WebSocket,'WebRTC':!!window.RTCPeerConnection,'WebGPU':!!navigator.gpu,'WebCodecs':!!window.VideoDecoder,'WebTransport':!!window.WebTransport,'WebXR':!!navigator.xr,'Service Worker':!!navigator.serviceWorker,'IndexedDB':!!window.indexedDB,'Cache API':!!window.caches,'Clipboard':!!navigator.clipboard,'Web Share':!!navigator.share,'File System Access':!!window.showOpenFilePicker,'Compression Streams':!!window.CompressionStream,'BroadcastChannel':!!window.BroadcastChannel,'ResizeObserver':!!window.ResizeObserver,'IntersectionObserver':!!window.IntersectionObserver,'MutationObserver':!!window.MutationObserver,'Web Animations':!!Element.prototype.animate}[x]?'Yes':'No'));
 const fonts=['Arial','Arial Black','Calibri','Cambria','Comic Sans MS','Consolas','Courier New','Georgia','Helvetica','Impact','Inter','Roboto','Segoe UI','Tahoma','Times New Roman','Trebuchet MS','Verdana','Noto Sans'];
 const ctx=document.createElement('canvas').getContext('2d');tags('fontDetails',fonts.filter(f=>{ctx.font='16px monospace';const a=ctx.measureText('mmmmmmmmmmlli').width;ctx.font='16px "'+f+'", monospace';return ctx.measureText('mmmmmmmmmmlli').width!==a;}));
 put('canvasDetails',{Status:'Not generated'});put('audioDetails',{Status:'Not generated'});put('addressChecks',{IPv4:'Not tested',IPv6:'Not tested'});put('latencyChecks',{Cloudflare:'Not tested',Google:'Not tested',Quad9:'Not tested'});put('dnsChecks',{Cloudflare_DoH:'Not tested',Google_DoH:'Not tested',Quad9_DoH:'Not tested'});
 refreshStorage();refreshPermissions();refreshMedia(false);refreshBattery();refreshAccess();refreshMatrix();renderInputStats();refreshHistory();D.initialized=true;
}
function storageAvailable(type){try{const s=window[type],k='__diag_test';s.setItem(k,'1');s.removeItem(k);return true;}catch(e){return false;}}
async function refreshStorage(){try{const x=await navigator.storage?.estimate();put('storageDetails',{Quota_MB:x?Math.round(x.quota/1048576):null,Usage_MB:x?Math.round(x.usage/1048576):null,Usage_percent:x&&x.quota?((x.usage/x.quota)*100).toFixed(2)+'%':null,Persistent_storage_API:!!navigator.storage?.persist,Persistence_granted:navigator.storage?.persisted?await navigator.storage.persisted():null});}catch(e){put('storageDetails',{Error:e.message});}}
async function refreshBattery(){try{if(!navigator.getBattery){put('batteryDetails',{Status:'Unsupported'});return;}const b=await navigator.getBattery();put('batteryDetails',{Level:Math.round(b.level*100)+'%',Charging:b.charging,Charging_time_seconds:b.chargingTime,Discharging_time_seconds:b.dischargingTime});D.battery={level:b.level,charging:b.charging,chargingTime:b.chargingTime,dischargingTime:b.dischargingTime};}catch(e){put('batteryDetails',{Status:'Unavailable',Reason:e.message});}}
async function refreshPermissions(){const names=['geolocation','camera','microphone','notifications','clipboard-read','clipboard-write','midi','persistent-storage'];const out={};if(!navigator.permissions){put('permissionDetails',{Status:'Permissions API unsupported'});return;}for(const n of names){try{const p=await navigator.permissions.query({name:n});out[n]=p.state;}catch(e){out[n]='Unsupported / restricted';}}D.permissions=out;put('permissionDetails',out);}
async function refreshMedia(allowLabels){try{if(!navigator.mediaDevices?.enumerateDevices){put('mediaDetails',{Status:'Unsupported'});return;}const list=await navigator.mediaDevices.enumerateDevices();const counts={audioinput:0,audiooutput:0,videoinput:0};list.forEach(x=>counts[x.kind]=(counts[x.kind]||0)+1);put('mediaDetails',{...counts,Labels_visible:list.some(x=>x.label),Permission_or_labels:'Labels may be hidden until permission is granted'});D.media_devices=counts;}catch(e){put('mediaDetails',{Status:'Unavailable',Reason:e.message});}}
async function hashText(s){const b=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(s));return Array.from(new Uint8Array(b)).map(x=>x.toString(16).padStart(2,'0')).join('');}
async function runAddresses(){setResult('addressResult','Checking public IPv4 and IPv6 endpoints…');const tests=[['IPv4','https://api4.ipify.org?format=json'],['IPv6','https://api6.ipify.org?format=json']];const out={};for(const [k,u] of tests){try{const r=await fetch(u,{cache:'no-store',signal:AbortSignal.timeout(7000)});if(!r.ok)throw Error('HTTP '+r.status);const j=await r.json();out[k]=j.ip||'Reachable';}catch(e){out[k]='Unavailable / blocked';}}D.address_family=out;put('addressChecks',out);setResult('addressResult',JSON.stringify(out,null,2));}
async function runLatency(){const urls=[['Cloudflare','https://www.cloudflare.com/cdn-cgi/trace'],['Google','https://www.google.com/generate_204'],['Quad9','https://dns.quad9.net/dns-query']];const out={};for(const [name,url] of urls){const times=[];for(let i=0;i<3;i++){const t=performance.now();try{await fetch(url+(url.includes('?')?'&':'?')+'diag='+Math.random(),{mode:'no-cors',cache:'no-store',signal:AbortSignal.timeout(6000)});times.push(Math.round(performance.now()-t));}catch(e){}}out[name]=times.length?Math.round(times.reduce((a,b)=>a+b,0)/times.length)+' ms avg':'Blocked / timeout';}D.latency=out;put('latencyChecks',out);setResult('latencyResult','Approximate browser request timing (not ICMP ping):\n'+JSON.stringify(out,null,2));}
async function runDNS(){const endpoints=[['Cloudflare_DoH','https://cloudflare-dns.com/dns-query?name=example.com&type=A'],['Google_DoH','https://dns.google/resolve?name=example.com&type=A'],['Quad9_DoH','https://dns.quad9.net/dns-query?name=example.com&type=A']];const out={};for(const [n,u] of endpoints){const t=performance.now();try{const r=await fetch(u,{headers:{Accept:'application/dns-json'},cache:'no-store',signal:AbortSignal.timeout(7000)});out[n]=r.ok?'Reachable · '+Math.round(performance.now()-t)+' ms':'HTTP '+r.status;}catch(e){out[n]='Blocked / timeout';}}D.doh=out;put('dnsChecks',out);setResult('dnsResult',JSON.stringify(out,null,2));}
async function runDownload(){const size=5*1024*1024,url='https://speed.cloudflare.com/__down?bytes='+size;const t=performance.now();$('downloadProgress').style.width='10%';setResult('downloadResult','Downloading 5 MB test payload…');try{const r=await fetch(url,{cache:'no-store',signal:AbortSignal.timeout(30000)});if(!r.ok)throw Error('HTTP '+r.status);const b=await r.arrayBuffer(),sec=(performance.now()-t)/1000,mbps=(b.byteLength*8/sec/1e6).toFixed(2);$('downloadValue').textContent=mbps+' Mbps';$('downloadProgress').style.width='100%';D.download={bytes:b.byteLength,seconds:sec,mbps:Number(mbps)};setResult('downloadResult','Received '+b.byteLength.toLocaleString()+' bytes in '+sec.toFixed(2)+' seconds. Single-run browser estimate.');}catch(e){$('downloadValue').textContent='Failed';setResult('downloadResult',e.message);$('downloadProgress').style.width='0%';}}
async function runUpload(){const size=4*1024*1024,body=new Uint8Array(size);crypto.getRandomValues(body.subarray(0,Math.min(size,65536)));const t=performance.now();setResult('uploadResult','Sending 4 MB to this site; the server discards it after measurement…');try{const r=await fetch(location.pathname+'?action=upload-test',{method:'POST',headers:{'Content-Type':'application/octet-stream'},body,cache:'no-store',signal:AbortSignal.timeout(20000)});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Upload failed');const sec=(performance.now()-t)/1000,mbps=(j.bytes*8/sec/1e6).toFixed(2);$('uploadValue').textContent=mbps+' Mbps';D.upload={bytes:j.bytes,seconds:sec,mbps:Number(mbps)};setResult('uploadResult','Server received '+j.bytes.toLocaleString()+' bytes in '+sec.toFixed(2)+' seconds. Includes request/response overhead.');}catch(e){$('uploadValue').textContent='Failed';setResult('uploadResult',e.message);}}
async function runWebRTC(){setResult('webrtcResult','Gathering ICE candidates. This contacts a public STUN service…');if(!window.RTCPeerConnection){setResult('webrtcResult','WebRTC unsupported.');return;}const pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});const found=[];pc.createDataChannel('diag');pc.onicecandidate=e=>{if(e.candidate){const s=e.candidate.candidate;found.push({candidate:s,type:(s.match(/ typ (\w+)/)||[])[1]||'unknown',protocol:(s.match(/ (udp|tcp) /)||[])[1]||'unknown'});}else{pc.close();D.webrtc_candidates=found;setResult('webrtcResult',found.length?JSON.stringify(found,null,2):'No candidates exposed. Browser privacy controls may mask them.');}};try{await pc.setLocalDescription(await pc.createOffer());setTimeout(()=>{if(pc.signalingState!=='closed'){pc.close();D.webrtc_candidates=found;setResult('webrtcResult',found.length?JSON.stringify(found,null,2):'No candidates received before timeout.');}},8000);}catch(e){pc.close();setResult('webrtcResult',e.message);}}
async function runGPS(){if(!navigator.geolocation){setResult('gpsResult','Geolocation unsupported.');return;}setResult('gpsResult','Waiting for browser permission…');navigator.geolocation.getCurrentPosition(p=>{const c=p.coords;D.gps={latitude:c.latitude,longitude:c.longitude,accuracy_m:c.accuracy,altitude_m:c.altitude,altitude_accuracy_m:c.altitudeAccuracy,heading:c.heading,speed_mps:c.speed,timestamp:new Date(p.timestamp).toISOString()};put('gpsDetails',D.gps);setResult('gpsResult','Location received. Coordinates are stored only in this page until you leave or export.');},e=>setResult('gpsResult',e.message),{enableHighAccuracy:true,timeout:15000,maximumAge:0});}
async function runSensors(){const out={};for(const n of ['accelerometer','gyroscope','magnetometer']){try{if(!window[n]){out[n]='Unsupported';continue;}const s=new window[n]({frequency:1});await new Promise(resolve=>{s.addEventListener('reading',()=>{out[n]='Available · sample received';s.stop();resolve();},{once:true});s.addEventListener('error',()=>{out[n]='Permission denied / unavailable';resolve();},{once:true});setTimeout(()=>{s.stop();resolve();},2500);});}catch(e){out[n]=e.message;}}if(typeof DeviceOrientationEvent!=='undefined')out.device_orientation='API available';if(typeof DeviceMotionEvent!=='undefined')out.device_motion='API available';D.sensors=out;put('sensorDetails',out);setResult('sensorResult','Sensor API status collected. Exact readings are not retained.');}
async function runCanvas(){const c=document.createElement('canvas');c.width=320;c.height=80;const x=c.getContext('2d');x.textBaseline='top';x.font='16px Arial';x.fillStyle='#f60';x.fillRect(10,10,100,30);x.fillStyle='#069';x.fillText('Araha diagnostics 🌐',12,14);x.fillStyle='rgba(102,204,0,.7)';x.fillText('render sample',15,48);const data=c.toDataURL();const hash=await hashText(data);D.canvas={sha256:hash,data_length:data.length};put('canvasDetails',{SHA256:hash,Data_length:data.length,Uploaded:'No'});}
async function runAudio(){try{const C=window.OfflineAudioContext||window.webkitOfflineAudioContext;if(!C){put('audioDetails',{Status:'Unsupported'});return;}const ctx=new C(1,44100,44100),osc=ctx.createOscillator(),comp=ctx.createDynamicsCompressor();osc.type='triangle';osc.frequency.value=10000;comp.threshold.value=-50;comp.knee.value=40;comp.ratio.value=12;comp.attack.value=0;comp.release.value=.25;osc.connect(comp);comp.connect(ctx.destination);osc.start(0);const b=await ctx.startRendering(),a=b.getChannelData(0);let sum=0;for(let i=0;i<a.length;i+=100)sum+=Math.abs(a[i]);const hash=await hashText(String(sum));D.audio={sample_metric:sum,sha256:hash};put('audioDetails',{SHA256:hash,Sample_metric:sum,Uploaded:'No'});}catch(e){put('audioDetails',{Status:'Unavailable',Reason:e.message});}}
async function runJSBench(){const n=1800000,t=performance.now();let z=0;for(let i=1;i<n;i++)z+=(Math.sqrt(i)*Math.sin(i))%7;const ms=performance.now()-t;$('jsBenchValue').textContent=ms.toFixed(1)+' ms';D.js_benchmark={iterations:n,ms:Number(ms.toFixed(2)),checksum:z};setResult('jsBenchResult','Completed '+n.toLocaleString()+' arithmetic iterations. Lower time is faster; results are not comparable across different devices or browser loads.');}
async function runFPS(){setResult('fpsResult','Sampling animation frames for approximately 2 seconds…');const times=[];let start;await new Promise(resolve=>{function frame(t){if(!start)start=t;times.push(t);if(t-start<2000)requestAnimationFrame(frame);else resolve();}requestAnimationFrame(frame);});const diffs=times.slice(1).map((x,i)=>x-times[i]).filter(x=>x>0),avg=diffs.reduce((a,b)=>a+b,0)/Math.max(1,diffs.length),fps=1000/avg;$('fpsValue').textContent=fps.toFixed(1)+' FPS (~'+(1000/fps).toFixed(2)+' ms/frame)';D.frame_cadence={samples:times.length,average_fps:Number(fps.toFixed(2)),average_frame_ms:Number(avg.toFixed(2))};setResult('fpsResult','Measured during a short requestAnimationFrame sample. This is a cadence estimate, not a guaranteed physical display refresh rate.');}

let avStream=null, inputStats={keys:0,clicks:0,pointer_moves:0,wheel:0,touches:0,last_key:'—',last_pointer:'—'};
function renderInputStats(){put('inputDetails',inputStats);}
['keydown','click','pointermove','wheel','touchstart'].forEach(type=>window.addEventListener(type,e=>{if(type==='keydown'){inputStats.keys++;inputStats.last_key=e.key===' '?'Space':e.key;}if(type==='click')inputStats.clicks++;if(type==='pointermove'){inputStats.pointer_moves++;inputStats.last_pointer=Math.round(e.clientX)+', '+Math.round(e.clientY);}if(type==='wheel')inputStats.wheel++;if(type==='touchstart')inputStats.touches++;renderInputStats();},{passive:true}));
async function runMultiIP(){setResult('multiIpResult','Querying independent public IP providers…');const providers=[['ipify IPv4','https://api.ipify.org?format=json'],['ipify IPv6','https://api6.ipify.org?format=json'],['ipinfo','https://ipinfo.io/json'],['Cloudflare trace','https://www.cloudflare.com/cdn-cgi/trace']];const out={};for(const [n,u] of providers){try{const r=await fetch(u,{cache:'no-store',signal:AbortSignal.timeout(7000)});if(!r.ok)throw Error('HTTP '+r.status);const t=await r.text();let v=t;try{const j=JSON.parse(t);v=j.ip||j.loc||JSON.stringify(j);}catch{const m=t.match(/(?:^|\n)ip=([^\n]+)/);if(m)v=m[1];}out[n]=v;}catch(e){out[n]='Blocked / unavailable';}}D.public_ip_comparison=out;put('multiIpDetails',out);setResult('multiIpResult','Different providers can observe different egress routes. A failed provider is not proof of filtering.');}
async function runStability(){setResult('stabilityResult','Running 20 sequential HTTP timing samples…');const samples=[];let fails=0;const endpoint='https://www.cloudflare.com/cdn-cgi/trace';for(let i=0;i<20;i++){const t=performance.now();try{await fetch(endpoint+'?diag='+Date.now()+'-'+i,{mode:'no-cors',cache:'no-store',signal:AbortSignal.timeout(5000)});samples.push(performance.now()-t);}catch(e){fails++;}await new Promise(r=>setTimeout(r,180));}const sorted=[...samples].sort((a,b)=>a-b),avg=samples.length?samples.reduce((a,b)=>a+b,0)/samples.length:0,variance=samples.length?samples.reduce((s,x)=>s+(x-avg)**2,0)/samples.length:0,jitter=samples.length>1?samples.slice(1).reduce((s,x,i)=>s+Math.abs(x-samples[i]),0)/(samples.length-1):0;const out={attempts:20,successes:samples.length,failures:fails,loss_percent:((fails/20)*100).toFixed(1)+'%',average_http_time_ms:avg.toFixed(1),minimum_ms:sorted[0]?.toFixed(1)||'—',median_ms:sorted.length?sorted[Math.floor(sorted.length/2)].toFixed(1):'—',p95_ms:sorted.length?sorted[Math.min(sorted.length-1,Math.ceil(sorted.length*.95)-1)].toFixed(1):'—',mean_absolute_jitter_ms:jitter.toFixed(1)};D.connection_stability=out;put('stabilityDetails',out);setResult('stabilityResult','HTTP request timing only; failures may be endpoint/CORS/network errors. Not ICMP packet loss or standardized jitter.');}
async function runHeaders(){const names=['strict-transport-security','content-security-policy','content-security-policy-report-only','x-content-type-options','x-frame-options','referrer-policy','permissions-policy','cross-origin-opener-policy','cross-origin-embedder-policy','cross-origin-resource-policy'];const out={};try{const r=await fetch(location.href,{method:'HEAD',cache:'no-store',credentials:'same-origin'});names.forEach(n=>out[n]=r.headers.get(n)||'Not present / not exposed');out.status=r.status;out.navigation_protocol=performance.getEntriesByType('navigation')[0]?.nextHopProtocol||'Not exposed';out.secure_context=isSecureContext;out.note='Headers visible to this origin; reverse proxies/CDNs may alter responses.';D.response_security_headers=out;put('headersDetails',out);}catch(e){put('headersDetails',{Error:e.message});}}
function startDisplayTest(){const names=['Black','White','Red','Green','Blue','50% gray','Gradient','Checkerboard'];let idx=0;const el=document.createElement('div');el.className='display-overlay';el.tabIndex=0;el.innerHTML='<div class="pattern-label"></div><div class="controls"><button type="button" data-prev>Previous</button><button type="button" data-next>Next</button><button type="button" data-exit>Exit (Esc)</button></div>';document.body.appendChild(el);function paint(){el.className='display-overlay';const name=names[idx];if(name==='Black')el.style.background='#000';else if(name==='White')el.style.background='#fff';else if(name==='Red')el.style.background='#f00';else if(name==='Green')el.style.background='#0f0';else if(name==='Blue')el.style.background='#00f';else if(name==='50% gray')el.style.background='#808080';else if(name==='Gradient')el.classList.add('gradient');else el.classList.add('checker');el.querySelector('.pattern-label').textContent=name+' · '+(idx+1)+' / '+names.length;}function close(){el.remove();window.removeEventListener('keydown',key);}function key(e){if(e.key==='Escape')close();if(e.key==='ArrowRight'){idx=(idx+1)%names.length;paint();}if(e.key==='ArrowLeft'){idx=(idx+names.length-1)%names.length;paint();}}el.querySelector('[data-next]').onclick=()=>{idx=(idx+1)%names.length;paint();};el.querySelector('[data-prev]').onclick=()=>{idx=(idx+names.length-1)%names.length;paint();};el.querySelector('[data-exit]').onclick=close;window.addEventListener('keydown',key);paint();el.focus();}
async function playTone(sweep=false){try{const C=window.AudioContext||window.webkitAudioContext;if(!C)throw Error('Web Audio API unsupported');const ctx=new C();await ctx.resume();const osc=ctx.createOscillator(),gain=ctx.createGain();osc.type='sine';osc.frequency.setValueAtTime(sweep?180:440,ctx.currentTime);if(sweep)osc.frequency.exponentialRampToValueAtTime(1600,ctx.currentTime+1.3);gain.gain.setValueAtTime(.0001,ctx.currentTime);gain.gain.exponentialRampToValueAtTime(.12,ctx.currentTime+.03);gain.gain.exponentialRampToValueAtTime(.0001,ctx.currentTime+(sweep?1.5:.55));osc.connect(gain);gain.connect(ctx.destination);osc.start();osc.stop(ctx.currentTime+(sweep?1.55:.6));put('toneDetails',{Status:'Played after user action',Type:sweep?'180–1600 Hz sweep':'440 Hz sine',Output_sample_rate:ctx.sampleRate+' Hz'});osc.onended=()=>ctx.close();}catch(e){put('toneDetails',{Status:'Unavailable',Reason:e.message});}}
async function startAV(){try{if(!navigator.mediaDevices?.getUserMedia)throw Error('Camera/microphone API unavailable. HTTPS is required.');stopAV();avStream=await navigator.mediaDevices.getUserMedia({video:true,audio:true});const v=$('avPreview');v.srcObject=avStream;v.style.display='block';setResult('avResult','Camera and microphone permission granted. Live preview is local to this page.');const tracks=avStream.getTracks().map(t=>({kind:t.kind,label:t.label,settings:t.getSettings?.()}));D.media_capture={tracks};}catch(e){setResult('avResult',e.name+': '+e.message);}}
function stopAV(){if(avStream){avStream.getTracks().forEach(t=>t.stop());avStream=null;}if($('avPreview')){$('avPreview').srcObject=null;$('avPreview').style.display='none';}setResult('avResult','Media tracks stopped.');}
async function inspectGPU(){if(!navigator.gpu){put('gpuDetails',{WebGPU:'Unsupported / disabled'});return;}try{const a=await navigator.gpu.requestAdapter();if(!a){put('gpuDetails',{WebGPU:'No adapter returned'});return;}let info={};try{info=await a.requestAdapterInfo();}catch(e){info={info:'Redacted / unavailable'};}const out={WebGPU:'Available',vendor:info.vendor||'Redacted',architecture:info.architecture||'Redacted',device:info.device||'Redacted',description:info.description||'Redacted',features:Array.from(a.features||[]).join(', '),limits_summary:'maxTextureDimension2D '+a.limits.maxTextureDimension2D};D.webgpu=out;put('gpuDetails',out);}catch(e){put('gpuDetails',{WebGPU:'Unavailable',Reason:e.message});}}
async function runExtendedBench(){const out={};let t=performance.now(),sum=0;for(let i=0;i<1500000;i++)sum+=(i%97)*Math.sqrt(i+1);out.cpu_loop_ms=(performance.now()-t).toFixed(1);out.cpu_checksum=Math.round(sum);t=performance.now();const root=document.createElement('div'),frag=document.createDocumentFragment();for(let i=0;i<4000;i++){const n=document.createElement('span');n.textContent='x'+i;frag.appendChild(n);}root.appendChild(frag);out.dom_create_4000_ms=(performance.now()-t).toFixed(1);root.remove();t=performance.now();const key='__araha_bench_'+Date.now(),payload='x'.repeat(256*1024);try{localStorage.setItem(key,payload);out.local_storage_write_256kb_ms=(performance.now()-t).toFixed(1);t=performance.now();localStorage.getItem(key);out.local_storage_read_256kb_ms=(performance.now()-t).toFixed(1);localStorage.removeItem(key);}catch(e){out.local_storage='Blocked / quota exceeded';}D.extended_benchmarks=out;put('benchDetails',out);}
function refreshAccess(){const q=s=>matchMedia(s).matches;const out={Reduced_motion:q('(prefers-reduced-motion: reduce)'),Color_scheme_dark:q('(prefers-color-scheme: dark)'),High_contrast:q('(prefers-contrast: more)'),Forced_colors:q('(forced-colors: active)'),Inverted_colors:q('(inverted-colors: inverted)'),Coarse_pointer:q('(pointer: coarse)'),Fine_pointer:q('(pointer: fine)'),Hover_supported:q('(hover: hover)'),Any_hover:q('(any-hover: hover)'),Color_gamut_P3:q('(color-gamut: p3)'),HDR_high_dynamic_range:q('(dynamic-range: high)'),Reduced_transparency:q('(prefers-reduced-transparency: reduce)'),Text_scaling:'Browser zoom / OS text scaling not reliably exposed',Screen_reader:'No reliable browser API to detect screen-reader usage'};D.accessibility=out;put('accessDetails',out);}
function refreshMatrix(){const v=document.createElement('video'),a=document.createElement('audio');const matrix={'AV1 video':v.canPlayType('video/mp4; codecs="av01.0.05M.08"'),'VP9 video':v.canPlayType('video/webm; codecs="vp9"'),'H.264 video':v.canPlayType('video/mp4; codecs="avc1.42E01E"'),'HEVC video':v.canPlayType('video/mp4; codecs="hvc1"'),'Opus audio':a.canPlayType('audio/ogg; codecs="opus"'),'AAC audio':a.canPlayType('audio/mp4; codecs="mp4a.40.2"'),'FLAC audio':a.canPlayType('audio/flac'),'WebM container':v.canPlayType('video/webm'),'MP4 container':v.canPlayType('video/mp4'),'MediaRecorder':!!window.MediaRecorder,'MediaDevices':!!navigator.mediaDevices,'WebCodecs VideoDecoder':!!window.VideoDecoder,'WebCodecs AudioDecoder':!!window.AudioDecoder};D.media_matrix=matrix;put('mediaMatrix',matrix);}

function localHistory(){try{return JSON.parse(localStorage.getItem('araha_diag_history')||'[]');}catch(e){return[];}}
function refreshHistory(){const h=localHistory();put('historyDetails',{Saved_reports:h.length,Latest:h[0]?.saved_at||'None',Storage:'This browser only'});}
function saveLocalReport(){const h=localHistory(),snapshot=JSON.parse(JSON.stringify(D));h.unshift({saved_at:new Date().toISOString(),report:snapshot});localStorage.setItem('araha_diag_history',JSON.stringify(h.slice(0,10)));refreshHistory();setResult('historyResult','Saved this report locally. Up to 10 reports are retained in this browser.');}
function compareLocalReport(){const h=localHistory();if(!h.length){setResult('historyResult','No saved report exists yet.');return;}const prev=h[0].report||{},now=D,keys=['browser','screen','network','webgl','address_family','latency','doh','download','upload','connection_stability','extended_benchmarks'];const diff={};keys.forEach(k=>{if(JSON.stringify(prev[k])!==JSON.stringify(now[k]))diff[k]={previous:prev[k]??'Not recorded',current:now[k]??'Not recorded'};});setResult('historyResult',Object.keys(diff).length?JSON.stringify({compared_with:h[0].saved_at,changed_sections:diff},null,2):'No differences in the selected report sections, or no matching tests have been run.');}
function clearLocalHistory(){localStorage.removeItem('araha_diag_history');refreshHistory();setResult('historyResult','Local report history cleared.');}
function exportCSV(){const rows=[['section','field','value']];function walk(prefix,obj){if(obj&&typeof obj==='object'&&!Array.isArray(obj)){Object.entries(obj).forEach(([k,v])=>walk(prefix?prefix+'.'+k:k,v));}else rows.push([prefix.split('.')[0]||'report',prefix,String(obj??'')]);}walk('',D);const csv=rows.map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\\r\\n');const a=document.createElement('a'),u=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.href=u;a.download='araha-diagnostics-summary.csv';a.click();URL.revokeObjectURL(u);setResult('reportResult','CSV summary exported.');}
function exportRedacted(){const copy=JSON.parse(JSON.stringify(D));delete copy.gps;delete copy.webrtc_candidates;delete copy.php?.remote_address;delete copy.php?.forwarding_headers_untrusted;delete copy.ip_intelligence;delete copy.ip_geo;delete copy.media_capture;const a=document.createElement('a'),u=URL.createObjectURL(new Blob([JSON.stringify(copy,null,2)],{type:'application/json'}));a.href=u;a.download='araha-diagnostics-redacted.json';a.click();URL.revokeObjectURL(u);setResult('reportResult','Redacted JSON exported. Review the file before sharing; some browser/device details may still be identifying.');}

const extraActions={multiip:runMultiIP,stability:runStability,headers:runHeaders,inputreset:()=>{inputStats={keys:0,clicks:0,pointer_moves:0,wheel:0,touches:0,last_key:'—',last_pointer:'—'};renderInputStats();setResult('inputResult','Counters reset. Interact with the page to populate them.');},gamepad:()=>{const g=navigator.getGamepads?Array.from(navigator.getGamepads()).filter(Boolean):[];const out=g.map((x,i)=>({index:i,id:x.id,mapping:x.mapping,buttons_pressed:x.buttons.filter(b=>b.pressed).length,button_count:x.buttons.length,axes:Array.from(x.axes)}));D.gamepads=out;put('gamepadDetails',out.length?Object.fromEntries(out.map((x,i)=>['Gamepad '+(i+1),JSON.stringify(x)])):{Status:'No gamepad detected. Press a controller button and retry.'});},displaytest:startDisplayTest,tone:()=>playTone(false),toneSweep:()=>playTone(true),avstart:startAV,avstop:stopAV,gpu:inspectGPU,bench:runExtendedBench,access:refreshAccess,matrix:refreshMatrix,saveReport:saveLocalReport,compareReport:compareLocalReport,clearHistory:clearLocalHistory,csv:exportCSV,share:exportRedacted};
document.querySelectorAll('[data-extra]').forEach(b=>b.addEventListener('click',async()=>{b.disabled=true;try{await extraActions[b.dataset.extra]();}catch(e){console.error(e);}finally{b.disabled=false;}}));
window.addEventListener('pagehide',stopAV);

const actions={addresses:runAddresses,latency:runLatency,dns:runDNS,download:runDownload,upload:runUpload,webrtc:runWebRTC,gps:runGPS,sensors:runSensors,canvas:runCanvas,audio:runAudio,jsbench:runJSBench,fps:runFPS,battery:refreshBattery,storage:refreshStorage,permissions:refreshPermissions,media:()=>refreshMedia(true)};
document.querySelectorAll('[data-run]').forEach(b=>b.addEventListener('click',async()=>{b.disabled=true;try{await actions[b.dataset.run]();}catch(e){console.error(e);}finally{b.disabled=false;}}));
document.querySelectorAll('[data-tab]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.setAttribute('aria-selected',String(x===b)));document.querySelectorAll('.panel').forEach(x=>x.classList.toggle('active',x.id===b.dataset.tab));}));
$('refreshBtn').addEventListener('click',()=>location.reload());
$('copyBtn').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(JSON.stringify(D,null,2));$('copyBtn').textContent='Copied';setTimeout(()=>$('copyBtn').textContent='Copy report',1500);}catch(e){alert('Clipboard access was blocked. Use Export JSON instead.');}});
$('jsonBtn').addEventListener('click',()=>{const blob=new Blob([JSON.stringify(D,null,2)],{type:'application/json'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='araha-diagnostics-'+new Date().toISOString().replace(/[:.]/g,'-')+'.json';a.click();URL.revokeObjectURL(a.href);});
window.addEventListener('online',()=>{$('onlinePill').textContent='Browser reports online';});window.addEventListener('offline',()=>{$('onlinePill').textContent='Browser reports offline';});
init();
</script>
</body>
</html>

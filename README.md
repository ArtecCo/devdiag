# Araha Device & Network Diagnostics

A single-file PHP diagnostics page for `ip.araha.co.in`. The application combines server-observed request information with browser APIs and explicit, user-triggered network/device tests.

## Files

- `index.php` — complete application (PHP, HTML, CSS and JavaScript in one file).

## Requirements

- PHP 8.0 or newer (PHP 8.1+ recommended).
- PHP cURL extension enabled for IP intelligence lookup.
- HTTPS strongly recommended. Geolocation, clipboard, battery and several device APIs require a secure context.
- Modern browser with JavaScript enabled.

No database, Composer packages, npm packages, build step, cron job or background service is required.

## Deploy

1. Back up the existing document-root files.
2. Upload `index.php` to the document root for `ip.araha.co.in`.
3. Ensure the hosting account uses a supported PHP version and has cURL enabled.
4. Visit the site over HTTPS.
5. Test the page on desktop and mobile browsers. Check the browser console and PHP error log if a feature is unavailable.

The upload test posts up to 4 MB to the same PHP endpoint. PHP's `post_max_size` must be at least 5 MB (8 MB or higher is suitable). The endpoint measures the request in memory and does not write the payload to disk.

## Included diagnostics

- IP address and IP-geolocation intelligence (IPWho.is).
- Server-observed request metadata and selected request headers.
- Browser, operating-system and device-category hints.
- Screen, viewport, pixel ratio, color depth, orientation and display preferences.
- Network Information API hints, online state, IPv4/IPv6 endpoint checks, request latency, DNS-over-HTTPS endpoint reachability, WebRTC ICE candidate inspection, download and upload throughput.
- CPU, memory and touch hints; battery, storage estimates, media-device enumeration and opt-in motion/orientation sampling.
- Browser APIs, image formats, media codec support, WebGL details, local canvas and offline-audio rendering hashes.
- Privacy signals, permissions, secure-context state, storage APIs and automation indicators.
- JavaScript execution and animation-frame cadence benchmarks.
- Opt-in GPS, JSON export, copy report and print-to-PDF through the browser.

## Privacy and limitations

- Diagnostic data is assembled in the visitor's browser. There is no database, analytics service, account system or diagnostic-history storage.
- IP intelligence lookup sends the visitor IP to IPWho.is from the PHP server. Network tests contact the named third-party endpoints when the visitor starts those tests.
- GPS, WebRTC candidate inspection, sensor sampling, throughput tests and local fingerprint samples are user-triggered. GPS coordinates and local rendering hashes are only added to the in-page report and are not automatically submitted to this site.
- The page does not open camera or microphone streams. Media enumeration may show generic device counts and labels only after browser permission has been granted elsewhere.
- Forwarded-IP headers are displayed as untrusted hints and are never used as the visitor's authoritative IP.
- Browser APIs intentionally hide information. JavaScript cannot reliably read the device's MAC address, serial number, exact hardware model, installed applications, Wi-Fi SSID/password, router configuration, operating-system DNS resolver, full TLS certificate details, or all browser/network internals. The site does not attempt to bypass those protections.
- IP geolocation is approximate and is not a substitute for GPS.
- Browser network timing is not ICMP ping. Browser tests may be affected by CORS, extensions, VPNs, proxies, privacy settings, captive portals and endpoint availability.
- WebRTC may reveal local or public network candidates to the page and the configured STUN service. The test is deliberately opt-in.
- Upload/download tests consume data. Upload tests are capped at 4 MB in the UI and 8 MB at the endpoint.
- Results are diagnostic hints, not a security audit, device identity proof, speed guarantee or forensic report.

## Search indexing

The page sends `noindex` directives in HTML metadata. These are crawler instructions, not access control. If the site must be private, protect it with HTTP authentication or hosting-level access controls.

<?php
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');

define('SECURITY', 1);
include 'config.php';

const CF_API_BASE = 'https://api.cloudflare.com/client/v4';

$headers = [
    'Authorization: Bearer ' . CF_API_TOKEN,
    'Content-Type: application/json',
];
define('HTTPHEADER', $headers);

function cloudflare(string $url, ?array $payload = null, string $method = 'GET'): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, HTTPHEADER);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Curl error: ' . $err);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON from Cloudflare (HTTP {$status}): {$raw}");
    }

    if (($decoded['success'] ?? false) !== true) {
        $errs = $decoded['errors'] ?? [];
        throw new RuntimeException('Cloudflare API error: ' . json_encode($errs));
    }

    return $decoded;
}

$currentip = trim(file_get_contents(PUBLIC_IP_ENDPOINT));
if (!filter_var($currentip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    throw new RuntimeException("Invalid public IPv4 detected: {$currentip}");
}

$file = __DIR__ . '/stored.ip';
$storedip = is_file($file) ? trim(file_get_contents($file)) : '';

if ($storedip !== $currentip) {
    // 1) Resolve zone id
    $zoneResp = cloudflare(
        CF_API_BASE . '/zones?name=' . urlencode(DOMAIN) . '&status=active&match=all'
    );

    if (empty($zoneResp['result'][0]['id'])) {
        throw new RuntimeException('Zone not found for DOMAIN=' . DOMAIN);
    }
    $zoneId = $zoneResp['result'][0]['id'];

    // 2) Update each A record if IP differs
    foreach (SUBDOMAINS as $fqdn) {
        $dnsResp = cloudflare(
            CF_API_BASE . '/zones/' . $zoneId . '/dns_records?type=A&name=' . urlencode($fqdn) . '&match=all'
        );

        if (empty($dnsResp['result'])) {
            // Optional: create missing record instead of skipping
            // cloudflare(CF_API_BASE . '/zones/' . $zoneId . '/dns_records', [
            //     'type'    => 'A',
            //     'name'    => $fqdn,
            //     'content' => $currentip,
            //     'ttl'     => 1,
            //     'proxied' => false,
            // ], 'POST');
            continue;
        }

        $record = $dnsResp['result'][0];
        if (($record['content'] ?? '') === $currentip) {
            continue;
        }

        // Build full PUT payload (safe with v4)
        $payload = [
            'type'    => 'A',
            'name'    => $record['name'],
            'content' => $currentip,
            'ttl'     => $record['ttl'] ?? 1,
            'proxied' => $record['proxied'] ?? false,
        ];

        cloudflare(
            CF_API_BASE . '/zones/' . $zoneId . '/dns_records/' . $record['id'],
            $payload,
            'PUT'
        );

        openlog('CloudflareUpdateLog', LOG_PID, LOG_LOCAL0);
        syslog(LOG_INFO, $fqdn . ' updated to ip: ' . $currentip);
        closelog();
    }

    file_put_contents($file, $currentip . PHP_EOL);
}
?>

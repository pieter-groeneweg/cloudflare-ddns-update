<?php

defined('SECURITY') or exit();

// Zone DNS name (apex)
define('DOMAIN', 'domain.com');

// Cloudflare API Token with:
// - Zone:Read
// - DNS:Edit
// scoped to this zone
define('CF_API_TOKEN', 'your_cloudflare_api_token');

// FQDN records you want to keep updated
define('SUBDOMAINS', [
    'subdomain_one.domain.com',
    'subdomain_two.domain.com',
]);

// Optional: where to fetch public IPv4 from
define('PUBLIC_IP_ENDPOINT', 'https://api.ipify.org');

?>

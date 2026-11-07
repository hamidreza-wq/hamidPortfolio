<?php

namespace App\Services\Platform;

class PublicDns
{
    public function ipv4(string $host): string
    {
        $addresses = gethostbynamel($host);
        if ($addresses === false || $addresses === []) {
            throw new IntegrationFailure('The configured SEO hostname has no resolvable public IPv4 address.');
        }
        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw new IntegrationFailure('SEO monitoring refuses private or reserved DNS addresses.');
            }
        }

        return $addresses[0];
    }

    public function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
}

<?php

namespace Best\Blueprint\Tenancy;

final class TenantDomains
{
    private const HOSTNAME = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * Central domains for a fresh tenancy config: the APP_URL host first, then local hosts.
     *
     * @return list<string>
     */
    public static function centralFromAppUrl(?string $appUrl): array
    {
        $host = $appUrl ? parse_url($appUrl, PHP_URL_HOST) : null;

        return array_values(array_unique(array_filter([$host ?: null, 'localhost', '127.0.0.1'])));
    }

    /**
     * @return list<string>
     */
    public function central(): array
    {
        return array_values(array_filter((array) config('tenancy.central_domains', []), 'is_string'));
    }

    /** The domain tenant subdomains hang off. */
    public function base(): string
    {
        $configured = config('blueprint.tenant_domain');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach ($this->central() as $domain) {
            if (filter_var($domain, FILTER_VALIDATE_IP) === false) {
                return $domain;
            }
        }

        return 'localhost';
    }

    public function forTenant(string $name): string
    {
        return "{$name}.{$this->base()}";
    }

    public function domainError(string $domain): ?string
    {
        $parents = array_unique([$this->base(), ...array_filter($this->central(), fn ($d) => filter_var($d, FILTER_VALIDATE_IP) === false)]);
        $valid = preg_match(self::HOSTNAME, $domain) === 1
            && ! in_array($domain, $this->central(), true)
            && array_filter($parents, fn (string $parent) => str_ends_with($domain, '.'.$parent)) !== [];

        return $valid ? null : "Invalid domain \"{$domain}\": it must be a subdomain of {$this->base()}.";
    }

    public function nameError(string $name): ?string
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $name) === 1
            ? null
            : "Invalid tenant name \"{$name}\". Use lowercase letters, numbers and dashes, e.g. acme.";
    }

    public function databaseError(string $database): ?string
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) === 1
            ? null
            : "Invalid database name \"{$database}\". Use letters, numbers and underscores.";
    }

    /** A URL on $domain using APP_URL's scheme and port. */
    public function url(string $domain, string $path = ''): string
    {
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$domain.($port ? ':'.$port : '').$path;
    }
}

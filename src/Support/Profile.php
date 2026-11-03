<?php

namespace Best\Blueprint\Support;

enum Profile: string
{
    case Basic = 'basic';
    case Modular = 'modular';
    case Saas = 'saas';
    case TenantSaas = 'tenant-saas';

    public function usesModules(): bool
    {
        return $this !== self::Basic;
    }

    public function usesFeatures(): bool
    {
        return $this === self::Saas || $this === self::TenantSaas;
    }

    public function usesSettings(): bool
    {
        return $this->usesFeatures();
    }

    public function usesTenancy(): bool
    {
        return $this === self::TenantSaas;
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return array_keys(array_filter([
            'Modules' => $this->usesModules(),
            'Features' => $this->usesFeatures(),
            'Settings' => $this->usesSettings(),
            'Tenancy' => $this->usesTenancy(),
        ]));
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic (standard Laravel app following the blueprint conventions)',
            self::Modular => 'Modular (business modules in app/Modules)',
            self::Saas => 'SaaS (modular, plus Features and Settings)',
            self::TenantSaas => 'Tenant SaaS (SaaS, plus multi-tenancy with a database per tenant)',
        };
    }

    public static function current(): ?self
    {
        $value = config('blueprint.profile');

        return is_string($value) ? self::tryFrom($value) : null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

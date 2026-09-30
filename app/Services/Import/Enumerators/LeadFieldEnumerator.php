<?php

namespace App\Services\Import\Enumerators;

enum LeadFieldEnumerator: string
{
    case ExternalId = 'external_id';
    case CreatedAt = 'created_at';
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case Phone = 'phone';
    case Email = 'email';
    case City = 'city';
    case Source = 'source';
    case UtmCampaign = 'utm_campaign';
    case Product = 'product';
    case BudgetUah = 'budget_uah';
    case Status = 'status';
    case Manager = 'manager';
    case Comment = 'comment';
    case NextContactAt = 'next_contact_at';

    public function isRequired(): bool
    {
        return $this === self::ExternalId;
    }

    public function isDateTime(): bool
    {
        return match ($this) {
            self::CreatedAt, self::NextContactAt => true,
            default => false,
        };
    }

    public function maxLength(): ?int
    {
        return match ($this) {
            self::Comment => 65535,
            self::BudgetUah => null,
            self::CreatedAt, self::NextContactAt => 19,
            default => 255,
        };
    }

    /**
     * @return list<self>
     */
    public static function importable(): array
    {
        return self::cases();
    }
}

<?php

namespace App\Services\Lead;

use App\Models\Lead;
use App\Services\Lead\Contracts\LeadServiceInterface;

class LeadService implements LeadServiceInterface
{
    public function delete(Lead $lead): bool
    {
        return (bool) $lead->delete();
    }

    public function deleteAll(): int
    {
        return (int) Lead::query()->delete();
    }
}

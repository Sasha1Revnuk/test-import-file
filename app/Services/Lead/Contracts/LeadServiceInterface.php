<?php

namespace App\Services\Lead\Contracts;

use App\Models\Lead;

interface LeadServiceInterface
{
    public function delete(Lead $lead): bool;

    public function deleteAll(): int;
}

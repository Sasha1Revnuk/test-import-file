<?php

namespace App\Services\Import\Enumerators;

enum ImportStatusEnumerator: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}

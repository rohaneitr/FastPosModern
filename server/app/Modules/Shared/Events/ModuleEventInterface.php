<?php

namespace App\Modules\Shared\Events;

interface ModuleEventInterface
{
    public function getEventName(): string;
    public function getPayload(): array;
}

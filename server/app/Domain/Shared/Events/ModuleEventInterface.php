<?php

namespace App\Domain\Shared\Events;

interface ModuleEventInterface
{
    public function getEventName(): string;
    public function getPayload(): array;
}

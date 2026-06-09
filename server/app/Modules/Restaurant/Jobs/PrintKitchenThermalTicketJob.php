<?php

namespace App\Modules\Restaurant\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintKitchenThermalTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $businessId;
    public string $ticketNumber;
    public array $items;

    public int $tries   = 3;
    public int $timeout = 10;

    public function __construct(int $businessId, string $ticketNumber, array $items)
    {
        $this->businessId   = $businessId;
        $this->ticketNumber = $ticketNumber;
        $this->items        = $items;
        $this->onQueue('kitchen_print');
    }

    public function handle(): void
    {
        // ESC/POS packet composition (simulation)
        // In production: integrate with Mike42\Escpos library or TCP socket to the receipt printer.
        $esc      = "\x1B";
        $packet   = $esc . "@";               // Initialize printer
        $packet  .= $esc . "!" . "\x38";      // Double-height, double-width header
        $packet  .= "** KOT: {$this->ticketNumber} **\n\n";
        $packet  .= $esc . "!" . "\x00";      // Normal font

        foreach ($this->items as $item) {
            $name     = $item['name']      ?? 'Unknown Item';
            $qty      = $item['qty']       ?? 1;
            $modifier = $item['modifier']  ?? '';

            $packet .= "{$qty}x {$name}";
            if (!empty($modifier)) {
                $packet .= " [{$modifier}]";
            }
            $packet .= "\n";
        }

        $packet .= "\n" . $esc . "d" . "\x04"; // Feed and cut

        // Log for testing/staging visibility
        Log::channel('kitchen')->info("KOT Thermal Packet queued for Business #{$this->businessId}", [
            'ticket'  => $this->ticketNumber,
            'payload' => $packet
        ]);
    }
}

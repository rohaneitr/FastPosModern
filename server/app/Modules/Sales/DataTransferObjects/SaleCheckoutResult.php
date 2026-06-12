<?php

namespace App\Modules\Sales\DataTransferObjects;

/**
 * SaleCheckoutResult — Immutable result object returned by ProcessSaleService.
 *
 * The Controller reads this and builds the HTTP response.
 * This eliminates the anti-pattern of returning raw arrays from service classes.
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
final class SaleCheckoutResult
{
    public function __construct(
        public readonly int    $transactionId,
        public readonly string $invoiceNo,
        public readonly string $subtotal,
        public readonly string $discount,
        public readonly string $tax,
        public readonly string $finalTotal,
    ) {}
}

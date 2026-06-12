<?php

namespace App\Modules\Sales\DataTransferObjects;

/**
 * SaleCheckoutDTO — Immutable Data Transfer Object
 *
 * Carries validated, sanitized sale checkout data from the Controller
 * into the ProcessSaleService. All properties are readonly (PHP 8.1+).
 *
 * WHY A DTO?
 * Passing a raw Illuminate\Http\Request object into a Service class tightly
 * couples the service to the HTTP layer. This DTO decouples them, enabling:
 *   1. Unit testing of ProcessSaleService without a real HTTP request
 *   2. Reuse of the service from CLI commands (seeding, smoke tests)
 *   3. Offline sync — payload can be deserialized into this DTO
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
final class SaleCheckoutDTO
{
    /**
     * @param int         $businessId       Authenticated user's business ID
     * @param int         $userId           ID of the user performing the checkout
     * @param int         $locationId       POS location/store ID
     * @param array       $items            Cart items (product_id, quantity, price, etc.)
     * @param float       $taxRate          Global tax rate (0–100)
     * @param string|null $discountType     'fixed' | 'percentage' | null
     * @param float       $discountAmount   Discount value (default 0)
     * @param int|null    $contactId        Customer/contact ID (required for credit sales)
     * @param float|null  $amountPaid       Amount tendered (null = full payment)
     * @param string      $paymentMethod    'cash', 'card', 'bank_transfer', 'bkash', 'store_credit', 'advance'
     * @param string      $documentType     'Invoice' | 'Quotation' | 'ProformaInvoice'
     * @param bool        $isPosting        True = final invoice; False = draft/quotation
     * @param string|null $idempotencyKey   X-Idempotency-Key header value
     * @param int|null    $convertQuotationId  Quotation transaction ID being converted
     * @param int|null    $cashRegisterId   Active register session ID
     * @param bool        $isOfflineSync    True when pushed from offline mobile sync
     * @param string|null $prescriptionDoctor
     * @param string|null $prescriptionPatient
     * @param string|null $prescriptionFile
     * @param string|null $prescriptionNotes
     */
    public function __construct(
        public readonly int     $businessId,
        public readonly int     $userId,
        public readonly int     $locationId,
        public readonly array   $items,
        public readonly float   $taxRate,
        public readonly ?string $discountType,
        public readonly float   $discountAmount,
        public readonly ?int    $contactId,
        public readonly ?float  $amountPaid,
        public readonly string  $paymentMethod,
        public readonly string  $documentType,
        public readonly bool    $isPosting,
        public readonly ?string $idempotencyKey,
        public readonly ?int    $convertQuotationId,
        public readonly ?int    $cashRegisterId,
        public readonly bool    $isOfflineSync,
        public readonly ?string $prescriptionDoctor,
        public readonly ?string $prescriptionPatient,
        public readonly ?string $prescriptionFile,
        public readonly ?string $prescriptionNotes,
        public readonly ?string $transactionDate = null,  // For offline sync: honor device timestamp
        public readonly ?string $invoiceNoOverride = null, // For offline sync: use device-generated invoice
    ) {}

    /**
     * Factory: build from a validated Illuminate Request array.
     * The Controller calls this after ->validate() passes.
     *
     * @param array  $validated     Output of $request->validate(...)
     * @param int    $businessId    From $request->user()->business_id
     * @param int    $userId        From $request->user()->id
     * @param int|null $cashRegisterId Active register session ID
     * @param bool   $isOfflineSync
     */
    public static function fromValidated(
        array   $validated,
        int     $businessId,
        int     $userId,
        ?int    $cashRegisterId = null,
        bool    $isOfflineSync = false,
    ): self {
        $documentType = $validated['document_type']
            ?? ($validated['save_as_quotation'] ? 'Quotation' : 'Invoice');

        return new self(
            businessId:           $businessId,
            userId:               $userId,
            locationId:           $validated['location_id'],
            items:                $validated['items'],
            taxRate:              (float) $validated['tax_rate'],
            discountType:         $validated['discount_type'] ?? null,
            discountAmount:       (float) ($validated['discount_amount'] ?? 0),
            contactId:            $validated['contact_id'] ?? null,
            amountPaid:           isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
            paymentMethod:        $validated['payment_method'],
            documentType:         $documentType,
            isPosting:            $documentType === 'Invoice',
            idempotencyKey:       null, // populated in Controller from header
            convertQuotationId:   $validated['convert_quotation_id'] ?? null,
            cashRegisterId:       $cashRegisterId,
            isOfflineSync:        $isOfflineSync,
            prescriptionDoctor:   $validated['prescription_doctor'] ?? null,
            prescriptionPatient:  $validated['prescription_patient'] ?? null,
            prescriptionFile:     $validated['prescription_file'] ?? null,
            prescriptionNotes:    $validated['prescription_notes'] ?? null,
        );
    }
}

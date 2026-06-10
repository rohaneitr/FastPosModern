<?php

namespace App\Modules\CRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via middleware
    }

    public function rules(): array
    {
        return [
            // Accept either user_ids (for staff) or customer_ids (for contacts)
            'user_ids' => ['array', 'nullable'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'customer_ids' => ['array', 'nullable'],
            'customer_ids.*' => ['integer', 'exists:contacts,id'],
            
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ];
    }
}

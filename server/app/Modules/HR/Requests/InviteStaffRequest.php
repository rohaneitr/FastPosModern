<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via middleware
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                // Ensure the email isn't already registered for this business
                Rule::unique('users')->where(function ($query) {
                    return $query->where('business_id', $this->user()->business_id);
                }),
            ],
            'role' => ['required', 'string', 'in:Cashier,InventoryManager,Accountant,BusinessAdmin'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered as a staff member in your business.',
        ];
    }
}

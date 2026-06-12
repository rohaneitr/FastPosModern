<?php

namespace App\Modules\SuperAdmin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('SuperAdmin');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:Open,In Progress,Resolved,Closed',
        ];
    }
}

<?php

namespace App\Modules\SuperAdmin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('SuperAdmin');
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|min:2|max:5000',
        ];
    }
}

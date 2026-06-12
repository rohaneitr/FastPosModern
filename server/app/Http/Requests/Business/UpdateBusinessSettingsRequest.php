<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->hasRole('BusinessAdmin');
    }

    public function rules()
    {
        return [
            'pos_enforce_device_lock' => 'nullable|boolean',
            'pos_enforce_strict_cash_control' => 'nullable|boolean',
        ];
    }
}

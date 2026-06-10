<?php

namespace App\Modules\SuperAdmin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('SuperAdmin');
    }

    public function rules(): array
    {
        return [
            'saas_name' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|timezone',
            'default_currency_symbol' => 'nullable|string|max:10',
            'smtp_sender_address' => 'nullable|email|max:255',
            // Allow files or strings for images depending on if they are uploading or not changing
            'saas_logo' => 'nullable|file|image|mimes:jpeg,png,jpg,svg|max:2048',
            'favicon' => 'nullable|file|image|mimes:ico,png|max:1024',
            
            // SMTP settings (optional since we're using a single endpoint for all settings based on the UI)
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Controlled by middleware
    }

    public function rules()
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB limit
        ];
    }
}

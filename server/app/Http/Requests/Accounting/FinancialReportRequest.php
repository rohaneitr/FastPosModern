<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class FinancialReportRequest extends FormRequest
{
    public function authorize()
    {
        return true; // We use role middleware
    }

    public function rules()
    {
        return [
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'as_of_date' => 'nullable|date|date_format:Y-m-d',
        ];
    }
}

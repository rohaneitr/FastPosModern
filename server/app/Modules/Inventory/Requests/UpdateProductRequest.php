<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $productId = $this->route('product') ? $this->route('product')->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('business_id', auth()->user()->business_id);
                })->ignore($productId)
            ],
            'barcode_type' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'purchase_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image_path' => ['nullable', 'string']
        ];
    }
}

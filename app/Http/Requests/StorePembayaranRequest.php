<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kewajiban_pajak_id' => ['required', 'exists:kewajiban_pajak,id'],
            // Nominal tidak boleh nol (atau negatif).
            'nominal' => ['required', 'numeric', 'gt:0'],
            // Tanggal tidak boleh di masa depan.
            'tanggal_bayar' => ['required', 'date', 'before_or_equal:today'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nominal.gt' => 'Nominal pembayaran harus lebih besar dari nol.',
            'tanggal_bayar.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWajibPajakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (?string $v) => $v === null ? null : preg_replace('/[\s.\-]/', '', $v);
        $jenis = $this->input('jenis') ?? $this->route('wajibPajak')?->jenis;

        $merged = [];
        if ($this->has('nik'))  $merged['nik']  = $jenis === 'BADAN'    ? null : $clean($this->input('nik'));
        if ($this->has('nib'))  $merged['nib']  = $jenis === 'INDIVIDU' ? null : $clean($this->input('nib'));
        if ($this->has('npwp')) $merged['npwp'] = $clean($this->input('npwp'));

        if ($merged) $this->merge($merged);
    }

    public function rules(): array
    {
        $id = $this->route('wajibPajak')?->id ?? $this->route('wajibPajak');
        $jenis = $this->input('jenis') ?? $this->route('wajibPajak')?->jenis;

        $nikRules = $jenis === 'BADAN'
            ? ['sometimes', 'nullable']
            : ['sometimes', 'nullable', 'digits:16', Rule::unique('wajib_pajak', 'nik')->ignore($id)];

        $nibRules = $jenis === 'INDIVIDU'
            ? ['sometimes', 'nullable']
            : ['sometimes', 'nullable', 'digits_between:9,30', Rule::unique('wajib_pajak', 'nib')->ignore($id)];

        return [
            'jenis'        => ['sometimes', Rule::in(['INDIVIDU', 'BADAN'])],
            'nama'         => ['sometimes', 'string', 'max:255'],
            'nik'          => $nikRules,
            'npwp'         => ['sometimes', 'nullable', 'regex:/^\d{15,16}$/', Rule::unique('wajib_pajak', 'npwp')->ignore($id)],
            'nib'          => $nibRules,
            'email'        => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('wajib_pajak', 'email')->ignore($id)],
            'telepon'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'alamat'       => ['sometimes', 'nullable', 'string'],
            'status_aktif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.digits'         => 'NIK harus terdiri dari 16 digit angka.',
            'npwp.regex'         => 'Format NPWP tidak valid (15 atau 16 digit angka).',
            'nib.digits_between' => 'NIB harus berupa angka (9-30 digit).',
            'email.unique'       => 'Email sudah digunakan.',
        ];
    }
}

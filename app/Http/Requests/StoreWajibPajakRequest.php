<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreWajibPajakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi NIK/NPWP/NIB: buang spasi, titik, dan strip sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        // Normalisasi format (buang spasi/titik/strip). NIK pada BADAN dan NIB
        // pada INDIVIDU diabaikan (di-null-kan) sehingga tidak tersimpan,
        // tanpa memunculkan error validasi.
        $clean = fn (?string $v) => $v === null ? null : preg_replace('/[\s.\-]/', '', $v);
        $jenis = $this->input('jenis');

        $this->merge([
            'nik'  => $jenis === 'BADAN'    ? null : $clean($this->input('nik')),
            'nib'  => $jenis === 'INDIVIDU' ? null : $clean($this->input('nib')),
            'npwp' => $clean($this->input('npwp')),
        ]);
    }

    public function rules(): array
    {
        $jenis = $this->input('jenis');

        // NIK wajib untuk INDIVIDU; untuk BADAN diabaikan (tidak divalidasi/tersimpan).
        $nikRules = $jenis === 'INDIVIDU'
            ? ['required', 'digits:16', Rule::unique('wajib_pajak', 'nik')]
            : ['nullable'];

        // NIB wajib untuk BADAN; untuk INDIVIDU diabaikan.
        $nibRules = $jenis === 'BADAN'
            ? ['required', 'digits_between:9,30', Rule::unique('wajib_pajak', 'nib')]
            : ['nullable'];

        return [
            'jenis'        => ['required', Rule::in(['INDIVIDU', 'BADAN'])],
            'nama'         => ['required', 'string', 'max:255'],
            'nik'          => $nikRules,
            'npwp'         => ['required', 'regex:/^\d{15,16}$/', Rule::unique('wajib_pajak', 'npwp')],
            'nib'          => $nibRules,
            'email'        => ['nullable', 'email', 'max:255', Rule::unique('wajib_pajak', 'email')],
            'telepon'      => ['nullable', 'string', 'max:20'],
            'alamat'       => ['nullable', 'string'],
            'status_aktif' => ['nullable', 'boolean'],
            'username'     => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'password'     => ['required', 'string', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required'       => 'NIK wajib diisi untuk wajib pajak individu.',
            'nik.digits'         => 'NIK harus terdiri dari 16 digit angka.',
            'npwp.required'      => 'NPWP wajib diisi.',
            'npwp.regex'         => 'Format NPWP tidak valid (15 atau 16 digit angka).',
            'nib.required'       => 'NIB wajib diisi untuk badan usaha.',
            'nib.digits_between' => 'NIB harus berupa angka (9-30 digit).',
            'email.unique'       => 'Email sudah digunakan.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan.',
            'password.required'  => 'Password wajib diisi.',
        ];
    }
}

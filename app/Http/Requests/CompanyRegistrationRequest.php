<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompanyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'company_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255|unique:company_registrations,contact_email|unique:users,email',
            'contact_phone' => 'required|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Nama perusahaan wajib diisi',
            'company_name.max' => 'Nama perusahaan maksimal 255 karakter',
            'contact_email.required' => 'Email kontak wajib diisi',
            'contact_email.email' => 'Format email tidak valid',
            'contact_email.unique' => 'Email sudah terdaftar',
            'contact_phone.required' => 'Nomor telepon wajib diisi',
            'contact_phone.max' => 'Nomor telepon maksimal 20 karakter',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}

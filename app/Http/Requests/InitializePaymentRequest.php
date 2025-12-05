<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitializePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by getAccountFromRequest() in controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'email' => 'required|email|max:255',
            'transactionId' => 'required|string|max:255',
            'contactId' => 'nullable|string|max:255',
            'orderId' => 'nullable|string|max:255',
            'subscriptionId' => 'nullable|string|max:255',
            'currency' => 'nullable|string|in:TRY,USD,EUR',
            'mode' => 'nullable|string|in:payment,subscription',
            'user_name' => 'nullable|string|max:255',
            'user_phone' => 'nullable|string|max:20',
            'user_ip' => 'nullable|ip',
            'user_address' => 'nullable|string|max:500',
            'installment_count' => 'nullable|integer|min:0|max:12',
            'store_card' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be at least 0.01',
            'email.required' => 'Customer email is required',
            'email.email' => 'Please provide a valid email address',
            'transactionId.required' => 'Transaction ID is required',
            'currency.in' => 'Currency must be one of: TRY, USD, EUR',
            'mode.in' => 'Payment mode must be either payment or subscription',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'transactionId' => 'transaction ID',
            'contactId' => 'contact ID',
            'orderId' => 'order ID',
            'subscriptionId' => 'subscription ID',
            'user_name' => 'customer name',
            'user_phone' => 'customer phone',
            'user_ip' => 'customer IP address',
            'installment_count' => 'installment count',
        ];
    }
}

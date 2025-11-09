<?php

namespace App\Http\Requests;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PlayerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $userType = $user ? UserType::from((int) $user->type) : null;

        return [
            'user_name' => ['required', 'string', 'unique:users,user_name'],
            'name' => ['nullable', 'string'],
            'phone' => ['nullable', 'regex:/^[0-9]+$/', 'unique:users,phone'],
            'password' => 'required|min:6',
            'amount' => 'nullable|numeric|min:0',
            'agent_id' => $userType === UserType::Owner
                ? ['required', 'exists:users,id']
                : ['nullable'],
        ];
    }
}

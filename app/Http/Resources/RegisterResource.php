<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = [
            'id' => $this->id,
            'name' => $this->name,
            'user_name' => $this->user_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'balance' => $this->balance,
            'max_score' => $this->max_score,
            'status' => $this->status,
            'is_changed_password' => $this->is_changed_password,
            'agent_id' => $this->agent_id,
            'payment_type_id' => $this->payment_type_id,
            'agent_logo' => $this->agent_logo,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'payment_type_id' => $this->payment_type_id,
        ];

        return [
            'user' => $user,
            'token' => $this->createToken($this->user_name)->plainTextToken,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];
        $user = $this->resource instanceof User ? $this->resource : ($payload['user'] ?? null);

        return [
            'user' => $this->when($user instanceof User, fn (): array => $this->user($user)),
            'access_token' => $this->when(isset($payload['access_token']), $payload['access_token'] ?? null),
            'token_type' => $this->when(isset($payload['token_type']), $payload['token_type'] ?? null),
            'verification' => $this->when(isset($payload['verification']), $payload['verification'] ?? null),
            'sent' => $this->when(array_key_exists('sent', $payload), $payload['sent'] ?? false),
        ];
    }

    /**
     * Transform the authenticated user into API-safe data.
     *
     * @return array<string, mixed>
     */
    private function user(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'birth_date' => $user->birth_date?->toDateString(),
            'is_approved' => (bool) $user->is_approved,
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
            'phone_verified_at' => $user->phone_verified_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
            
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResetPasswordTokenResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'reset_token' => $this['reset_token'],
            'expires_in' => $this['expires_in'],
        ];
    }
}

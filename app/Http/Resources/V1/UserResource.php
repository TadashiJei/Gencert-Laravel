<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user->id === $this->id, $this->email),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'certificates_count' => $this->when(isset($this->certificates_count), $this->certificates_count),
            'templates_count' => $this->when(isset($this->templates_count), $this->templates_count),
        ];
    }
}

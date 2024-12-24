<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'is_public' => $this->is_public,
            'content' => $this->when($request->user->id === $this->user_id, $this->content),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'certificates_count' => $this->when(isset($this->certificates_count), $this->certificates_count),
        ];
    }
}

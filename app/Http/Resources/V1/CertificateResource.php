<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'user_id' => $this->user_id,
            'recipient_name' => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'status' => $this->status,
            'format' => $this->format,
            'file_path' => $this->when($request->user->id === $this->user_id, $this->file_path),
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'template' => new TemplateResource($this->whenLoaded('template')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

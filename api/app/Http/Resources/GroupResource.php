<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'owner' => $this->relationLoaded('owner') ? $this->owner : null,
            'group_name' => $this->group_name,
            'description' => $this->description,
            'status' => $this->status,
            'members_count' => $this->relationLoaded('members') ? $this->members->count() : 0,
            'members' => $this->relationLoaded('members') ? $this->members : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

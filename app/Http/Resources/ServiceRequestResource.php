<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Užklausos puslapis. Adresas – privatus: rodomas tik klientui ir išrinktam teikėjui (withPrivateDetails()).
 *
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    private bool $withPrivateDetails = false;

    public function withPrivateDetails(bool $show = true): static
    {
        $this->withPrivateDetails = $show;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new ServiceRequestSummaryResource($this->resource))->toArray($request),
            'description' => $this->description,
            'address' => $this->when($this->withPrivateDetails, $this->address),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'views_count' => $this->views_count,
        ];
    }
}

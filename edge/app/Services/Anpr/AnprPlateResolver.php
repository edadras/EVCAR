<?php

namespace App\Services\Anpr;

use App\Models\AnprDetection;
use App\Models\CachedUser;
use App\Models\CachedVehicle;

class AnprPlateResolver
{
    /** @return array{user:?CachedUser,vehicle:?CachedVehicle} */
    public function resolve(AnprDetection $detection): array
    {
        $vehicle = null;

        $parts = array_filter([
            'plate_two' => $detection->plate_two,
            'plate_letter' => $detection->plate_letter,
            'plate_three' => $detection->plate_three,
            'plate_province' => $detection->plate_province,
        ], fn ($v) => $v !== null && $v !== '');

        if (count($parts) === 4) {
            $vehicle = CachedVehicle::query()->where($parts)->first();
        }

        if (! $vehicle && $detection->plate_normalized) {
            $vehicle = CachedVehicle::where('plate_normalized', $detection->plate_normalized)->first();
        }

        $user = null;
        if ($vehicle?->cached_user_uuid) {
            $user = CachedUser::where('uuid', $vehicle->cached_user_uuid)->first();
        }

        return ['user' => $user, 'vehicle' => $vehicle];
    }
}

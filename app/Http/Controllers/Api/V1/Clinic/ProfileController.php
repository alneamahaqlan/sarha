<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ClinicResource as ClinicApiResource;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(): ClinicApiResource
    {
        $clinic = auth('clinic')->user()->load('city:id,name');

        return new ClinicApiResource($clinic);
    }

    public function update(UpdateProfileRequest $request): ClinicApiResource
    {
        $clinic = auth('clinic')->user();
        $data = $request->validated();

        // Mirror ClinicProfile page: empty password → keep existing; otherwise bcrypt.
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $clinic->update($data);

        return new ClinicApiResource($clinic->fresh()->load('city:id,name'));
    }
}

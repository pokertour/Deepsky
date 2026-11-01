<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateObjectDescriptionRequest;
use App\Models\CelestialObject;
use App\Services\Astronomy\ObjectDescriptionGenerator;
use DomainException;
use Illuminate\Http\JsonResponse;

class GenerateObjectDescriptionController extends Controller
{
    public function __invoke(GenerateObjectDescriptionRequest $request, ObjectDescriptionGenerator $generator): JsonResponse
    {
        $object = CelestialObject::findOrFail($request->validated('celestial_object_id'));

        try {
            return response()->json(['description' => $generator->generate($object), 'object_id' => $object->id]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}

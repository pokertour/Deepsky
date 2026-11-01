<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TranslateAstrophotoRequest;
use App\Services\Translation\AstrophotoTranslator;
use DomainException;
use Illuminate\Http\JsonResponse;

class TranslateAstrophotoController extends Controller
{
    public function __invoke(TranslateAstrophotoRequest $request, AstrophotoTranslator $translator): JsonResponse
    {
        try {
            return response()->json(['translations' => $translator->toEnglish($request->validated())]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}

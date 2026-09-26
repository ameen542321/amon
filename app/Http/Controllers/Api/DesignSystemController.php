<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignSystemController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['nullable', Rule::in(['dark', 'light'])],
        ]);
        $contract = config('design_system');
        $themes = isset($validated['theme'])
            ? [$validated['theme'] => $contract['themes'][$validated['theme']]]
            : $contract['themes'];
        $data = [
            'version' => (string) $contract['version'],
            'default_theme' => $contract['default_theme'],
            'supported_themes' => $contract['supported_themes'],
            'direction' => $contract['direction'],
            'locale' => $contract['locale'],
            'font' => $contract['font'],
            'brand' => $contract['brand'],
            'themes' => $themes,
            'status_dots' => $contract['status_dots'],
            'capabilities' => [
                'preview' => true,
                'publish' => false,
                'rollback' => false,
            ],
        ];
        $checksum = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return ApiResponse::success($data, ['contract_checksum' => $checksum])
            ->header('ETag', '"'.$checksum.'"');
    }
}

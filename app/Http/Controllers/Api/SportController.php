<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;

class SportController extends Controller
{
    public function index(): JsonResponse
    {
        $sports = Sport::query()
            ->where('is_active', true)
            ->where('slug', 'mah-jongg')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon_key']);

        return response()->json([
            'data' => $sports,
        ]);
    }
}

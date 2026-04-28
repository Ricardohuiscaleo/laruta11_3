<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDeliveryConfigRequest;
use App\Models\DeliveryConfig;
use Illuminate\Http\JsonResponse;

class DeliveryConfigController extends Controller
{
    /**
     * GET /api/v1/admin/delivery-config
     * Returns all delivery_config records.
     */
    public function index(): JsonResponse
    {
        $items = DeliveryConfig::all(['config_key', 'config_value', 'description', 'updated_by', 'updated_at']);

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    /**
     * PUT /api/v1/admin/delivery-config
     * Updates delivery config values in bulk.
     */
    public function update(UpdateDeliveryConfigRequest $request): JsonResponse
    {
        $userName = $request->user()->nombre ?? 'admin';

        foreach ($request->validated()['items'] as $item) {
            DeliveryConfig::where('config_key', $item['config_key'])
                ->update([
                    'config_value' => $item['config_value'],
                    'updated_by' => $userName,
                ]);
        }

        $items = DeliveryConfig::all(['config_key', 'config_value', 'description', 'updated_by', 'updated_at']);

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }
}

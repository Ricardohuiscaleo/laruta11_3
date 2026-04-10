<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePersonalRequest;
use App\Models\Personal;
use Illuminate\Http\JsonResponse;

class PersonalController extends Controller
{
    public function index(): JsonResponse
    {
        $personal = Personal::with('usuario')->get();

        return response()->json(['success' => true, 'data' => $personal]);
    }

    public function store(StorePersonalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['rol'] = implode(',', $data['rol']);

        $personal = Personal::create($data);

        return response()->json(['success' => true, 'data' => $personal], 201);
    }

    public function update(StorePersonalRequest $request, int $id): JsonResponse
    {
        $personal = Personal::findOrFail($id);
        $data = $request->validated();
        $data['rol'] = implode(',', $data['rol']);

        $personal->update($data);

        return response()->json(['success' => true, 'data' => $personal]);
    }

    public function toggle(int $id): JsonResponse
    {
        $personal = Personal::findOrFail($id);
        $personal->update(['activo' => !$personal->activo]);

        return response()->json(['success' => true, 'data' => $personal]);
    }
}

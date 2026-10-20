<?php

namespace App\Http\Controllers;

use App\Models\ShippingService;
use Illuminate\Http\Request;

class ShippingServiceController extends Controller
{
    public function index(Request $req)
    {
        $req->validate([
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $services = ShippingService::query()
            ->when($req->user()?->role === 'customer', fn ($q) => $q->where('is_active', true))
            ->paginate($req->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Shipping services retrieved successfully.',
            'data' => $services->items(),
            'meta' => [
                'pagination' => [
                    'page' => $services->currentPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                    'last_page' => $services->lastPage(),
                ],
            ],
        ]);
    }

    public function store(Request $req)
    {
        $v = $req->validate([
            'service_name' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $services = ShippingService::create($v);

        return response()->json([
            'success' => true,
            'message' => 'Shipping service created successfully.',
            'data' => $services,
        ], 201);
    }

    public function show(Request $req, string $id)
    {
        $services = ShippingService::find($id);

        if (! $services || ($req->user()->role === 'customer' && ! $services->is_active)) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping service not found.',
                'errors' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service retrieved successfully.',
            'data' => $services,
        ]);
    }

    public function update(Request $req, string $id)
    {
        $services = ShippingService::find($id);

        if (! $services) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping service not found.',
                'errors' => [],
            ], 404);
        }

        $v = $req->validate([
            'service_name' => 'sometimes|string',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $services->update($v);

        return response()->json([
            'success' => true,
            'message' => 'Shipping service updated successfully.',
            'data' => $services,
        ]);
    }
}

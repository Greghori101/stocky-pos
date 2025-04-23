<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('reservations');

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('price', 'like', "%{$search}%")
                      ->orWhere('unit_per_minute', 'like', "%{$search}%");
            });
        }

        // Sorting
        $orderBy = $request->query('order_by', 'created_at'); // default to 'id'
        $orderDirection = $request->query('order_direction', 'desc'); // default to 'desc'
        $query->orderBy($orderBy, $orderDirection);

        // Pagination
        $perPage = $request->query('per_page', 10);
        $services = $query->paginate($perPage);

        return response()->json($services);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'price'           => 'required|numeric',
            'unit_per_minute' => 'required|numeric',
        ]);

        $service = Service::create($validated);

        return response()->json($service, 201);
    }

    public function show(Service $service)
    {
        return response()->json($service->load('reservations'));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'price'           => 'sometimes|numeric',
            'unit_per_minute' => 'sometimes|numeric',
        ]);

        $service->update($validated);

        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Service;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::with(['post', 'service', 'products', 'reservationItem']);

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('total_price', 'like', "%{$search}%")
                    ->orWhere('started_at', 'like', "%{$search}%")
                    ->orWhere('ended_at', 'like', "%{$search}%");
            });
        }

        // Sorting
        $orderBy = $request->query('order_by', 'created_at'); // default to 'id'
        $orderDirection = $request->query('order_direction', 'desc'); // default to 'desc'
        $query->orderBy($orderBy, $orderDirection);

        // Pagination
        $perPage = $request->query('per_page', 10);
        $reservations = $query->paginate($perPage);

        return response()->json($reservations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'started_at'   => 'required|date',
            'ended_at'     => 'required|date',
            'service_id'   => 'required|exists:services,id',
            'post_id'      => 'required|exists:posts,id',
            'products'     => 'required|array',  // Ensure products is an array
            'products.*.id' => 'required|exists:products,id',  // Ensure each product exists
            'products.*.price' => 'required|numeric', // Product price is required
            'products.*.qte' => 'required|integer|min:1', // Quantity must be at least 1
        ]);

        // Calculate the total price based on products and quantity
        $totalPrice = 0;
        foreach ($request->products as $product) {
            $totalPrice += $product['price'] * $product['qte'];
        }

        // Calculate the duration in minutes for the service
        $service = Service::findOrFail($request->service_id);
        $startedAt = Carbon::parse($request->started_at);
        $endedAt = Carbon::parse($request->ended_at);
        $durationInMinutes = $startedAt->diffInMinutes($endedAt);
        $servicePrice = $service->price *  $durationInMinutes / $service->unit_per_minute;

        // Calculate the final total price
        $totalPrice += $servicePrice;

        // Create the reservation
        $reservation = Reservation::create([
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'total_price' => $totalPrice,
            'service_id' => $request->service_id,
            'post_id' => $request->post_id,
        ]);

        // Attach products to the reservation
        foreach ($request->products as $product) {
            $reservation->products()->attach($product['id'], [
                'price' => $product['price'],
                'qte' => $product['qte'],
            ]);
        }

        return response()->json($reservation->load(['post', 'service', 'products', 'reservationItem']), 201);
    }

    public function show(Reservation $reservation)
    {
        return response()->json($reservation->load(['post', 'service', 'products', 'reservationItem']));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'started_at'   => 'sometimes|date',
            'ended_at'     => 'sometimes|date',
            'service_id'   => 'sometimes|exists:services,id',
            'post_id'      => 'sometimes|exists:posts,id',
            'products'     => 'sometimes|array',  // Make products optional in update
            'products.*.id' => 'sometimes|exists:products,id',  // Ensure each product exists
            'products.*.price' => 'sometimes|numeric', // Product price is required
            'products.*.qte' => 'sometimes|integer|min:1', // Quantity must be at least 1
        ]);

        // Update the reservation details
        $reservation->update($validated);

        // Recalculate the total price based on products and quantity
        $totalPrice = 0;
        foreach ($request->products ?? $reservation->products as $product) {
            $totalPrice += $product['price'] * $product['qte'];
        }

        // Calculate the duration in minutes for the service
        $service = Service::findOrFail($request->service_id ?? $reservation->service_id);
        $startedAt = Carbon::parse($request->started_at ?? $reservation->started_at);
        $endedAt = Carbon::parse($request->ended_at ?? $reservation->ended_at);
        $durationInMinutes = $startedAt->diffInMinutes($endedAt);
        $servicePrice = $service->unit_per_minute * $durationInMinutes;

        // Calculate the final total price
        $totalPrice += $servicePrice;

        // Update the reservation total price
        $reservation->update(['total_price' => $totalPrice]);

        // If products are provided, update the relationship
        if ($request->has('products')) {
            $reservation->products()->sync($request->products);
        }

        return response()->json($reservation->load(['post', 'service', 'products', 'reservationItem']));
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();

        return response()->json(null, 204);
    }
}

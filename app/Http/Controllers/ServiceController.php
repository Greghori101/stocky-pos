<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Intervention\Image\ImageManagerStatic as Image;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Service::class);
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
        $this->authorizeForUser($request->user('api'), 'create', Service::class);
        $validated = $request->validate([
            'price'           => 'required|numeric',
            'unit_per_minute' => 'required|numeric',
        ]);

        $service = Service::create($validated);
        if ($request->hasFile('image')) {

            $image = $request->file('image');
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(200, 200);
            $image_resize->save(public_path('/images/services/' . $filename));
        } else {
            $filename = 'no-image.png';
        }


        $service->image = $filename;
        $service->save();
        return response()->json($service, 201);
    }

    public function show(Request $request, Service $service)
    {
        $this->authorizeForUser($request->user('api'), 'view', Service::class);
        return response()->json($service->load('reservations'));
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeForUser($request->user('api'), 'update', Service::class);
        $validated = $request->validate([
            'price'           => 'sometimes|numeric',
            'unit_per_minute' => 'sometimes|numeric',
        ]);

        $service->update($validated);

        $currentImage = $service->image;
        if ($currentImage && $request->image != $currentImage) {
            $image = $request->file('image');
            $path = public_path() . '/images/services';
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(200, 200);
            $image_resize->save(public_path('/images/services/' . $filename));

            $BrandImage = $path . '/' . $currentImage;
            if (file_exists($BrandImage)) {
                if ($currentImage != 'no-image.png') {
                    @unlink($BrandImage);
                }
            }
        } else if (!$currentImage && $request->image != 'null') {
            $image = $request->file('image');
            $path = public_path() . '/images/services';
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(200, 200);
            $image_resize->save(public_path('/images/services/' . $filename));
        } else {
            $filename = $currentImage ? $currentImage : 'no-image.png';
        }

        $service->image = $filename;
        $service->save();
        return response()->json($service);
    }

    public function destroy(Request $request, Service $service)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Service::class);
        $service->delete();

        return response()->json(null, 204);
    }


    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Service::class);

        \DB::transaction(function () use ($request) {
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $service_id) {

                $Service = Service::findOrFail($service_id);

                $pathIMG = public_path() . '/images/services/' . $Service->image;
                if (file_exists($pathIMG)) {
                    if ($Service->image != 'no-image.png') {
                        @unlink($pathIMG);
                    }
                }

                $Service->delete();
            }
        }, 10);

        return response()->json(['success' => true]);
    }
}

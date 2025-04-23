<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with('reservations');


        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }


        $orderBy = $request->query('order_by', 'created_at');
        $direction = $request->query('order_direction', 'desc');
        $query->orderBy($orderBy, $direction);


        $perPage = $request->query('per_page', 10);
        $posts = $query->paginate($perPage);

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $post = Post::create($validated);

        return response()->json($post, 201);
    }

    public function show(Post $post)
    {
        return response()->json($post->load('reservations'));
    }

    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return response()->json(null, 204);
    }
}

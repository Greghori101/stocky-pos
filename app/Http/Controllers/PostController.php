<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Post::class);

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
        $this->authorizeForUser($request->user('api'), 'create', Post::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $post = Post::create($validated);

        return response()->json($post, 201);
    }

    public function show(Request $request,Post $post)
    {
        $this->authorizeForUser($request->user('api'), 'view', Post::class);
        return response()->json($post->load('reservations'));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorizeForUser($request->user('api'), 'update', Post::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    public function destroy(Request $request,Post $post)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Post::class);
        $post->delete();

        return response()->json(null, 204);
    }
}

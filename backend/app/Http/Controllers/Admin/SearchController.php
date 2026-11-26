<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, AdminSearch $search)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(AdminSearch::TYPES))],
        ]);
        $query = trim($validated['q'] ?? '');
        $type = $validated['type'] ?? null;

        // The quick-search box asks for JSON: a few results per type, instantly.
        if ($request->wantsJson()) {
            return response()->json([
                'query' => $query,
                'groups' => collect($search->search($query, 4))
                    ->map(fn ($items, $key) => ['type' => $key, 'label' => AdminSearch::TYPES[$key], 'items' => $items])
                    ->values(),
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('admin.search', [
            'query' => $query,
            'type' => $type,
            'types' => AdminSearch::TYPES,
            'results' => $search->search($query, $type ? 25 : 8, $type),
        ]);
    }
}

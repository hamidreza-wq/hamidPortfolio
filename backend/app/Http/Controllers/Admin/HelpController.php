<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HelpCentre;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function index(Request $request, HelpCentre $help)
    {
        $query = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));

        return view('admin.help.index', [
            'help' => $help,
            'query' => $query,
            'results' => $query !== '' ? $help->search($query, 20) : null,
        ]);
    }

    public function show(string $topic, HelpCentre $help)
    {
        abort_unless($help->exists($topic), 404);
        $slugs = array_keys(HelpCentre::TOPICS);
        $index = array_search($topic, $slugs, true);

        return view('admin.help.show', [
            'help' => $help,
            'topic' => $topic,
            'article' => $help->render($topic),
            'previous' => $slugs[$index - 1] ?? null,
            'next' => $slugs[$index + 1] ?? null,
            'page' => HelpCentre::TOPICS[$topic][3][0] ?? null,
        ]);
    }
}

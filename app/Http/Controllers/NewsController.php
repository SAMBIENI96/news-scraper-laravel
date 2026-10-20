<?php

namespace App\Http\Controllers;

use App\Services\NewsParserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function __construct(private readonly NewsParserService $parser) {}

    /**
     * Main page — renders the Blade view with today's news.
     */
    public function index(Request $request): View
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $news = $this->parser->getNews($date);

        return view('news.index', [
            'news'         => array_map(fn($n) => $n->toArray(), $news),
            'selectedDate' => $date,
            'total'        => count($news),
        ]);
    }

    /**
     * JSON endpoint — called by JS after user types a search query or changes date.
     * GET /api/news?date=YYYY-MM-DD&search=keyword
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'date'   => 'required|date_format:Y-m-d',
            'search' => 'nullable|string|max:255',
        ]);

        $date   = $request->input('date');
        $search = $request->input('search');

        $news = $this->parser->getNews($date, $search ?: null);

        return response()->json([
            'success' => true,
            'date'    => $date,
            'search'  => $search,
            'total'   => count($news),
            'news'    => array_map(fn($n) => $n->toArray(), $news),
        ]);
    }
}

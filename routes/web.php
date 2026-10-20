<?php

use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NewsController::class, 'index'])->name('news.index');
// routes/web.php

Route::get('/debug-selectors', function() {
    $client = new \GuzzleHttp\Client(['verify' => false]);
    $html = (string) $client->get('https://kaktus.media/')->getBody();
    
    $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
    
    return response()->json([
        'liens_doc'        => $crawler->filter('a[href*="/doc/"]')->count(),
        'premier_lien'     => $crawler->filter('a[href*="/doc/"]')->first()->count() > 0 
                              ? $crawler->filter('a[href*="/doc/"]')->first()->attr('href') 
                              : 'aucun',
        'premier_titre'    => $crawler->filter('a[href*="/doc/"]')->first()->count() > 0 
                              ? $crawler->filter('a[href*="/doc/"]')->first()->text() 
                              : 'aucun',
        'total_liens'      => $crawler->filter('a')->count(),
        'total_images'     => $crawler->filter('img')->count(),
    ]);
});

Route::get('/debug-date', function() {
    $client = new \GuzzleHttp\Client(['verify' => false]);
    try {
        $response = $client->get('https://kaktus.media/2026/03/31/');
        $html = (string) $response->getBody();
        
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        
        return response()->json([
            'status'       => $response->getStatusCode(),
            'liens_doc'    => $crawler->filter('a[href*="/doc/"]')->count(),
            'total_liens'  => $crawler->filter('a')->count(),
        ]);
    } catch (\Exception $e) {
        return response()->json(['erreur' => $e->getMessage()]);
    }
});
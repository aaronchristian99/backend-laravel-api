<?php

namespace Database\Seeders;

use App\Models\NewsAuthor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NewsAuthorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $authors = $this->getUniqueAuthors();

        foreach ($authors as $author) {
            NewsAuthor::create([
                'name' => $author,
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    private function getUniqueAuthors(): array
    {
        $authors = [];

//        $authors[] = $this->getAuthorsFromNewsAPI();
        $authors[] = $this->getAuthorsFromNYTimes();
        $authors[] = $this->getAuthorsFromTheGuardian();

        return array_unique(array_merge(...$authors));
    }

    private function getAuthorsFromNewsAPI(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.news_api').'/sources', [
            'apiKey' => config('api.news_api_key')
        ]);

        if ($response->successful()) {
            $sources = $response->json('sources');
            $authors = [];

            foreach ($sources as $source) {
                $articlesResponse = Http::withOptions([
                    'verify' => false
                ])->get(config('api.news_api').'/everything', [
                    'apiKey' => config('api.news_api_key'),
                    'sources' => $source['id']
                ]);

                if ($articlesResponse->successful()) {
                    $articles = $articlesResponse->json('articles');

                    foreach ($articles as $article) {
                        if (isset($article['author']) && !in_array($article['author'], $authors)) {
                            $authors[] = $article['author'];
                        }
                    }
                }
            }

            return $authors;
        } else {
            throw new \Exception('Cannot get authors from NewsAPI.', $response->status());
        }
    }

    private function getAuthorsFromNYTimes(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.nyt_api').'/search/v2/articlesearch.json', [
            'api-key' => config('api.nyt_api_key')
        ]);

        if ($response->successful()) {
            return collect($response->json('response.docs'))
                ->pluck('byline.original')
                ->map(function($author) {
                    // Split the author string by comma and "and"
                    $authors = preg_split('/,\s*|\s+and\s+/i', $author);

                    // Trim the whitespace and remove "By" prefix from each author
                    $authors = array_map(function($author) {
                        return trim(str_replace('By', '', $author));
                    }, $authors);

                    return $authors;
                })
                ->flatten()
                ->unique()
                ->values()
                ->all();
        } else {
            throw new \Exception('Cannot get authors from New York Times.', $response->status());
        }
    }

    private function getAuthorsFromTheGuardian(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.guardian_api').'/search', [
            'api-key' => config('api.guardian_api_key'),
            'show-fields' => 'byline'
        ]);

        if ($response->successful()) {
            $articles = $response->json('response.results');
            $authors = [];

            foreach ($articles as $article) {
                if (isset($article['fields']['byline'])) {
                    // Split the author string by comma and "and"
                    $authorsList = preg_split('/,\s*|\s+and\s+/i', $article['fields']['byline']);

                    // Trim the whitespace from each author
                    $authorsList = array_map('trim', $authorsList);

                    // Add the authors to the $authors array
                    foreach ($authorsList as $author) {
                        if (!in_array($author, $authors)) {
                            $authors[] = $author;
                        }
                    }
                }
            }

            return $authors;
        } else {
            throw new \Exception('Cannot get authors from The Guardian.', $response->status());
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\NewsException;
use App\Http\Controllers\Controller;
use App\Models\NewsAuthor;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use App\Models\UserAuthorPreference;
use App\Models\UserCategoryPreference;
use App\Models\UserSourcePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class NewsController extends Controller
{
    /**
     * Fetching articles from all sources
     * @throws NewsException
     */
    public function fetchNews(Request $request): JsonResponse
    {
//        $user = auth()->user();
        $userId = $request->query('userId');

        // Fetch user preferences
        $userCategories = UserCategoryPreference::with('category')
                                                ->where('user_id', $userId)
                                                ->get()
                                                ->pluck('category.name')
                                                ->flatten()
                                                ->all();
        $userSources = UserSourcePreference::with('source')
                                            ->where('user_id', $userId)
                                            ->get()
                                            ->pluck('source.name')
                                            ->flatten()
                                            ->all();
        $userAuthors = UserAuthorPreference::with('author')
                                            ->where('user_id', $userId)
                                            ->get()
                                            ->pluck('author.name')
                                            ->flatten()
                                            ->all();

        // Fetch NewsAPI articles
        $newsApiArticles = $this->fetchNewsApiArticles($userCategories, $userSources, $userAuthors);

        // Fetch New York Times articles
        $nytArticles = $this->fetchNytArticles($userCategories, $userSources, $userAuthors);

        // Fetch The Guardian Articles
        $guardianArticles = $this->fetchGuardianArticles($userCategories, $userSources, $userAuthors);

        // Combine all articles from different sources into single collection
        $articles = array_merge($newsApiArticles, $nytArticles, $guardianArticles);

        return response()->json([
            'news' => $articles
        ]);
    }

    /**
     * Search for news articles
     * @throws NewsException
     */
    public function searchNews(Request $request): JsonResponse
    {
        $searchKeyword = $request->input('keyword');
        $sources = [];
        $categories = [];
        $authors = [];

        // Search and fetch the articles using keyword
        $newsApiArticles = $this->fetchNewsApiArticles([], [], [], $searchKeyword);
        $nytArticles = $this->fetchNytArticles([], [], [], $searchKeyword);
        $guardianArticles = $this->fetchGuardianArticles([], [], [], $searchKeyword);

        // Combine all articles from different sources into single collection
        $articles = array_merge($newsApiArticles, $nytArticles, $guardianArticles);

        $sourceList = NewsSource::all();
        $categoryList = NewsCategory::all();
        $authorList = NewsAuthor::all();

        foreach($sourceList as $item) {
            $sources[] = $item['name'];
        }

        foreach($categoryList as $item) {
            $categories[] = $item['name'];
        }

        foreach($authorList as $item) {
            $authors[] = $item['name'];
        }

        // Collect the sources, categories, authors to make a collection
        $filters = collect([
            'sources' => $sources,
            'categories' => $categories,
            'authors' => $authors
        ])->all();

        session(['keyword' => $searchKeyword]);

        return response()->json([
            'news' => $articles,
            'filters' => $filters
        ]);
    }

    /**
     * Filter resulted articles by author, source, category and date
     * @throws NewsException
     */
    public function filterNews(Request $request): JsonResponse
    {
        $searchKeyword = $request->input('keyword') ?? session('keyword');
        $categories = $request->input('categories') ?? '';
        $authors = $request->input('authors') ?? '';
        $sources = $request->input('sources') ?? '';
        $fromDate = $request->input('fromDate') ?? '';
        $toDate = $request->input('toDate') ?? '';

        // Filter the resulted news
        $newsApiArticles = $this->filterNewsApiArticles($categories, $sources, $authors, $searchKeyword, $fromDate, $toDate);
        $nytArticles = $this->filterNytArticles($categories, $sources, $authors, $searchKeyword, $fromDate, $toDate);
        $guardianArticles = $this->fetchGuardianArticles($categories, $sources, $authors, $searchKeyword, $fromDate, $toDate);

        // Combine all filtered articles from different sources into single collection
        $articles = array_merge($newsApiArticles, $nytArticles, $guardianArticles);

        return response()->json([
            'news' => $articles
        ]);
    }

    /**
     * Fetch articles from NewsAPi
     * @throws NewsException
     */
    private function fetchNewsApiArticles(array $userCategories, array $userSources, array $userAuthors, string $searchKeyword = '', string $fromDate = '', string $toDate = ''): array
    {
        $api = config('api.news_api'). '/everything';
        $sourceApi = config('api.news_api'). '/sources';
        $apiKey = config('api.news_api_key');
        $excludedSources = ['The New York Times', 'theguardian.com', 'The Guardian'];
        $query = [
            'apiKey' => $apiKey,
            'q' => $searchKeyword,
            'from' => $fromDate,
            'to' => $toDate,
            'sources' => implode(',', $userSources)
        ];
        $sources = [];
        $newApiArticles = [];

        $filteredSources = array_filter($userSources, function ($source) use ($excludedSources) {
            return !in_array($source, $excludedSources);
        });

        if(empty($filteredSources)) {
            $api = config('api.news_api'). '/top-headlines';
            $query = [
                'apiKey' => $apiKey,
                'country' => 'ca'
            ];
        }

        $response = Http::withOptions([
            'verify' => false
        ])->get($api, $query);

        $sourceResponse = Http::withOptions([
            'verify' => false
        ])->get($sourceApi);

        if($sourceResponse->successful()) {
            $sources = $response->json('sources');
        }

        if($response->successful()) {
            $articles = $response->json('articles');

            $userCategories = array_map('strtolower', $userCategories);

            // Processing the filtered articles
            if(!empty($articles)) {
                foreach($articles as $article) {
                    $shouldAddArticle = false;
                    foreach($sources as $source) {
                        if($source->name === $article->source) {
                            if(in_array(strtolower($source->category), $userCategories)) {
                                $shouldAddArticle = true;
                                break;
                            }
                        }
                    }

                    if(in_array($article['author'], $userAuthors)) {
                        $shouldAddArticle = true;
                    }

                    if($shouldAddArticle) {
                        $newApiArticles[] = [
                            'title' => $article['title'],
                            'excerpt' => $article['description'],
                            'image' => $article['urlToImage'],
                            'author' => $article['author'],
                            'publishedDate' => $article['publishedAt'],
                            'source' => $article['source']['name'],
                            'content' => $article['content'],
                            'url' => $article['url']
                        ];
                    } else {
                        $newApiArticles[] = [
                            'title' => $article['title'],
                            'excerpt' => $article['description'],
                            'image' => $article['urlToImage'],
                            'author' => $article['author'],
                            'publishedDate' => $article['publishedAt'],
                            'source' => $article['source']['name'],
                            'content' => $article['content'],
                            'url' => $article['url']
                        ];
                    }
                }
            }

            return $newApiArticles;
        } else {
            throw new NewsException('Cannot get the articles from NewsAPI.', $response->status());
        }
    }

    /**
     * Fetch articles from NewsAPi
     * @throws NewsException
     */
    private function filterNewsApiArticles(array $userCategories, array $userSources, array $userAuthors, string $searchKeyword = '', string $fromDate = '', string $toDate = ''): array
    {
        $api = config('api.news_api'). '/everything';
        $sourceApi = config('api.news_api'). '/sources';
        $apiKey = config('api.news_api_key');
        $excludedSources = ['The New York Times', 'theguardian.com', 'The Guardian'];
        $query = [
            'apiKey' => $apiKey,
            'q' => $searchKeyword,
            'from' => $fromDate,
            'to' => $toDate,
            'sources' => implode(',', $userSources)
        ];
        $sources = [];
        $newApiArticles = [];

        $filteredSources = array_filter($userSources, function ($source) use ($excludedSources) {
            return !in_array($source, $excludedSources);
        });

        if(empty($filteredSources)) {
            $api = config('api.news_api'). '/top-headlines';
            $query = [
                'apiKey' => $apiKey,
                'country' => 'ca'
            ];
        }

        $response = Http::withOptions([
            'verify' => false
        ])->get($api, $query);

        $sourceResponse = Http::withOptions([
            'verify' => false
        ])->get($sourceApi);

        if($sourceResponse->successful()) {
            $sources = $response->json('sources');
        }

        if($response->successful()) {
            $articles = $response->json('articles');

            $userCategories = array_map('strtolower', $userCategories);

            // Processing the filtered articles
            if(!empty($articles)) {
                foreach($articles as $article) {
                    $shouldAddArticle = false;
                    foreach($sources as $source) {
                        if($source->name === $article->source) {
                            if(in_array(strtolower($source->category), $userCategories)) {
                                $shouldAddArticle = true;
                                break;
                            }
                        }
                    }

                    if(in_array($article['author'], $userAuthors)) {
                        $shouldAddArticle = true;
                    } else {
                        $shouldAddArticle = false;
                    }

                    if($shouldAddArticle) {
                        $newApiArticles[] = [
                            'title' => $article['title'],
                            'excerpt' => $article['description'],
                            'image' => $article['urlToImage'],
                            'author' => $article['author'],
                            'publishedDate' => $article['publishedAt'],
                            'source' => $article['source']['name'],
                            'content' => $article['content'],
                            'url' => $article['url']
                        ];
                    }
                }
            }
            return $newApiArticles;
        } else {
            throw new NewsException('Cannot get the articles from NewsAPI.', $response->status());
        }
    }

    /**
     * Fetch articles from New York Times
     * @throws NewsException
     */
    private function fetchNytArticles(array $userCategories, array $userSources, array $userAuthors, string $searchKeyword = '', string $fromDate = '', string $toDate = ''): array
    {
        $api = config('api.nyt_api'). '/search/v2/articlesearch.json';
        $apiKey = config('api.nyt_api_key');
        $query = [
            'api-key' => $apiKey,
            'q' => $searchKeyword,
        ];
        $nytArticles = [];

        if(!empty($userCategories) || !empty($userSources) || !empty($userAuthors)) {
            $query['fq'] = 'section_name: ('.implode(',', $userCategories).')
                    OR source: ('.implode(',', $userSources).')
                    OR byline: ('.implode(',', $userAuthors).')';
        }

        if(isset($fromDate) && !empty($fromDate)) {
            $query['begin_date'] = $fromDate;
        }

        if(isset($toDate) && !empty($toDate)) {
            $query['end_date'] = $toDate;
        }

        $response = Http::withOptions([
            'verify' => false
        ])->get($api, $query);

        if($response->successful()) {
            $articles = $response->json('response.docs');

            // Processing the filtered articles
            if(!empty($articles)) {
                foreach($articles as $article) {
                    if(isset($article['byline']['original'])) {
                        // Split the author string by comma and "and"
                        $authors = preg_split('/,\s*|\s+and\s+/i', $article['byline']['original']);

                        // Trim the whitespace and remove "By" prefix from each author
                        $authorNames = array_map(function($author) {
                            return trim(str_replace('By', '', $author));
                        }, $authors);
                    }

                    $nytArticles[] = [
                        'title' => $article['headline']['main'],
                        'excerpt' => $article['lead_paragraph'],
                        'image' => !empty($article['multimedia']) ? 'https://static01.nyt.com/'.$article['multimedia'][0]['url'] : '',
                        'author' => $authorNames ?? '',
                        'publishedDate' => $article['pub_date'],
                        'source' => $article['source'],
                        'content' => $article['content'] ?? '',
                        'url' => $article['web_url']
                    ];
                }
            }

            return $nytArticles;
        } else {
            throw new NewsException('Cannot get the articles from New York Times.', $response->status());
        }
    }

    /**
     * Fetch articles from New York Times
     * @throws NewsException
     */
    private function filterNytArticles(array $userCategories, array $userSources, array $userAuthors, string $searchKeyword = '', string $fromDate = '', string $toDate = ''): array
    {
        $api = config('api.nyt_api'). '/search/v2/articlesearch.json';
        $apiKey = config('api.nyt_api_key');
        $query = [
            'api-key' => $apiKey,
            'q' => $searchKeyword,
        ];
        $nytArticles = [];

        if(!empty($userCategories) || !empty($userSources) || !empty($userAuthors)) {
            $query['fq'] = 'section_name: ('.implode(',', $userCategories).')
                    AND source: ('.implode(',', $userSources).')
                    AND byline: ('.implode(',', $userAuthors).')';
        }

        if(isset($fromDate) && !empty($fromDate)) {
            $query['begin_date'] = $fromDate;
        }

        if(isset($toDate) && !empty($toDate)) {
            $query['end_date'] = $toDate;
        }

        $response = Http::withOptions([
            'verify' => false
        ])->get($api, $query);

        if($response->successful()) {
            $articles = $response->json('response.docs');

            // Processing the filtered articles
            if(!empty($articles)) {
                foreach($articles as $article) {
                    if(isset($article['byline']['original'])) {
                        // Split the author string by comma and "and"
                        $authors = preg_split('/,\s*|\s+and\s+/i', $article['byline']['original']);

                        // Trim the whitespace and remove "By" prefix from each author
                        $authorNames = array_map(function($author) {
                            return trim(str_replace('By', '', $author));
                        }, $authors);
                    }

                    $nytArticles[] = [
                        'title' => $article['headline']['main'],
                        'excerpt' => $article['lead_paragraph'],
                        'image' => !empty($article['multimedia']) ? 'https://static01.nyt.com/'.$article['multimedia'][0]['url'] : '',
                        'author' => $authorNames ?? '',
                        'publishedDate' => $article['pub_date'],
                        'source' => $article['source'],
                        'content' => $article['content'] ?? '',
                        'url' => $article['web_url']
                    ];
                }
            }

            return $nytArticles;
        } else {
            throw new NewsException('Cannot get the articles from New York Times.', $response->status());
        }
    }

    /**
     * Fetch articles from The Guardian
     * @throws NewsException
     */
    private function fetchGuardianArticles(array $userCategories, array $userSources, array $userAuthors, string $searchKeyword = '', string $fromDate = '', string $toDate = ''): array
    {
        $api = config('api.guardian_api'). '/search';
        $apiKey = config('api.guardian_api_key');
        $query = [
            'api-key' => $apiKey,
            'show-fields' => 'all',
            'q' => $searchKeyword,
        ];
        $guardianArticles = [];

        if(isset($fromDate) && !empty($fromDate)) {
            $query[] = [
                'from-date' => $fromDate,
            ];
        }

        if(isset($toDate) && !empty($toDate)) {
            $query[] = [
                'to-date' => $toDate,
            ];
        }

        $response = Http::withOptions([
            'verify' => false
        ])->get($api, $query);

        if($response->successful()) {
            $articles = $response->json('response.results');

            // Filter the articles by user preferenced sources, authors and categories
            $filteredAuthorArticles = array_filter($articles, function($article) use($userAuthors) {
                return isset($article['fields']['byline']) && in_array($article['fields']['byline'], $userAuthors);
            });

            $filterCategoryArticles = array_filter($articles, function($article) use($userCategories) {
                return isset($article['section_name']) && in_array($article['section_name'], $userCategories);
            });

            $filterSourceArticles = array_filter($articles, function($article) use($userSources) {
                return isset($article['fields']['publication']) && in_array($article['fields']['publication'], $userSources);
            });

            $filteredArticles = collect([
                $filterCategoryArticles,
                $filteredAuthorArticles,
                $filterSourceArticles
            ])->unique()->flatten()->all();

            // Processing the filtered articles
            if(!empty($filteredArticles)) {
                foreach($filteredArticles as $article) {
                    $guardianArticles[] = [
                        'title' => $article['webTitle'],
                        'excerpt' => $article['fields']['trailText'],
                        'image' => $article['fields']['thumbnail'],
                        'author' => $article['fields']['byline'],
                        'publishedDate' => $article['webPublicationDate'],
                        'source' => $article['fields']['publication'],
                        'content' => $article['fields']['bodyText'],
                        'url' => $article['webUrl']
                    ];
                }
            }

            return $guardianArticles;
        } else {
            throw new NewsException('Cannot get the articles from The Guardian.', $response->status());
        }
    }
}

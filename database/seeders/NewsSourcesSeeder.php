<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class NewsSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * @throws \Exception
     */
    public function run(): void
    {
        $sources = $this->getUniqueSources();

        foreach ($sources as $source) {
            NewsSource::create([
                'name' => $source,
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    private function getUniqueSources(): array
    {
        $sources = [];

        $sources[] = $this->getSourcesFromNewsAPI();
        $sources[] = $this->getSourcesFromNYTimes();
        $sources[] = $this->getSourcesFromTheGuardian();

        return array_unique(array_merge(...$sources));
    }

    /**
     * @throws \Exception
     */
    private function getSourcesFromNewsAPI(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.news_api').'/sources', [
            'apiKey' => config('api.news_api_key')
        ]);

        return $this->extractSources($response, 'sources', 'name');
    }

    /**
     * @throws \Exception
     */
    private function getSourcesFromNYTimes(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.nyt_api').'/search/v2/articlesearch.json', [
            'api-key' => config('api.nyt_api_key')
        ]);

        return $this->extractSources($response, 'response.docs', 'source');
    }

    /**
     * @throws \Exception
     */
    private function getSourcesFromTheGuardian(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.guardian_api').'/search', [
            'api-key' => config('api.guardian_api_key'),
            'show-fields' => 'all'
        ]);

        return $this->extractSources($response, 'response.results', 'fields.publication');
    }

    private function extractSources($response, $key, $keyValue): array
    {
        if ($response->successful()) {
            return collect($response->json($key))
                ->pluck($keyValue)
                ->unique()
                ->values()
                ->all();
        } else {
            throw new \Exception('Cannot get sources.', $response->status());
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class NewsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = $this->getUniqueCategories();

        foreach ($categories as $category) {
            NewsCategory::create([
                'name' => $category,
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    private function getUniqueCategories(): array
    {
        $sources = [];

//        $sources[] = $this->getCategoriesFromNewsAPI();
        $sources[] = $this->getCategoriesFromNYTimes();
        $sources[] = $this->getCategoriesFromTheGuardian();

        return array_unique(array_merge(...$sources));
    }

    /**
     * @throws \Exception
     */
    private function getCategoriesFromNewsAPI(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.news_api').'/sources', [
            'apiKey' => config('api.news_api_key')
        ]);

        return $this->extractSources($response, 'sources', 'category');
    }

    /**
     * @throws \Exception
     */
    private function getCategoriesFromNYTimes(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.nyt_api').'/news/v3/content/section-list.json', [
            'api-key' => config('api.nyt_api_key')
        ]);

        return $this->extractSources($response, 'results', 'display_name');
    }

    /**
     * @throws \Exception
     */
    private function getCategoriesFromTheGuardian(): array
    {
        $response = Http::withOptions([
            'verify' => false
        ])->get(config('api.guardian_api').'/sections', [
            'api-key' => config('api.guardian_api_key')
        ]);

        return $this->extractSources($response, 'response.results', 'webTitle');
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
            throw new \Exception('Cannot get categories.', $response->status());
        }
    }
}

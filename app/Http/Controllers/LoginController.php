<?php

namespace App\Http\Controllers;

use App\Models\NewsAuthor;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use App\Models\User;
use App\Models\UserAuthorPreference;
use App\Models\UserCategoryPreference;
use App\Models\UserSourcePreference;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class LoginController extends Controller
{
    /**
     * The login method of controller
     */
    public function login(Request $request): JsonResponse
    {
        $creds = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ], [
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'password.required' => 'The password field is required.'
        ]);

        if(Auth::attempt($creds)) {
            $user = auth()->user();
            session(['user' => $user]);
            return response()->json([
                'user' => $user
            ]);
        } else {
            throw new AuthenticationException('Incorrect email or password.');
        }
    }

    /**
     * The Register Method
     */
    public function register(Request $request): JsonResponse
    {
        // Validation
        $request->validate([
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required'
        ], [
            'firstName.required' => 'The first name field is required.',
            'lastName.required' => 'The last name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email address already exists.',
            'password' => 'The password field is required.'
        ]);

        // Create the user
        $user = User::create([
            'first_name' => $request->input('firstName'),
            'last_name' => $request->input('lastName'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        // Automatically log in the user after registration
        auth()->login($user);
        session(['user' => $user]);

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * Change user password
    */
    public function changeUserPassword(Request $request): JsonResponse
    {
        $userId = $request->input('userId');
        $newPassword = $request->input('newPassword');
        $tempPassword = $request->input('tempPassword');

        if($newPassword === $tempPassword) {
            $user = User::find($userId)
                        ->update([
                            'password' => $newPassword
                        ]);

            \auth()->login($user);

            return response()->json([
                'user' => $user
            ]);
        } else {
            throw \Exception("unable to update the password. Password does not match!");
        }
    }

    /**
     * The Logout method
     */
    public function logout(Request $request): JsonResponse
    {
        //Log out the user
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Fetch the categories, sources, and authors list
     */
    public function fetchPreferencesList(Request $request): JsonResponse
    {
        $sources = NewsSource::all();
        $categories = NewsCategory::all();
        $authors = NewsAuthor::all();

        // Collect the sources, categories, authors to make a collection
        $preferenceList = collect([
            'sources' => $sources,
            'categories' => $categories,
            'authors' => $authors
        ])->all();

        return response()->json([
            'preferences' => $preferenceList
        ]);
    }

    /**
     * Fetch user preference llist
     */
    public function fetchUserPreferences(Request $request): JsonResponse
    {
        $userId = $request->query('userId');

        $sources = $this->fetchUserSources($userId);
        $categories = $this->fetchUserCategories($userId);
        $authors = $this->fetchUserAuthors($userId);


        // Collect user preference sources, categories and authors to make a collection
        $userPreferences = collect([
            'sources' => $sources,
            'categories' => $categories,
            'authors' => $authors,
        ])->all();

        return response()->json([
            'userPreferences' => $userPreferences
        ]);
    }

    /**
     * Store user preferences
     */
    public function storeUserPreferences(Request $request): JsonResponse
    {
//        $user = auth()->user() ?? session('user');
        $userId = $request->input('userId');
        $userAuthors = $request->input('authors');
        $userSources = $request->input('sources');
        $userCategories = $request->input('categories');

        // Storing the user preferences
        $authors = $this->storeUserAuthors($userId, $userAuthors);
        $sources = $this->storeUserSources($userId, $userSources);
        $categories = $this->storeUserCategories($userId, $userCategories);

        // Collecting authors, sources and categories into a single collection
        $userPreferences = collect([
            'authors' => $authors,
            'sources' => $sources,
            'categories' => $categories,
        ])->all();

        return response()->json($userPreferences);
    }

    /**
     * Store user preferences
     */
    public function removeUserPreferences(Request $request): JsonResponse
    {
//        $user = auth()->user() ?? session('user');
        $userId = $request->input('userId');
        $userAuthors = $request->input('authors');
        $userSources = $request->input('sources');
        $userCategories = $request->input('categories');

        // Removing the user preferences
        $authors = $this->removeUserAuthors($userId, $userAuthors);
        $sources = $this->removeUserSources($userId, $userSources);
        $categories = $this->removeUserCategories($userId, $userCategories);

        // Collecting authors, sources and categories into a single collection
        $userPreferences = collect([
            $authors,
            $sources,
            $categories,
        ]);

        return response()->json($userPreferences);
    }

    /**
     * Fetching user preference authors
     * @return array
     */
    private function fetchUserAuthors(int $userId): array
    {
//        $user = auth()->user() ?? session('user');
        $authors = [];

        $userAuthors = UserAuthorPreference::with('author')
                                            ->where('user_id', $userId)
                                            ->get();

        foreach($userAuthors as $userAuthor) {
            $authors[] = [
                'id' => $userAuthor->author_id,
                'name' => $userAuthor->author->name
            ];
        }

        return $authors;
    }

    /**
     * Fetching user preference sources
     * @return array
     */
    private function fetchUserSources(int $userId): array
    {
//        $user = auth()->user() ?? session('user');
        $sources = [];

        $userSources = UserSourcePreference::with('source')
                                            ->where('user_id', $userId)
                                            ->get();

        foreach($userSources as $userSource) {
            $sources[] = [
                'id' => $userSource->source_id,
                'name' => $userSource->source->name
            ];
        }

        return $sources;
    }

    /**
     * Fetching user preference categories
     * @return array
     */
    private function fetchUserCategories(int $userId): array
    {
//        $user = auth()->user() ?? session('user');
        $categories = [];

        $userCategories = UserCategoryPreference::with('category')
                                                ->where('user_id', $userId)
                                                ->get();

        foreach($userCategories as $userCategory) {
            $categories[] = [
                'id' => $userCategory->category_id,
                'name' => $userCategory->category->name
            ];
        }

        return $categories;
    }

    /**
     * Storing authors selected by user
     * @param array $userAuthors
     * @return array
     */
    private function storeUserAuthors(int $userId, array $userAuthors): array
    {
//        $user = auth()->user() ?? session('user');

        $authors = [];

        // Storing the authors selected by user
        foreach($userAuthors as $userAuthor) {
            $author = UserAuthorPreference::create([
                'user_id' => $userId,
                'author_id' => $userAuthor
            ]);

            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * Storing sources selected by user
     * @param array $userSources
     * @return array
     */
    private function storeUserSources(int $userId, array $userSources): array
    {
//        $user = auth()->user() ?? session('user');
        $sources = [];

        // Storing the authors selected by user
        foreach($userSources as $userSource) {
            $source = UserSourcePreference::create([
                'user_id' => $userId,
                'source_id' => $userSource
            ]);

            $sources[] = $source;
        }

        return $sources;
    }

    /**
     * Storing categories selected by user
     * @param array $userCategories
     * @return array
     */
    private function storeUserCategories(int $userId, array $userCategories): array
    {
//        $user = auth()->user() ?? session('user');
        $categories = [];

        // Storing the authors selected by user
        foreach($userCategories as $userCategory) {
            $category = UserCategoryPreference::create([
                'user_id' => $userId,
                'category_id' => $userCategory
            ]);

            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Removing authors selected by user
     * @param array $userAuthors
     * @return array
     */
    private function removeUserAuthors(int $userId, array $userAuthors): array
    {
//        $user = auth()->user() ?? session('user');

        // Removing the authors selected by user
        foreach($userAuthors as $userAuthor) {
            UserAuthorPreference::where('user_id', $userId)
                                ->where('author_id', $userAuthor)
                                ->delete();
        }

        return $this->fetchUserAuthors();
    }

    /**
     * Removing sources selected by user
     * @param array $userSources
     * @return array
     */
    private function removeUserSources(int $userId, array $userSources): array
    {
//        $user = auth()->user() ?? session('user');

        // Removing the authors selected by user
        foreach($userSources as $userSource) {
            UserSourcePreference::where('user_id', $userId)
                                ->where('source_id', $userSource)
                                ->delete();
        }

        return $this->fetchUserSources() ?? session('user');
    }

    /**
     * Removing categories selected by user
     * @param array $userCategories
     * @return array
     */
    private function removeUserCategories(int $userId, array $userCategories): array
    {
//        $user = auth()->user() ?? session('user');

        // Removing the categories selected by user
        foreach($userCategories as $userCategory) {
            UserCategoryPreference::where('user_id', $userId)
                                  ->where('category_id', $userCategory)
                                  ->delete();
        }

        return $this->fetchUserCategories();
    }
}

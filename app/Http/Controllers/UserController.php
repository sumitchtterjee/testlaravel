<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use League\Csv\Writer;
use Illuminate\Pagination\LengthAwarePaginator;

class UserController extends Controller
{
    //
    /**
     * Display a paginated list of users, with optional gender filtering and CSV export.
     *
     * Logic Overview:
     * - Retrieves 'page', 'gender', and 'export' query parameters from the request.
     * - Attempts to fetch a batch of users from cache (or from the external API if not cached).
     *   - Caching is based on gender and batch (5 pages per batch).
     *   - If gender is specified and valid, it is included in the API query.
     *   - If the API response is invalid, an error is set.
     * - Paginates the users for the current page (10 users per page).
     * - If 'export' is requested and there is no error, streams a CSV download of the current batch.
     * - Otherwise, renders the 'user' view with paginated users, filter info, and error state.
     *
     */
    public function index(Request $request)
    {
        // Get the 'page' query parameter from the request, defaulting to 1, and cast it to integer
        $page = (int) $request->query('page', 1);
        // Get the 'gender' query parameter from the request (if present)
        $gender = $request->query('gender');
        // Get the 'export' query parameter from the request (if present)
        $export = $request->query('export');
        // Initialize the error variable as null (no error by default)
        $error = null;
        // Initialize both $paginatedUsers and $users as empty arrays
        $paginatedUsers = $users = [];
        // Begin a try block to catch any exceptions or errors during execution
        try {
            // Get the API URL from environment or use default if not set
            $apiUrl = env('RANDOM_USER_API_URL', 'https://randomuser.me/api/');
            // Get the number of results to fetch from environment or default to 50, cast to integer
            $resultsCount = (int) env('RANDOM_USER_API_RESULTS', 50);
            // Build a cache key based on gender and batch number (5 pages per batch)
            $cacheKey = 'users_' . ($gender ?: 'all') . '_batch_' . ceil($page / 5);
            // Retrieve users from cache or fetch from API and cache for 300 seconds
            $users = Cache::remember($cacheKey, 300, function () use ($gender, $apiUrl, $resultsCount) {
                // Prepare query parameters for the API request
                $query = [
                    'results' => $resultsCount
                ];
                // If gender is specified and valid, add it to the query
                if ($gender && in_array($gender, ['male', 'female'])) {
                    $query['gender'] = $gender;
                }
                // Make a GET request to the API with the query parameters
                $response = Http::get($apiUrl, $query);
                // If the response is not OK or does not contain 'results', throw an exception
                if (!$response->ok() || !isset($response['results'])) {
                    throw new \Exception('Invalid API response');
                }
                // Return the 'results' array from the API response
                return $response['results'];
            });
            // Set the number of users to display per page
            $perPage = 10;
            // Calculate the offset for the current page within the batch
            $offset = (($page - 1) % ceil($resultsCount / $perPage)) * $perPage;
            // Slice the users array to get only the users for the current page
            $paginatedUsers = array_slice($users, $offset, $perPage);
            // Create a paginator instance for the paginated users
            $paginator = new LengthAwarePaginator(
                $paginatedUsers, // Items for the current page
                $resultsCount,   // Total items in the batch
                $perPage,        // Items per page
                $page,           // Current page number
                [
                    // Set the base path for pagination links
                    'path' => url('/users'),
                    // Add gender to the query string if present
                    'query' => array_filter(['gender' => $gender])
                ]
            );
        // Catch any exception or error thrown in the try block
        } catch (\Throwable $e) {
            // Set a user-friendly error message if fetching users fails
            $error = 'Unable to fetch users at this time. Please try again later.';
            // Set paginator to null since pagination cannot proceed on error
            $paginator = null;
        }
        // Render the 'user' view, passing all relevant data to the template
        return view('user', [
            // The users to display on the current page
            'users' => $paginatedUsers,
            // The current page number
            'page' => $page,
            // The selected gender filter, if any
            'gender' => $gender,
            // All user details fetched (for export or other use)
            'details' => $users,
            // Any error message to display
            'error' => $error,
            // The paginator instance for pagination controls
            'paginator' => $paginator
        ]);
    }

    public function export(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $gender = $request->input('gender');
        $apiUrl = env('RANDOM_USER_API_URL', 'https://randomuser.me/api/');
        $resultsCount = (int) env('RANDOM_USER_API_RESULTS', 50);
        $cacheKey = 'users_' . ($gender ?: 'all') . '_batch_' . ceil($page / 5);
        try {
            $users = app('cache')->remember($cacheKey, 300, function () use ($gender, $apiUrl, $resultsCount) {
                $query = ['results' => $resultsCount];
                if (in_array($gender, ['male', 'female'])) $query['gender'] = $gender;
                $response = app('http')->get($apiUrl, $query);
                if (!$response->ok() || !isset($response['results'])) {
                    throw new \Exception('Invalid API response');
                }
                return $response['results'];
            });
            $perPage = 10;
            $offset = (($page - 1) % ceil($resultsCount / $perPage)) * $perPage;
            $paginatedUsers = array_slice($users, $offset, $perPage);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Unable to fetch users at this time. Please try again later.');
        }
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ];
        return response()->stream(function () use ($paginatedUsers) {
            $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
            $csv->insertOne(['Name', 'Email', 'Gender', 'Nationality']);
            foreach ($paginatedUsers as $u) {
                $csv->insertOne([
                    $u['name']['first'] . ' ' . $u['name']['last'],
                    $u['email'],
                    ucfirst($u['gender']),
                    $u['nat']
                ]);
            }
            $csv->output();
        }, 200, $headers);
    }
}

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

    /**
     * Export the currently filtered and paginated user list as a CSV file.
     * 
     * - Reads 'page' and 'gender' from the request.
     * - Fetches the relevant batch of users from cache or API.
     * - Slices the batch to get only the users for the current page.
     * - Streams a CSV download containing Name, Email, Gender, and Nationality columns.
     * - Handles API errors gracefully by redirecting back with an error message.
     */
    public function export(Request $request)
    {
        // Get the current page number from the request, default to 1
        $page = (int) $request->input('page', 1);
        // Get the selected gender filter from the request
        $gender = $request->input('gender');
        // Get the Random User API URL from environment or use default
        $apiUrl = env('RANDOM_USER_API_URL', 'https://randomuser.me/api/');
        // Get the number of results to fetch per batch from environment or use 50
        $resultsCount = (int) env('RANDOM_USER_API_RESULTS', 50);
        // Build a cache key based on gender and batch number (10 users per page, 5 pages per batch)
        $cacheKey = 'users_' . ($gender ?: 'all') . '_batch_' . ceil($page / 5);
        try {
            // Try to get users from cache, or fetch from API and cache for 5 minutes (300 seconds)
            $users = app('cache')->remember($cacheKey, 300, function () use ($gender, $apiUrl, $resultsCount) {
                // Build the query for the API request
                $query = ['results' => $resultsCount];
                // If gender is set to 'male' or 'female', add it to the query
                if (in_array($gender, ['male', 'female'])) $query['gender'] = $gender;
                // Make the HTTP GET request to the API
                $response = app('http')->get($apiUrl, $query);
                // If the response is not OK or does not contain 'results', throw an exception
                if (!$response->ok() || !isset($response['results'])) {
                    throw new \Exception('Invalid API response');
                }
                // Return the users array from the API response
                return $response['results'];
            });
            // Set the number of users per page
            $perPage = 10;
            // Calculate the offset for the current page within the batch
            $offset = (($page - 1) % ceil($resultsCount / $perPage)) * $perPage;
            // Slice the users array to get only the users for the current page
            $paginatedUsers = array_slice($users, $offset, $perPage);
        } catch (\Throwable $e) {
            // If any error occurs, redirect back with an error message
            return redirect()->back()->with('error', 'Unable to fetch users at this time. Please try again later.');
        }
        // Set the headers for the CSV download response
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ];
        // Stream the CSV file as a response
        return response()->stream(function () use ($paginatedUsers) {
            // Create a CSV writer using a temporary file object
            $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
            // Insert the CSV header row
            $csv->insertOne(['Name', 'Email', 'Gender', 'Nationality']);
            // Insert each user's data as a row in the CSV
            foreach ($paginatedUsers as $u) {
                $csv->insertOne([
                    $u['name']['first'] . ' ' . $u['name']['last'],
                    $u['email'],
                    ucfirst($u['gender']),
                    $u['nat']
                ]);
            }
            // Output the CSV to the response stream
            echo $csv->toString();
        }, 200, $headers);
    }
}

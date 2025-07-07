# Laravel Random User List

## Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <your-repo-url>
   cd <project-directory>
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install && npm run dev # if you use frontend assets
   ```

3. **Copy and configure your environment file**
   ```bash
   cp .env.example .env
   # Then edit .env as needed
   ```
   Add these lines to your `.env` file if not present:
   ```env
   RANDOM_USER_API_URL=https://randomuser.me/api/
   RANDOM_USER_API_RESULTS=50
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **Set up permissions** (if needed)
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

6. **Run the development server**
   ```bash
   php artisan serve
   ```

## How to Use

### Access the User Page
- Visit: `http://localhost:8000/users`
- The page will display a paginated table of users fetched from the Random User API.

### Filtering by Gender
- Use the dropdown in the table header to filter users by gender:
  - **All**: Shows all users
  - **Male**: Shows only male users
  - **Female**: Shows only female users
- The filter works by updating the `gender` query parameter in the URL, e.g.:
  - `http://localhost:8000/users?gender=male`
  - `http://localhost:8000/users?gender=female`

### Pagination
- The table paginates users, 10 per page. Use the pagination controls at the bottom to navigate pages.

### Export to CSV
- Click the **Export to CSV** button to download the current (filtered/unfiltered) user list as a CSV file.

## Notes
- The app uses Laravel's cache to reduce API calls and improve performance.
- If the Random User API is down or returns an error, a user-friendly message will be displayed.
- You can configure the API URL and number of users fetched per batch in your `.env` file.

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
    <body class="container">
        @if(isset($error) && $error)
            <div style="color: red; margin-bottom: 16px;">{{ $error }}</div>
        @endif
        <h2>Display the following user details in a table:</h2>
        
        <form method="GET" style="margin-bottom: 16px; display: inline-block;">
            <input type="hidden" name="gender" value="{{ $gender }}">
            <input type="hidden" name="page" value="{{ $page ?? 1 }}">
            <input type="hidden" name="export" value="1">
            <button type="submit" class="btn btn-success">Export to CSV</button>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Name (first and last)</th>
                        <th>Email</th>
                        <th>
                            <form method="GET" style="margin-bottom:0;">
                                <label for="gender" class="mb-0">Gender</label><br>
                                <select name="gender" id="gender" onchange="this.form.submit()" class="form-control form-control-sm" style="width:auto;display:inline-block;">
                                    <option value="" @if(empty($gender)) selected @endif>All</option>
                                    <option value="male" @if(isset($gender) && $gender==='male') selected @endif>Male</option>
                                    <option value="female" @if(isset($gender) && $gender==='female') selected @endif>Female</option>
                                </select>
                                <input type="hidden" name="page" value="{{ $page ?? 1 }}">
                                <noscript><button type="submit">Apply</button></noscript>
                            </form>
                        </th>
                        <th>Nationality</th>
                    </tr>
                </thead>
                <tbody>
                @if(isset($paginator) && $paginator)
                    @foreach($paginator as $user)
                        <tr>
                            <td>{{ $user['name']['first'] }} {{ $user['name']['last'] }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>{{ ucfirst($user['gender']) }}</td>
                            <td>{{ $user['nat'] }}</td>
                        </tr>
                    @endforeach
                @elseif(isset($users) && count($users) > 0)
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user['name']['first'] }} {{ $user['name']['last'] }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>{{ ucfirst($user['gender']) }}</td>
                            <td>{{ $user['nat'] }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr><td colspan="4">No users found.</td></tr>
                @endif
                </tbody>
            </table>
        </div>
        @if(isset($paginator) && $paginator)
            <div class="mt-3 d-flex justify-content-center">
                {{ $paginator->withQueryString()->links('pagination::bootstrap-4') }}
            </div>
        @endif

    </body>
</html>
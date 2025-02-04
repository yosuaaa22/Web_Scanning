<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Monitoring</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #007bff;
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .container {
            margin-top: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        th {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">Monitoring System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1>Website Monitoring</h1>
        <table class="table table-striped text-center" id="monitoring-table">
            <thead>
                <tr>
                    <th>Website</th>
                    <th>Status</th>
                    <th>Response Time</th>
                    <th>Last Checked</th>
                    <th>Vulnerabilities</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data akan diisi oleh JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- JavaScript -->
    <script>
        function fetchData() {
            fetch('/monitor/real-time-status')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#monitoring-table tbody');
                    tbody.innerHTML = ''; // Clear existing rows

                    data.forEach(website => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${website.name}</td>
                            <td class="${website.status === 'up' ? 'text-success' : 'text-danger'}">${website.status}</td>
                            <td>${website.response_time ? website.response_time + ' ms' : 'N/A'}</td>
                            <td>${website.last_checked_at ? new Date(website.last_checked_at).toLocaleString() : 'N/A'}</td>
                            <td>${website.vulnerabilities.length ? website.vulnerabilities.join(', ') : 'None'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        // Fetch data every 30 seconds
        setInterval(fetchData, 30000);

        // Initial fetch
        fetchData();
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

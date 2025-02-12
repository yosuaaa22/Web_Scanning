<!DOCTYPE html>
<html>
<head>
    <title>Performance Metrics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Performance Metrics</h2>
        
        <div class="card shadow">
            <div class="card-body">
                <table class="table table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Timestamp</th>
                            <th>Memory Usage</th>
                            <th>CPU Usage</th>
                            <th>Disk Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics as $metric)
                        <tr>
                            <td>{{ $metric->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ number_format($metric->memory_usage / 1024 / 1024, 2) }} MB</td>
                            <td>{{ $metric->cpu_usage }}%</td>
                            <td>{{ $metric->disk_usage }}%</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">No metrics found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Security Login Attempts</h1>

    <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Login Attempt Summary</h2>
            <ul>
                @foreach($loginAttempts as $attempt)
                    <li>
                        {{ $attempt->status }}: {{ $attempt->total }} attempts 
                        ({{ $attempt->unique_ips }} unique IPs)
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="bg-white rounded-lg shadow p-6 md:col-span-2">
            <h2 class="text-xl font-semibold mb-4">Recent Login Attempts</h2>
            <table class="w-full">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentAttempts as $attempt)
                        <tr>
                            <td>{{ $attempt->ip_address }}</td>
                            <td>{{ $attempt->status }}</td>
                            <td>{{ $attempt->location }}</td>
                            <td>{{ $attempt->created_at->diffForHumans() }}</td>
                            <td>
                                <button class="block-ip-btn text-red-500" 
                                        data-ip="{{ $attempt->ip_address }}">
                                    Block
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Blocked IPs</h2>
        <table class="w-full">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Location</th>
                    <th>Blocked At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($blockedIPs as $ip)
                    <tr>
                        <td>{{ $ip->ip_address }}</td>
                        <td>{{ $ip->location }}</td>
                        <td>{{ $ip->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
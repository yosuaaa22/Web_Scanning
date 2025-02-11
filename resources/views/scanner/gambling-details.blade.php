<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gambling Analysis</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <header class="bg-blue-600 text-white py-4 shadow-md">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">Gambling Analysis</h1>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="#" class="hover:underline">Home</a></li>
                    <li><a href="#" class="hover:underline">Reports</a></li>
                    <li><a href="#" class="hover:underline">Settings</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-semibold mb-4">Gambling Analysis Details</h1>
        <div class="bg-white shadow-md rounded-lg p-6">
            @if(!empty($gamblingResult['analysis']))
                @foreach($gamblingResult['analysis'] as $key => $value)
                    @if(is_array($value))
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}</h2>
                            <ul class="list-disc list-inside pl-5">
                                @foreach($value as $item)
                                    <li>{{ is_array($item) ? json_encode($item) : $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            @else
                <p>No details available.</p>
            @endif
        </div>
    </div>
</body>
</html>
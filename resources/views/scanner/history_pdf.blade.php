<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Riwayat Scanning</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.3;
        }
        .logo-text {
            font-size: 14px;
            margin: 0;
            padding: 0;
        }
        .organization-name {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
            padding: 0;
        }
        .address {
            font-size: 12px;
            margin: 5px 0 15px 0;
            padding: 0;
        }
        .header-line {
            border-bottom: 1px solid #000;
            margin: 0 0 20px 0;
        }
        .report-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0;
        }
        .date-info {
            margin: 15px 0;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: auto;
            background-color: #ffffff;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #405d99;
            color: white;
            font-weight: bold;
            border: 1px solid #2b3f68;
        }
        .url-column {
            width: 35%;
        }
        .waktu-column {
            width: 20%;
        }
        .backdoor-column {
            width: 25%;
        }
        .judi-column {
            width: 15%;
        }
        .no-column {
            width: 5%;
        }

        /* Risk Level Colors */
        .risk-tinggi {
            color: #dc3545;
            font-weight: bold;
        }
        .risk-sedang {
            color: #fd7e14;
            font-weight: bold;
        }
        .risk-rendah {
            color: #198754;
            font-weight: bold;
        }
        .risk-minimal {
            color: #198754;
            font-weight: bold;
        }

        /* Container for date info */
        .date-container {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
        }
        .date-info {
            font-size: 12px;
            color: #495057;
        }
    </style>
</head>
<body>
    <h1 class="organization-name">CSIRT PURWAKARTAKAB</h1>
    <p class="address">Jl. Laks. Laut RE. Martadinata No.47, Nagri Tengah, Kec. Purwakarta, Kabupaten Purwakarta, Jawa Barat 41114</p>
    <div class="header-line"></div>

    <h2 class="report-title">Laporan Riwayat Scanning</h2>

    <div class="date-container">
        <div class="date-info">
            <strong>Tahun:</strong> {{ date('Y') }}
        </div>
        <div class="date-info">
            <strong>Tanggal:</strong> {{ date('d F Y') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="no-column">No</th>
                <th class="url-column">URL</th>
                <th class="waktu-column">Waktu Scan</th>
                <th class="backdoor-column">Risiko Backdoor</th>
                <th class="judi-column">Risiko Judi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($scanResults as $index => $result)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $result->url }}</td>
                <td>{{ $result->scan_time->format('Y-m-d H:i:s') }}</td>
                <td class="risk-{{ strtolower(str_replace(' (Kepercayaan Tinggi)', '', $result->backdoor_risk)) }}">
                    {{ $result->backdoor_risk }}
                </td>
                <td class="risk-{{ strtolower($result->gambling_risk) }}">
                    {{ $result->gambling_risk }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center;">Tidak ada data scanning</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

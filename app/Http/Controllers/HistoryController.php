<?php

namespace App\Http\Controllers;

use App\Models\ScanResult;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class HistoryController extends Controller
{
    public function index()
    {
        $scanResults = ScanResult::orderBy('scan_time', 'desc')->paginate(10);
        
        $uniqueDates = ScanResult::selectRaw('DATE(scan_time) as date')
                        ->groupBy('date')
                        ->orderBy('date', 'desc')
                        ->pluck('date');

        return view('scanner.history', compact('scanResults', 'uniqueDates'));
    }

    public function downloadPDF(Request $request)
    {
        try {
            $query = ScanResult::orderBy('scan_time', 'desc');

            // Gunakan filled() untuk cek parameter tidak kosong
            if ($request->filled('date')) {
                $query->whereDate('scan_time', $request->date);
            }

            $scanResults = $query->get();

            // Validasi jika data kosong
            if($scanResults->isEmpty()) {
                return redirect()->route('scanner.history')
                    ->with('error', 'Tidak ada data untuk ditampilkan');
            }

            $pdf = Pdf::loadView('scanner.history_pdf', compact('scanResults'));
            
            // Nama file berdasarkan filter
            $filename = $request->filled('date') 
                ? "scan_history_{$request->date}.pdf" 
                : "scan_history_all.pdf";

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PDF Error: '.$e->getMessage());
            return redirect()->route('scanner.history')
                ->with('error', 'Gagal membuat PDF: '.$e->getMessage());
        }
    }
}
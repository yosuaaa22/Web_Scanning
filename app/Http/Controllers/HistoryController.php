<?php

namespace App\Http\Controllers;

use App\Models\ScanResult;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class HistoryController extends Controller
{
    public function index()
    {
        $scanResults = ScanResult::orderBy('created_at', 'desc')->paginate(10);
        return view('scanner.history', compact('scanResults'));
    }

    public function downloadPDF()
    {
        $scanResults = ScanResult::orderBy('created_at', 'desc')->get();
        $pdf = Pdf::loadView('scanner.history_pdf', compact('scanResults'));
        return $pdf->download('scan_history.pdf');
    }
}

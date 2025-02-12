<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('scanner.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Validasi input
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        // Coba login
        if (Auth::attempt($credentials, $request->has('remember'))) {
            // Jika berhasil
            Log::info('Login berhasil untuk user: ' . $request->username);
            $request->session()->regenerate();

            // Redirect ke halaman scanner
            return redirect()->route('scanner.index');
        }

        // Jika gagal
        Log::warning('Percobaan login gagal untuk username: ' . $request->username);
        return back()
            ->withErrors([
                'username' => 'Username atau password salah.',
            ])
            ->withInput($request->only('username'));
    }
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * بوابة كلمة المرور المشتركة للدخول للموقع.
 */
class GateController extends Controller
{
    /** عرض صفحة إدخال كلمة المرور. */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('site_gate_passed')) {
            return redirect()->intended(route('home'));
        }

        return Inertia::render('Gate');
    }

    /** التحقق من كلمة المرور وفتح البوابة. */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $expected = (string) config('gate.password');

        if ($expected === '' || ! hash_equals($expected, (string) $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('site_gate_passed', true);

        return redirect()->intended(route('home'));
    }
}

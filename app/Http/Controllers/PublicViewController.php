<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\ContactForm;
use App\Models\MenuItem;

class PublicViewController extends Controller
{
    public function index(): View
    {
        return view('public.home');
    }
    
    public function howItWorksCorporates(): View
    {
        return view('public.how-it-works-corporate');
    }
    
    public function howItWorksKitchen(): View
    {
        return view('public.how-it-works-kitchen');
    }

    public function about(): View
    {
        return view('public.about');
    }
    
    public function contact(): View
    {
        return view('public.contact');
    }

    public function menu(): View
    {
        $menu = MenuItem::query()
            ->where('is_featured', true)
            ->orderBy('display_order')
            ->take(9)
            ->get()
            ->map(function ($m) {
                $image = $m->thumbnail;
                if ($image && !preg_match('/^https?:\/\//', $image)) {
                    $image = asset(ltrim($image, '/'));
                }

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'price' => $m->price,
                    'description' => $m->summary ?? '',
                    'image' => $image ?: asset('img/default.jpg'),
                ];
            });

        return view('public.menu', compact('menu'));
    }

    public function faq(): View
    {
        return view('public.faq');
    }

    public function contactSubmit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'regex:/^(?:\+88|88)?(01[3-9]\d{8})$/'],
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        ContactForm::create($validated);

        return back()->with('success', 'Your message has been queued successfully!');
    }
}
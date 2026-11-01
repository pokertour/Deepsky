<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $subscribers = NewsletterSubscriber::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('email', 'like', '%'.$request->string('search')->trim().'%'))
            ->when($request->string('status')->value() === 'confirmed', fn ($query) => $query->confirmed())
            ->when($request->string('status')->value() === 'pending', fn ($query) => $query->whereNull('confirmed_at'))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.newsletter.index', compact('subscribers'));
    }

    public function resend(NewsletterSubscriber $subscriber, NewsletterConfirmationService $confirmation): RedirectResponse
    {
        abort_if($subscriber->isConfirmed(), 422);

        if (! $confirmation->send($subscriber)) {
            return back()->with('success', __('Un email a déjà été envoyé récemment. Patiente cinq minutes avant de le renvoyer.'));
        }

        return back()->with('success', __('Email de confirmation renvoyé.'));
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', __('Abonné supprimé.'));
    }
}

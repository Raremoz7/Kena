<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function index(): Response
    {
        $venues = Venue::withCount(['seats', 'events'])->orderBy('name')->get()
            ->map(fn (Venue $v): array => [
                'id' => $v->id,
                'name' => $v->name,
                'city' => $v->city,
                'state' => $v->state,
                'seats' => $v->seats_count,
                'events' => $v->events_count,
            ])->all();

        return Inertia::render('admin/venues', ['venues' => $venues]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/venue-form', ['venue' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Venue::create($this->validateData($request));

        return redirect()->route('admin.venues');
    }

    public function edit(Venue $venue): Response
    {
        return Inertia::render('admin/venue-form', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'city' => $venue->city,
                'state' => $venue->state,
                'address' => $venue->address,
                'maps_query' => $venue->maps_query,
                'seatsCount' => $venue->seats()->count(),
                'canEditMap' => ! $venue->events()->exists(),
                'importUrl' => route('admin.venues.seats.import', $venue),
                'generateUrl' => route('admin.venues.seats.generate', $venue),
            ],
        ]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $venue->update($this->validateData($request));

        return redirect()->route('admin.venues');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        if ($venue->events()->exists()) {
            return back()->withErrors(['venue' => 'Não é possível excluir um local com eventos vinculados.']);
        }

        $venue->delete();

        return redirect()->route('admin.venues');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:255'],
            'maps_query' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

<?php

namespace Tests\Feature\Kena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Evento que já aconteceu não dá 404: abre em modo somente leitura e vai para o fim da lista. */
class EndedEventTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_ended_event_page_opens_without_purchase(): void
    {
        $session = $this->makeSession(3);
        $session->update(['starts_at' => now()->subDay()]);

        $this->get(route('events.show', $session->event->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('buyer/event-ended')
                ->where('event.status.label', 'Encerrado')
                ->where('photosUrl', null));
    }

    public function test_listing_marks_ended_and_puts_upcoming_first(): void
    {
        $ended = $this->makeSession(3);
        $ended->update(['starts_at' => now()->subDay()]);
        $upcoming = $this->makeSession(3);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('events.data.0.slug', $upcoming->event->slug)
                ->where('events.data.0.ended', false)
                ->where('events.data.1.slug', $ended->event->slug)
                ->where('events.data.1.ended', true));
    }
}

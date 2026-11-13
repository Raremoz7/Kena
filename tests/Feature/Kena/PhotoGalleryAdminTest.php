<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\PanelUser;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoPerson;
use App\Models\User;
use App\Support\PhotoPriceTiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Área de fotos do painel: acesso, faixas de preço, publicação e pessoas. */
class PhotoGalleryAdminTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    private function event(): Event
    {
        return $this->makeSession()->event;
    }

    private function gallery(?Event $event = null, string $status = 'draft'): PhotoGallery
    {
        return PhotoGallery::create([
            'event_id' => ($event ?? $this->event())->id,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'price_tiers' => [
                ['min_qty' => 1, 'unit_price_cents' => 3500],
                ['min_qty' => 10, 'unit_price_cents' => 2000],
            ],
        ]);
    }

    private function photo(PhotoGallery $gallery, string $name = 'a.jpg'): Photo
    {
        return Photo::create([
            'photo_gallery_id' => $gallery->id,
            'filename' => $name,
            'preview_path' => 'fotos/p-'.$name,
            'thumb_path' => 'fotos/t-'.$name,
            'width' => 800,
            'height' => 600,
        ]);
    }

    private function person(PhotoGallery $gallery, string $name): PhotoPerson
    {
        return PhotoPerson::create(['photo_gallery_id' => $gallery->id, 'name' => $name]);
    }

    /** @return array<string, mixed> */
    private function payload(array $tiers): array
    {
        return ['description' => 'Registros do espetáculo.', 'tiers' => $tiers];
    }

    // --- Acesso -----------------------------------------------------------

    public function test_staff_cannot_reach_any_photo_page(): void
    {
        $staff = PanelUser::factory()->staff()->create();
        $gallery = $this->gallery();
        $event = $gallery->event;
        $person = $this->person($gallery, 'Alice');

        $this->actingAs($staff, 'painel');
        $this->get(route('admin.photos'))->assertForbidden();
        $this->get(route('admin.photos.edit', $event))->assertForbidden();
        $this->get(route('admin.photos.people', $event))->assertForbidden();
        $this->get(route('admin.photos.photos', $event))->assertForbidden();
        $this->put(route('admin.photos.save', $event), $this->payload([['min_qty' => 1, 'price' => '30']]))->assertForbidden();
        $this->post(route('admin.photos.publish', $event))->assertForbidden();
        $this->post(route('admin.photos.unpublish', $event))->assertForbidden();
        $this->put(route('admin.photos.people.rename', $person), ['name' => 'X'])->assertForbidden();
        $this->put(route('admin.photos.people.toggle', $person), ['hidden' => true])->assertForbidden();

        $this->assertSame('draft', $gallery->fresh()->status);
        $this->assertSame('Alice', $person->fresh()->name);
    }

    public function test_buyer_and_guest_are_sent_to_the_panel_login(): void
    {
        $event = $this->gallery()->event;

        $this->actingAs(User::factory()->create())
            ->get(route('admin.photos'))->assertRedirect(route('painel.login'));
        $this->post(route('admin.photos.publish', $event))->assertRedirect(route('painel.login'));
    }

    public function test_organizer_reaches_the_photo_pages(): void
    {
        $gallery = $this->gallery();
        $event = $gallery->event;

        $this->actingAs($this->organizer(), 'painel');
        $this->get(route('admin.photos'))->assertOk();
        $this->get(route('admin.photos.edit', $event))->assertOk();
        $this->get(route('admin.photos.people', $event))->assertOk();
        $this->get(route('admin.photos.photos', $event))->assertOk();
    }

    public function test_people_and_photos_pages_are_404_without_a_gallery(): void
    {
        $event = $this->event();

        $this->actingAs($this->organizer(), 'painel');
        $this->get(route('admin.photos.edit', $event))->assertOk();
        $this->get(route('admin.photos.people', $event))->assertNotFound();
        $this->get(route('admin.photos.photos', $event))->assertNotFound();
    }

    // --- Faixas de preço ----------------------------------------------------

    public function test_valid_tiers_are_saved_in_cents_and_create_a_draft_gallery(): void
    {
        $event = $this->event();

        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.save', $event), $this->payload([
                ['min_qty' => 1, 'price' => '35,00'],
                ['min_qty' => 5, 'price' => '25.50'],
                ['min_qty' => 10, 'price' => '20'],
            ]))
            ->assertSessionHasNoErrors();

        $gallery = PhotoGallery::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('draft', $gallery->status);
        $this->assertSame([
            ['min_qty' => 1, 'unit_price_cents' => 3500],
            ['min_qty' => 5, 'unit_price_cents' => 2550],
            ['min_qty' => 10, 'unit_price_cents' => 2000],
        ], $gallery->price_tiers);
    }

    public function test_first_tier_must_start_at_one(): void
    {
        $event = $this->event();

        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.save', $event), $this->payload([
                ['min_qty' => 2, 'price' => '35'],
                ['min_qty' => 10, 'price' => '20'],
            ]))
            ->assertSessionHasErrors('tiers');

        $this->assertNull(PhotoGallery::where('event_id', $event->id)->first());
    }

    public function test_quantities_must_be_increasing(): void
    {
        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.save', $this->event()), $this->payload([
                ['min_qty' => 1, 'price' => '35'],
                ['min_qty' => 5, 'price' => '25'],
                ['min_qty' => 5, 'price' => '20'],
            ]))
            ->assertSessionHasErrors('tiers');
    }

    public function test_prices_must_decrease(): void
    {
        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.save', $this->event()), $this->payload([
                ['min_qty' => 1, 'price' => '20'],
                ['min_qty' => 10, 'price' => '20'],
            ]))
            ->assertSessionHasErrors('tiers');
    }

    public function test_invalid_price_values_are_rejected(): void
    {
        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.save', $this->event()), $this->payload([
                ['min_qty' => 1, 'price' => 'abc'],
            ]))
            ->assertSessionHasErrors('tiers.0.price');

        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.save', $this->event()), ['tiers' => []])
            ->assertSessionHasErrors('tiers');
    }

    public function test_tier_rules_directly(): void
    {
        $ok = [
            ['min_qty' => 1, 'unit_price_cents' => 3500],
            ['min_qty' => 10, 'unit_price_cents' => 2000],
        ];
        $this->assertSame([], PhotoPriceTiers::errors($ok));
        $this->assertSame([], PhotoPriceTiers::errors([['min_qty' => 1, 'unit_price_cents' => 3500]]));

        $this->assertNotEmpty(PhotoPriceTiers::errors([]));
        $this->assertNotEmpty(PhotoPriceTiers::errors([['min_qty' => 3, 'unit_price_cents' => 3500]]));
        $this->assertNotEmpty(PhotoPriceTiers::errors([
            ['min_qty' => 1, 'unit_price_cents' => 3500],
            ['min_qty' => 1, 'unit_price_cents' => 2000],
        ]));
        $this->assertNotEmpty(PhotoPriceTiers::errors([
            ['min_qty' => 1, 'unit_price_cents' => 2000],
            ['min_qty' => 5, 'unit_price_cents' => 2500],
        ]));
    }

    // --- Publicação -------------------------------------------------------------

    public function test_cannot_publish_an_empty_gallery(): void
    {
        $gallery = $this->gallery();

        $this->actingAs($this->organizer(), 'painel')
            ->post(route('admin.photos.publish', $gallery->event))
            ->assertSessionHasErrors('publish');

        $this->assertSame('draft', $gallery->fresh()->status);
    }

    public function test_publish_and_unpublish_toggle_the_public_listing(): void
    {
        $gallery = $this->gallery();
        $this->photo($gallery);
        $event = $gallery->event;
        $organizer = $this->organizer();

        $this->get('/fotos/'.$event->slug)->assertNotFound();

        $this->actingAs($organizer, 'painel')->post(route('admin.photos.publish', $event))->assertRedirect();
        $this->assertSame('published', $gallery->fresh()->status);
        $this->assertNotNull($gallery->fresh()->published_at);
        $this->get('/fotos/'.$event->slug)->assertOk();

        $this->actingAs($organizer, 'painel')->post(route('admin.photos.unpublish', $event))->assertRedirect();
        $this->assertSame('draft', $gallery->fresh()->status);
        $this->get('/fotos/'.$event->slug)->assertNotFound();
    }

    // --- Pessoas -------------------------------------------------------------------

    public function test_organizer_renames_a_person_but_not_into_an_existing_name(): void
    {
        $gallery = $this->gallery();
        $alice = $this->person($gallery, 'Alice M.');
        $this->person($gallery, 'Bia');
        $organizer = $this->organizer();

        $this->actingAs($organizer, 'painel')
            ->put(route('admin.photos.people.rename', $alice), ['name' => 'Alice Moura'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Alice Moura', $alice->fresh()->name);

        $this->actingAs($organizer, 'painel')
            ->put(route('admin.photos.people.rename', $alice), ['name' => 'Bia'])
            ->assertSessionHasErrors('name');
        $this->assertSame('Alice Moura', $alice->fresh()->name);
    }

    public function test_merge_moves_photos_and_removes_the_source_person(): void
    {
        $gallery = $this->gallery();
        $short = $this->person($gallery, 'Alice M.');
        $full = $this->person($gallery, 'Alice Moura');
        [$p1, $p2, $p3] = [$this->photo($gallery, '1.jpg'), $this->photo($gallery, '2.jpg'), $this->photo($gallery, '3.jpg')];
        $short->photos()->attach([$p1->id, $p2->id]);
        $full->photos()->attach([$p2->id, $p3->id]);

        $this->actingAs($this->organizer(), 'painel')
            ->post(route('admin.photos.people.merge', $short), ['into' => $full->id])
            ->assertSessionHasNoErrors();

        $this->assertNull(PhotoPerson::find($short->id));
        $this->assertEqualsCanonicalizing(
            [$p1->id, $p2->id, $p3->id],
            $full->photos()->pluck('photos.id')->all(),
        );
    }

    public function test_cannot_merge_with_a_person_from_another_gallery(): void
    {
        $mine = $this->person($this->gallery(), 'Alice');
        $other = $this->person($this->gallery(), 'Outra');

        $this->actingAs($this->organizer(), 'painel')
            ->post(route('admin.photos.people.merge', $mine), ['into' => $other->id])
            ->assertSessionHasErrors('into');

        $this->assertNotNull(PhotoPerson::find($mine->id));
    }

    public function test_hidden_person_leaves_the_public_filter_but_keeps_the_photos(): void
    {
        $gallery = $this->gallery(status: 'published');
        $photo = $this->photo($gallery);
        $alice = $this->person($gallery, 'Alice');
        $bia = $this->person($gallery, 'Bia');
        $photo->people()->attach([$alice->id, $bia->id]);
        $slug = $gallery->event->slug;

        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.people.toggle', $alice), ['hidden' => true])
            ->assertRedirect();

        $this->get('/fotos/'.$slug)->assertInertia(fn ($page) => $page
            ->has('people', 1)
            ->where('people.0.name', 'Bia')
            ->has('photos', 1));

        $this->actingAs($this->organizer(), 'painel')
            ->put(route('admin.photos.people.toggle', $alice), ['hidden' => false]);

        $this->get('/fotos/'.$slug)->assertInertia(fn ($page) => $page->has('people', 2));
    }

    // --- Conferência das fotos -------------------------------------------------------

    public function test_photos_page_filters_by_group(): void
    {
        $gallery = $this->gallery();
        $group = $gallery->groups()->create(['name' => 'Turma A', 'position' => 1]);
        $this->photo($gallery, '1.jpg')->update(['photo_group_id' => $group->id]);
        $this->photo($gallery, '2.jpg');

        $this->actingAs($this->organizer(), 'painel');

        $this->get(route('admin.photos.photos', ['event' => $gallery->event, 'turma' => $group->id]))
            ->assertInertia(fn ($page) => $page->has('photos.data', 1)->where('filter', (string) $group->id));
        $this->get(route('admin.photos.photos', ['event' => $gallery->event, 'turma' => 'sem']))
            ->assertInertia(fn ($page) => $page->has('photos.data', 1)->where('noGroupCount', 1));
        $this->get(route('admin.photos.photos', $gallery->event))
            ->assertInertia(fn ($page) => $page->has('photos.data', 2)->where('totalCount', 2));
    }
}

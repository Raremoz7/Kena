<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\PanelUser;
use App\Models\PhotoGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Código de acesso escolhido no painel e o botão de importar da galeria vazia. */
class PhotoGalleryAccessCodeTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private PanelUser $panel;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = PanelUser::factory()->create();
        $this->event = $this->makeSession()->event;
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return [
            'description' => '',
            'tiers' => [['min_qty' => 1, 'price' => '35']],
            ...$extra,
        ];
    }

    private function save(array $extra = [])
    {
        return $this->actingAs($this->panel, 'painel')
            ->put("/painel/fotos/{$this->event->id}", $this->payload($extra));
    }

    public function test_new_gallery_without_code_gets_a_random_one(): void
    {
        $this->save()->assertSessionHasNoErrors();

        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{6}$/', PhotoGallery::firstOrFail()->access_code);
    }

    public function test_organizer_sets_a_custom_code(): void
    {
        $this->save(['access_code' => 'quebra nozes 2026'])->assertSessionHasNoErrors();

        $gallery = PhotoGallery::firstOrFail();
        $this->assertSame('QUEBRA-NOZES-2026', $gallery->access_code);
        $this->assertTrue($gallery->acceptsCode('quebranozes2026'));
    }

    public function test_blank_code_keeps_the_current_one(): void
    {
        $this->save(['access_code' => 'BALLET-2026']);
        $this->save(['access_code' => '', 'description' => 'Outra']);

        $this->assertSame('BALLET-2026', PhotoGallery::firstOrFail()->access_code);
    }

    public function test_short_or_letters_only_code_is_rejected(): void
    {
        $this->save(['access_code' => 'NOZES1'])->assertSessionHasErrors('access_code');
        $this->save(['access_code' => 'SOMENTELETRAS'])->assertSessionHasErrors('access_code');
        $this->save(['access_code' => 'NOZES#2026'])->assertSessionHasErrors('access_code');
    }

    public function test_code_of_another_gallery_is_rejected(): void
    {
        $other = $this->makeSession(1)->event;
        PhotoGallery::create([
            'event_id' => $other->id, 'status' => 'draft', 'access_code' => 'TUTU-123456',
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]],
        ]);

        $this->save(['access_code' => 'tutu 123456'])->assertSessionHasErrors('access_code');
    }

    public function test_settings_show_the_current_code(): void
    {
        $this->save(['access_code' => 'BALLET-2026']);

        $this->actingAs($this->panel, 'painel')->get("/painel/fotos/{$this->event->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('form.access_code', 'BALLET-2026'));
    }

    public function test_empty_photos_tab_knows_the_drive_state(): void
    {
        $this->save(['drive_folder' => 'https://drive.google.com/drive/folders/10_LftOB8Q1ypE5y2pwYXl72iu0T5ZbYf']);

        $this->actingAs($this->panel, 'painel')->get("/painel/fotos/{$this->event->id}/galeria")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('admin/photo-photos')
                ->where('totalCount', 0)
                ->where('drive.hasFolder', true));
    }
}

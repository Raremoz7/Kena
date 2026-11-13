<?php

namespace Tests\Unit;

use App\Services\Photos\LightroomFixtureWriter;
use App\Services\Photos\XmpReader;
use PHPUnit\Framework\TestCase;

class XmpReaderTest extends TestCase
{
    private const ALICE = ['x' => 0.31, 'y' => 0.42, 'w' => 0.1, 'h' => 0.15];

    private const CECILIA = ['x' => 0.7, 'y' => 0.4, 'w' => 0.09, 'h' => 0.14];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/kena-xmp-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function test_reads_names_and_regions_from_lightroom_xmp(): void
    {
        $xmp = LightroomFixtureWriter::xmp(['Alice Moura' => self::ALICE, 'Cecília Ramos' => self::CECILIA], ['Palco']);

        [$people, $orientation] = (new XmpReader)->parse($xmp);

        $this->assertCount(2, $people);
        $this->assertSame('Alice Moura', $people[0]->name);
        $this->assertSame(self::ALICE, $people[0]->region);
        $this->assertSame('Cecília Ramos', $people[1]->name);
        $this->assertSame(self::CECILIA, $people[1]->region);
        $this->assertSame(1, $orientation);
    }

    public function test_region_wins_by_name_and_keyword_only_people_are_added_without_crop(): void
    {
        $xmp = LightroomFixtureWriter::xmp(['Alice Moura' => self::ALICE, 'Helena Costa' => null]);

        [$people] = (new XmpReader)->parse($xmp);

        $this->assertSame(['Alice Moura', 'Helena Costa'], array_map(fn ($p) => $p->name, $people));
        $this->assertSame(self::ALICE, $people[0]->region);
        $this->assertNull($people[1]->region);
    }

    public function test_without_regions_uses_only_person_keywords_without_crop(): void
    {
        $xmp = LightroomFixtureWriter::xmp(['Cecília Ramos' => null, 'Júlia Carvalho' => null], ['Palco', 'Baby Class']);

        [$people] = (new XmpReader)->parse($xmp);

        $this->assertSame(['Cecília Ramos', 'Júlia Carvalho'], array_map(fn ($p) => $p->name, $people));
        $this->assertNull($people[0]->region);
        $this->assertNull($people[1]->region);
    }

    public function test_plain_keywords_outside_the_people_folder_are_not_people(): void
    {
        $xmp = LightroomFixtureWriter::xmp(['Alice Moura' => null], ['Palco'], personFolder: false);

        [$people] = (new XmpReader)->parse($xmp);

        $this->assertSame([], $people);
    }

    public function test_ignores_unnamed_faces_and_other_region_types(): void
    {
        $xmp = $this->wrap(<<<'XML'
<mwg-rs:Regions rdf:parseType="Resource"><mwg-rs:RegionList><rdf:Bag>
 <rdf:li><rdf:Description mwg-rs:Type="Face"><mwg-rs:Area stArea:x="0.5" stArea:y="0.5" stArea:w="0.1" stArea:h="0.1" stArea:unit="normalized"/></rdf:Description></rdf:li>
 <rdf:li><rdf:Description mwg-rs:Name="Rex" mwg-rs:Type="Pet"><mwg-rs:Area stArea:x="0.2" stArea:y="0.2" stArea:w="0.1" stArea:h="0.1" stArea:unit="normalized"/></rdf:Description></rdf:li>
</rdf:Bag></mwg-rs:RegionList></mwg-rs:Regions>
XML);

        [$people] = (new XmpReader)->parse($xmp);

        $this->assertSame([], $people);
    }

    public function test_reads_element_style_regions(): void
    {
        $xmp = $this->wrap(<<<'XML'
<mwg-rs:Regions rdf:parseType="Resource"><mwg-rs:RegionList><rdf:Bag>
 <rdf:li rdf:parseType="Resource">
  <mwg-rs:Name>Lívia Teixeira</mwg-rs:Name><mwg-rs:Type>Face</mwg-rs:Type>
  <mwg-rs:Area rdf:parseType="Resource"><stArea:x>0.25</stArea:x><stArea:y>0.3</stArea:y><stArea:w>0.08</stArea:w><stArea:h>0.12</stArea:h><stArea:unit>normalized</stArea:unit></mwg-rs:Area>
 </rdf:li>
</rdf:Bag></mwg-rs:RegionList></mwg-rs:Regions>
XML);

        [$people] = (new XmpReader)->parse($xmp);

        $this->assertSame('Lívia Teixeira', $people[0]->name);
        $this->assertSame(['x' => 0.25, 'y' => 0.3, 'w' => 0.08, 'h' => 0.12], $people[0]->region);
    }

    public function test_decomposed_accents_are_normalized(): void
    {
        $decomposed = "Ce\u{0301}cilia"; // "Cécilia" com acento separado (NFD)
        $xmp = LightroomFixtureWriter::xmp([$decomposed => self::CECILIA]);

        [$people] = (new XmpReader)->parse($xmp);

        $this->assertSame("C\u{00E9}cilia", $people[0]->name);
    }

    public function test_garbage_xmp_yields_nothing(): void
    {
        [$people, $orientation] = (new XmpReader)->parse('<x:xmpmeta><broken');

        $this->assertSame([], $people);
        $this->assertNull($orientation);
    }

    public function test_reads_people_and_exif_orientation_from_a_jpeg_file(): void
    {
        $path = $this->jpeg(['Alice Moura' => self::ALICE], 6);

        $metadata = (new XmpReader)->read($path);

        $this->assertSame(6, $metadata->orientation);
        $this->assertSame('Alice Moura', $metadata->people[0]->name);
        $this->assertSame(self::ALICE, $metadata->people[0]->region);
    }

    public function test_jpeg_without_metadata_has_no_people_and_upright_orientation(): void
    {
        $path = $this->dir.'/plain.jpg';
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, $path);

        $metadata = (new XmpReader)->read($path);

        $this->assertSame([], $metadata->people);
        $this->assertSame(1, $metadata->orientation);
    }

    public function test_not_a_jpeg_is_empty(): void
    {
        file_put_contents($this->dir.'/x.jpg', 'isto nao e uma imagem');

        $metadata = (new XmpReader)->read($this->dir.'/x.jpg');

        $this->assertSame([], $metadata->people);
        $this->assertSame(1, $metadata->orientation);
    }

    /** @param  array<string, array{x: float, y: float, w: float, h: float}|null>  $people */
    private function jpeg(array $people, int $orientation = 1): string
    {
        $path = $this->dir.'/foto.jpg';
        LightroomFixtureWriter::write($path, imagecreatetruecolor(60, 40), $people, [], $orientation);

        return $path;
    }

    private function wrap(string $description): string
    {
        return <<<XML
<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
<rdf:Description rdf:about="" xmlns:mwg-rs="http://www.metadataworkinggroup.com/schemas/regions/" xmlns:stArea="http://ns.adobe.com/xmp/sType/Area#">
{$description}
</rdf:Description></rdf:RDF></x:xmpmeta>
XML;
    }
}

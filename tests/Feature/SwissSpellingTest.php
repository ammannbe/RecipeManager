<?php

namespace Tests\Feature;

use App\Services\SwissSpelling;
use Tests\TestCase;

class SwissSpellingTest extends TestCase
{
    public function test_addresses_and_author_names_stay_as_they_are(): void
    {
        $recipe = SwissSpelling::recipe([
            'name' => 'Großes Gratin',
            'author' => 'Hans Groß',
            'source' => 'Nach https://example.com/süß-und-groß?x=ß, siehe www.straße.de/rezept. Gruß',
            'instructions' => '1. In Weißwein köcheln (http://example.com/weiß).',
            'photos' => [['url' => 'https://example.com/groß.jpg']],
        ]);

        $this->assertSame('Grosses Gratin', $recipe['name']);
        $this->assertSame('Hans Groß', $recipe['author']);
        $this->assertSame('Nach https://example.com/süß-und-groß?x=ß, siehe www.straße.de/rezept. Gruss', $recipe['source']);
        $this->assertSame('1. In Weisswein köcheln (http://example.com/weiß).', $recipe['instructions']);
        $this->assertSame('https://example.com/groß.jpg', $recipe['photos'][0]['url']);
        $this->assertSame('GROSS', SwissSpelling::text('GROẞ'));
    }
}

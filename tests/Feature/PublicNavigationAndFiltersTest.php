<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Tests\TestCase;

class PublicNavigationAndFiltersTest extends TestCase
{
    public function test_the_navigation_links_the_public_pages_and_marks_the_current_one(): void
    {
        $html = (string) $this->get(route('recipes.season'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('recipes.index').'"', $html);
        // Desktop and mobile navigation each mark the season calendar, nothing else.
        preg_match_all('#href="([^"]+)"[^>]*aria-current="page"#', $html, $current);
        $this->assertSame([route('recipes.season'), route('recipes.season')], $current[1]);
        $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
        $this->assertStringContainsString('id="mobile-navigation"', $html);
        $this->assertStringContainsString(url('/admin/login'), $html);
    }

    public function test_signed_in_users_get_the_backend_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee(url('/admin'), false)
            ->assertDontSee(url('/admin/login'), false);
    }

    public function test_no_chips_are_rendered_without_active_filters(): void
    {
        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertDontSee('data-active-filters', false)
            ->assertSee('recipeFilters({ expanded: false })', false);
    }

    public function test_active_filters_are_rendered_as_chips_linking_to_the_url_without_them(): void
    {
        $category = Category::factory()->create(['name' => 'Desserts']);
        $vegan = Tag::factory()->create(['name' => 'Vegan']);
        $quickTag = Tag::factory()->create(['name' => 'Schnell']);
        Recipe::factory()->create(['cookbook_id' => null, 'is_public' => true, 'category_id' => $category->id]);

        $filters = [
            'search' => 'Torte',
            'quick' => 1,
            'category' => $category->id,
            'sort' => 'name_asc',
            'tags' => [$vegan->id, $quickTag->id],
        ];

        $response = $this->get(route('recipes.index', [...$filters, 'page' => 1]))->assertOk();
        $chips = $this->chips((string) $response->getContent());

        $this->assertSame(['search', 'quick', 'category', 'tags', 'tags', 'sort'], array_column($chips, 'key'));
        $this->assertSame(['«Torte»', __('Max. 30 min'), 'Desserts', 'Schnell', 'Vegan', __('Name A-Z')], array_column($chips, 'label'));

        $expected = [
            array_diff_key($filters, ['search' => true]),
            array_diff_key($filters, ['quick' => true]),
            array_diff_key($filters, ['category' => true]),
            [...$filters, 'tags' => [$vegan->id]],
            [...$filters, 'tags' => [$quickTag->id]],
            array_diff_key($filters, ['sort' => true]),
        ];

        foreach ($chips as $index => $chip) {
            parse_str((string) parse_url($chip['url'], PHP_URL_QUERY), $query);

            $this->assertEquals($this->normalize($expected[$index]), $this->normalize($query), $chip['key']);
        }

        $response->assertSee(__('Reset all'));
        $response->assertSee('recipeFilters({ expanded: true })', false);
    }

    public function test_the_last_tag_chip_drops_the_tags_parameter_and_invalid_values_get_no_chip(): void
    {
        $tag = Tag::factory()->create(['name' => 'Vegan']);

        $html = (string) $this->get(route('recipes.index', ['tags' => [$tag->id], 'complexity' => 'impossible', 'sort' => 'nonsense']))
            ->assertOk()
            ->getContent();
        $chips = $this->chips($html);

        $this->assertSame(['tags'], array_column($chips, 'key'));
        $this->assertSame(route('recipes.index'), $chips[0]['url']);
    }

    public function test_seasonal_and_difficulty_chips(): void
    {
        $chips = $this->chips((string) $this->get(route('recipes.index', ['seasonal' => 1, 'complexity' => 'simple']))->getContent());

        $this->assertSame([__('Seasonal'), __('Simple')], array_column($chips, 'label'));
        $this->assertSame(route('recipes.index', ['complexity' => 'simple']), $chips[0]['url']);
        $this->assertSame(route('recipes.index', ['seasonal' => 1]), $chips[1]['url']);
    }

    /**
     * @return array<int, array{key: string, label: string, url: string}>
     */
    private function chips(string $html): array
    {
        preg_match_all('#<a\s+href="([^"]*)"[^>]*data-filter-chip="([^"]+)"\s*>\s*<span class="truncate">([^<]*)</span>#', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [
            'key' => $match[2],
            'label' => html_entity_decode($match[3]),
            'url' => html_entity_decode($match[1]),
        ], $matches);
    }

    /**
     * @param  array<int|string, mixed>  $query
     * @return array<int|string, mixed>
     */
    private function normalize(array $query): array
    {
        ksort($query);

        return array_map(function (mixed $value): mixed {
            if (! is_array($value)) {
                return (string) $value;
            }

            $values = array_map('strval', array_values($value));
            sort($values);

            return $values;
        }, $query);
    }
}

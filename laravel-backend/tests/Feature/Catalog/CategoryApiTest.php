<?php

declare(strict_types=1);

use App\Models\Category;

it('lists only active categories ordered by position then title', function () {
    Category::factory()->create(['title' => 'Beta', 'status' => true, 'position_order' => 2]);
    Category::factory()->create(['title' => 'Alpha', 'status' => true, 'position_order' => 1]);
    Category::factory()->inactive()->create(['title' => 'Hidden']);

    $response = $this->getJson('/api/v1/categories')->assertOk();

    $titles = array_column($response->json('data'), 'title');

    expect($titles)->toBe(['Alpha', 'Beta']);
});

it('fetches a single active category by slug', function () {
    $category = Category::factory()->create(['slug' => 'lovinna-chair', 'status' => true]);

    $this->getJson('/api/v1/categories/lovinna-chair')
        ->assertOk()
        ->assertJsonPath('data.id', $category->id);
});

it('returns 404 for an inactive category', function () {
    Category::factory()->inactive()->create(['slug' => 'hidden-cat']);

    $this->getJson('/api/v1/categories/hidden-cat')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionRoleSeeder::class);
});

function catalogManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has catalog.manage

    return $user;
}

it('blocks users without catalog.manage from creating', function () {
    $user = User::factory()->create();
    $user->assignRole('marketer'); // only marketing.manage

    actingAs($user)
        ->post('/admin/catalog/categories', ['title' => 'Sneaky'])
        ->assertForbidden();
});

it('lists categories for staff with catalog.view', function () {
    Category::factory()->create(['title' => 'Chairs']);

    actingAs(catalogManager())
        ->get('/admin/catalog/categories')
        ->assertOk();
});

it('creates a category with auto slug', function () {
    actingAs(catalogManager())
        ->post('/admin/catalog/categories', [
            'title' => 'Living Room',
            'status' => '1',
            'position_order' => 2,
        ])
        ->assertRedirect(route('admin.categories.index'));

    $category = Category::query()->where('title', 'Living Room')->first();
    expect($category)->not->toBeNull();
    expect($category->slug)->toBe('living-room');
    expect($category->status)->toBeTrue();
});

it('uploads category images and stores their paths', function () {
    Storage::fake('public');

    actingAs(catalogManager())
        ->post('/admin/catalog/categories', [
            'title' => 'Tables',
            'header_image' => UploadedFile::fake()->image('h.png', 300, 120),
            'thumbnail_image' => UploadedFile::fake()->image('t.png', 120, 120),
        ])
        ->assertRedirect(route('admin.categories.index'));

    $category = Category::query()->where('title', 'Tables')->firstOrFail();
    expect($category->header_image)->not->toBeNull();
    expect($category->thumbnail_image)->not->toBeNull();
    Storage::disk('public')->assertExists($category->header_image);
    Storage::disk('public')->assertExists($category->thumbnail_image);
});

it('rejects an svg category image', function () {
    actingAs(catalogManager())
        ->post('/admin/catalog/categories', [
            'title' => 'Bad',
            'header_image' => UploadedFile::fake()->create('x.svg', 4, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('header_image');
});

it('updates a category', function () {
    $category = Category::factory()->create(['title' => 'Old']);

    actingAs(catalogManager())
        ->put("/admin/catalog/categories/{$category->id}", [
            'title' => 'New name',
            'status' => '1',
            'position_order' => 0,
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->refresh()->title)->toBe('New name');
});

it('soft-deletes a category', function () {
    $category = Category::factory()->create();

    actingAs(catalogManager())
        ->delete("/admin/catalog/categories/{$category->id}")
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::query()->find($category->id))->toBeNull();
    expect(Category::withTrashed()->find($category->id))->not->toBeNull();
});

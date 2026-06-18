<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use Database\Seeders\PermissionRoleSeeder;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function manager(): User
{
    $user = User::factory()->create();
    $user->assignRole('manager'); // has catalog.manage

    return $user;
}

it('creates a category with an auto-generated unique slug', function () {
    $category = app(CategoryService::class)->create([
        'title' => 'Lovinna Chair',
        'status' => true,
    ]);

    expect($category->slug)->toBe('lovinna-chair');
});

it('generates a distinct slug when the base already exists', function () {
    Category::factory()->create(['slug' => 'lovinna-chair']);

    $category = app(CategoryService::class)->create(['title' => 'Lovinna Chair']);

    expect($category->slug)->toBe('lovinna-chair-2');
});

it('lets an authorized user create a category over http', function () {
    $this->actingAs(manager())
        ->postJson('/admin/categories', ['title' => 'Tables', 'status' => true])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'tables');
});

it('rejects a duplicate explicit slug with a validation error', function () {
    Category::factory()->create(['slug' => 'tables']);

    $this->actingAs(manager())
        ->postJson('/admin/categories', ['title' => 'Tables', 'slug' => 'tables'])
        ->assertStatus(422);
});

it('forbids an unauthorized user from creating a category', function () {
    $user = User::factory()->create();
    $user->assignRole('marketer'); // no catalog.manage

    $this->actingAs($user)
        ->postJson('/admin/categories', ['title' => 'Nope'])
        ->assertForbidden();

    expect(Category::count())->toBe(0);
});

it('audits a category update', function () {
    $category = Category::factory()->create(['title' => 'Old']);

    app(CategoryService::class)->update($category, ['title' => 'New']);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => Category::class,
        'subject_id' => $category->id,
        'event' => 'updated',
    ]);
});

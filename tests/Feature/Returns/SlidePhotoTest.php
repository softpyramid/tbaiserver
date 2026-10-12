<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\SlidePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('slides');
    Storage::fake('public');
});

function slideStaff(Facility $facility, TeamRole $role = TeamRole::Member): User
{
    $user = User::factory()->create();
    $facility->team->members()->attach($user, ['role' => $role->value]);

    return $user;
}

function slideReturn(Facility $facility, User $user, ?string $date = null): DailyReturn
{
    return DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $user->id,
        'reported_on' => $date ?? now()->toDateString(),
    ]);
}

function slideAdmin(): User
{
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    return $admin;
}

function slideUpload(User $user, Facility $facility, DailyReturn $return, array $photos, array $labels = [])
{
    return test()->actingAs($user)
        ->from(route('returns.today', $facility->team))
        ->post(route('returns.slides.store', ['current_team' => $facility->team->slug, 'dailyReturn' => $return->id]), [
            'photos' => $photos,
            'labels' => $labels,
        ]);
}

test('a lab member uploads slide photos with labels to a private disk with thumbnails', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);
    $return = slideReturn($facility, $member);

    slideUpload($member, $facility, $return, [
        UploadedFile::fake()->image('Patient Ali Khan 12345.jpg', 1600, 1200),
        UploadedFile::fake()->image('slide2.png', 800, 600),
    ], ['S-12', ''])->assertRedirect(route('returns.today', $facility->team))->assertSessionHasNoErrors();

    $photos = SlidePhoto::query()->orderBy('id')->get();

    expect($photos)->toHaveCount(2)
        ->and($photos[0]->label)->toBe('S-12')
        ->and($photos[1]->label)->toBeNull()
        ->and($photos[0]->disk)->toBe('slides')
        ->and($photos[0]->facility_id)->toBe($facility->id)
        ->and($photos[0]->uploaded_by)->toBe($member->id)
        ->and($photos[0]->mime_type)->toBe('image/jpeg')
        ->and($photos[0]->width)->toBe(1600)
        ->and($photos[0]->path)->not->toContain('Ali')
        ->and($photos[0]->thumbnail_path)->not->toBeNull();

    Storage::disk('slides')->assertExists([$photos[0]->path, $photos[0]->thumbnail_path, $photos[1]->path]);
    expect(Storage::disk('public')->allFiles())->toBe([]);

    [$thumbWidth] = getimagesizefromstring(Storage::disk('slides')->get($photos[0]->thumbnail_path));
    expect($thumbWidth)->toBe(480);

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('slides.needsSavedReturn', false)
            ->where('slides.uploadUrl', route('returns.slides.store', ['current_team' => $facility->team->slug, 'dailyReturn' => $return->id]))
            ->has('slides.photos', 2)
            ->where('slides.photos.0.label', 'S-12')
            ->where('slides.photos.0.url', route('slide-photos.show', $photos[0]))
            ->where('slides.photos.0.thumbnailUrl', route('slide-photos.thumbnail', $photos[0]))
            ->whereNot('slides.photos.0.deleteUrl', null));
});

test('photos need a saved return first', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('slides.needsSavedReturn', true)
            ->where('slides.uploadUrl', null)
            ->where('slides.photos', []));
});

test('uploads are validated for type, size, count and label length', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);
    $return = slideReturn($facility, $member);

    slideUpload($member, $facility, $return, [])->assertSessionHasErrors('photos');
    slideUpload($member, $facility, $return, [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('photos.0');
    slideUpload($member, $facility, $return, [UploadedFile::fake()->createWithContent('fake.jpg', 'not really an image')])
        ->assertSessionHasErrors('photos.0');
    slideUpload($member, $facility, $return, [UploadedFile::fake()->image('big.jpg')->size(10241)])
        ->assertSessionHasErrors('photos.0');
    slideUpload($member, $facility, $return, array_map(fn (int $i) => UploadedFile::fake()->image("s{$i}.jpg", 10, 10), range(1, 11)))
        ->assertSessionHasErrors('photos');
    slideUpload($member, $facility, $return, [UploadedFile::fake()->image('ok.jpg', 10, 10)], [str_repeat('x', 61)])
        ->assertSessionHasErrors('labels.0');

    slideUpload($member, $facility, $return, [UploadedFile::fake()->image('webp.webp', 20, 20)])->assertSessionHasNoErrors();

    expect(SlidePhoto::query()->count())->toBe(1);
});

test('only staff who may edit the return date can upload', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);
    $other = Facility::factory()->create();
    $outsider = slideStaff($other, TeamRole::Owner);
    $return = slideReturn($facility, $member);
    $old = slideReturn($facility, $member, now()->subDays(3)->toDateString());
    $otherReturn = slideReturn($other, $outsider);
    $photo = fn () => [UploadedFile::fake()->image('s.jpg', 10, 10)];

    slideUpload($outsider, $facility, $return, $photo())->assertForbidden();
    slideUpload($member, $facility, $old, $photo())->assertForbidden();
    slideUpload($member, $facility, $otherReturn, $photo())->assertForbidden();
    slideUpload(slideAdmin(), $facility, $return, $photo())->assertForbidden();

    $owner = slideStaff($facility, TeamRole::Owner);
    slideUpload($owner, $facility, $old, $photo())->assertRedirect();

    expect(SlidePhoto::query()->count())->toBe(1);
});

test('viewing a photo needs the admin role or membership of the facility', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);
    $colleague = slideStaff($facility);
    $outsider = slideStaff(Facility::factory()->create(), TeamRole::Owner);
    $return = slideReturn($facility, $member);
    slideUpload($member, $facility, $return, [UploadedFile::fake()->image('s.jpg', 600, 400)]);
    $photo = SlidePhoto::query()->firstOrFail();

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $facility->city_id,
    ]);

    auth()->logout();
    $this->get(route('slide-photos.show', $photo))->assertRedirect(route('login'));

    $this->actingAs($member)->get(route('slide-photos.show', $photo))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($this->actingAs($member)->get(route('slide-photos.show', $photo))->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs($colleague)->get(route('slide-photos.thumbnail', $photo))->assertOk();
    $this->actingAs(slideAdmin())->get(route('slide-photos.show', $photo))->assertOk();
    $this->actingAs($outsider)->get(route('slide-photos.show', $photo))->assertForbidden();
    $this->actingAs($outsider)->get(route('slide-photos.thumbnail', $photo))->assertForbidden();
    $this->actingAs($officer)->get(route('slide-photos.show', $photo))->assertForbidden();
});

test('the uploader or an admin can delete a photo and its files, a colleague cannot', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);
    $colleague = slideStaff($facility);
    $return = slideReturn($facility, $member);
    slideUpload($member, $facility, $return, [
        UploadedFile::fake()->image('a.jpg', 300, 200),
        UploadedFile::fake()->image('b.jpg', 300, 200),
    ]);
    [$first, $second] = SlidePhoto::query()->orderBy('id')->get()->all();
    $destroy = fn (SlidePhoto $photo) => route('returns.slides.destroy', ['current_team' => $facility->team->slug, 'slidePhoto' => $photo->id]);

    $this->actingAs($colleague)->delete($destroy($first))->assertForbidden();

    $this->actingAs($colleague)
        ->get(route('returns.today', $facility->team))
        ->assertInertia(fn (Assert $page) => $page->where('slides.photos.0.deleteUrl', null));

    $paths = [$first->path, $first->thumbnail_path];
    $this->actingAs($member)->delete($destroy($first))->assertRedirect();

    expect(SlidePhoto::query()->whereKey($first->id)->exists())->toBeFalse();
    Storage::disk('slides')->assertMissing($paths);

    $otherTeam = Facility::factory()->create()->team;
    $this->actingAs(slideAdmin())
        ->delete(route('returns.slides.destroy', ['current_team' => $otherTeam->slug, 'slidePhoto' => $second->id]))
        ->assertNotFound();
    $this->actingAs(slideAdmin())->delete($destroy($second))->assertRedirect();

    expect(SlidePhoto::query()->count())->toBe(0);
});

test('the admin return detail lists slide photos and a programme viewer gets no section', function () {
    $facility = Facility::factory()->create();
    $member = slideStaff($facility);
    $return = slideReturn($facility, $member);
    slideUpload($member, $facility, $return, [UploadedFile::fake()->image('a.jpg', 300, 200)], ['S-1']);

    $this->actingAs(slideAdmin())
        ->get(route('admin.daily-returns.show', $return))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('slidePhotosTitle', 'Slide photos')
            ->has('slidePhotos', 1)
            ->where('slidePhotos.0.label', 'S-1')
            ->where('slidePhotos.0.deleteUrl', null));

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $facility->city_id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.returns.show', $return))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('slidePhotos', null));
});

test('an admin still gets the empty slide photo section when a return has no photos', function () {
    $facility = Facility::factory()->create();
    $return = slideReturn($facility, slideStaff($facility));

    $this->actingAs(slideAdmin())
        ->get(route('command.returns.show', $return))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('slidePhotos', []));
});

test('the page shows the patient privacy notice string', function () {
    expect(__('entry.slide_photos_privacy'))->toContain('Do not include patient names')
        ->and(__('entry.slide_photos_privacy'))->not->toContain('—');
});

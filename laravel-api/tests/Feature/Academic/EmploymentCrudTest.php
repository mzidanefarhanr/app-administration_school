<?php

use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->plainUser = $this->makeUserWithRole();
});

it('lists, creates, shows and updates employments with description', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/employments', ['name' => 'Wakil Kepala Sarpras', 'description' => 'Mengelola sarana'])
        ->assertCreated()
        ->json('data');

    expect($created['name'])->toBe('Wakil Kepala Sarpras')
        ->and($created['description'])->toBe('Mengelola sarana');

    $this->getJson('/api/employments')->assertOk()->assertJsonFragment(['name' => 'Wakil Kepala Sarpras']);
    $this->getJson('/api/employments/'.$created['id'])->assertOk()->assertJsonFragment(['description' => 'Mengelola sarana']);

    $this->putJson('/api/employments/'.$created['id'], ['name' => 'Wakil Kepala Sarana', 'description' => ''])
        ->assertOk();

    $fresh = Employment::find($created['id']);
    expect($fresh->name)->toBe('Wakil Kepala Sarana')->and($fresh->description)->toBe('None');
});

it('rejects duplicate and too short names', function () {
    Sanctum::actingAs($this->operator);

    Employment::create(['name' => 'Bendahara', 'description' => 'None']);

    $this->postJson('/api/employments', ['name' => 'Bendahara'])->assertStatus(422);
    $this->postJson('/api/employments', ['name' => 'ab'])->assertStatus(422);
});

it('restores a soft deleted employment with the same name', function () {
    Sanctum::actingAs($this->operator);

    $old = Employment::create(['name' => 'Laboran', 'description' => 'None']);
    $old->delete();

    $this->postJson('/api/employments', ['name' => 'Laboran', 'description' => 'Kelola lab'])->assertOk();

    expect(Employment::where('name', 'Laboran')->count())->toBe(1)
        ->and(Employment::find($old->id)->description)->toBe('Kelola lab');
});

it('blocks deleting an employment still used by employees', function () {
    Sanctum::actingAs($this->operator);

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);
    $employment = Employment::create(['name' => 'Operator', 'description' => 'None']);

    Employee::create([
        'name' => 'Pegawai Uji',
        'employee_status_id' => EmployeeStatus::firstOrCreate(['name' => 'Tetap'], ['abbreviation' => 'T'])->id,
        'employee_type_id' => EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan'])->id,
        'employment_id' => $employment->id,
        'subject_id' => Subject::firstOrCreate(['name' => 'Umum'], ['description' => 'Umum'])->id,
        'religion_id' => Religion::firstOrCreate(['name' => 'Islam'])->id,
        'district_id' => $district->id,
        'marital_status_id' => MaritalStatus::firstOrCreate(['name' => 'Belum Menikah'])->id,
        'gender_id' => Gender::firstOrCreate(['name' => 'Laki-Laki'])->id,
        'tmt_employee' => '2020-01-01',
    ]);

    $this->deleteJson('/api/employments/'.$employment->id, ['delete' => 'true'])->assertStatus(422);
    expect(Employment::find($employment->id))->not->toBeNull();

    $free = Employment::create(['name' => 'Penjaga', 'description' => 'None']);
    $this->deleteJson('/api/employments/'.$free->id, ['delete' => 'true'])->assertOk();
    expect(Employment::find($free->id))->toBeNull();
});

it('denies write access without master.manage', function () {
    Sanctum::actingAs($this->plainUser);

    $this->postJson('/api/employments', ['name' => 'Tanpa Hak'])->assertForbidden();
    $this->getJson('/api/employments')->assertForbidden();
});

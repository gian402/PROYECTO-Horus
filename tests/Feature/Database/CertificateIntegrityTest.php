<?php

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->enrollment = Enrollment::factory()->approved()->create();
});

it('generates an unpredictable 64 character hex hash automatically', function () {
    $a = Certificate::factory()->for($this->enrollment)->create();
    $b = Certificate::factory()->create();

    expect($a->unique_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($a->unique_hash)->not->toBe($b->unique_hash)
        ->and($a->issued_at)->not->toBeNull();
});

it('does not allow mass assigning the public hash', function () {
    $certificate = Certificate::create([
        'enrollment_id' => $this->enrollment->id,
        'unique_hash' => str_repeat('a', 64),
    ]);

    expect($certificate->unique_hash)->not->toBe(str_repeat('a', 64));
});

it('allows only one active certificate per enrollment', function () {
    Certificate::factory()->for($this->enrollment)->create();

    expect(fn () => Certificate::factory()->for($this->enrollment)->create())
        ->toThrow(QueryException::class, 'certificates_one_active_per_enrollment');
});

it('allows reissuing after revoking and keeps the full history', function () {
    $original = Certificate::factory()->for($this->enrollment)->create();

    $original->update(['revoked_at' => now(), 'revocation_reason' => 'Corrección de nombre']);
    $reissued = Certificate::factory()->for($this->enrollment)->create();

    expect($this->enrollment->certificates()->count())->toBe(2)
        ->and($original->fresh()->isRevoked())->toBeTrue()
        ->and($reissued->isRevoked())->toBeFalse()
        ->and($this->enrollment->activeCertificate->is($reissued))->toBeTrue();
});

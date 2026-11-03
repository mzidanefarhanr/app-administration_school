<?php

namespace Tests\Feature\Performance;

use App\Logging\PiiRedactionProcessor;
use App\Models\JobExecutionStatus;
use App\Models\User;
use App\Services\Jobs\JobTrackingService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Support\CreatesAccessFixtures;
use Tests\TestCase;

class QueueAndErrorResilienceTest extends TestCase
{
    use CreatesAccessFixtures, RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessReferenceData();

        $this->admin = $this->makeUserWithRole('admin-sistem');
        $this->operator = $this->makeUserWithRole('operator-sekolah');
        $this->regularUser = $this->makeUserWithRole(null);
    }

    /**
     * R1: Header X-Correlation-ID otomatis disematkan pada setiap respons sukses API.
     */
    public function test_it_attaches_x_correlation_id_header_on_successful_api_responses(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tasks/summary');

        $response->assertStatus(200);
        $response->assertHeader('X-Correlation-ID');

        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertNotEmpty($correlationId);
        $this->assertTrue(Str::isUuid($correlationId) || strlen($correlationId) >= 16);
    }

    /**
     * R2: Header X-Correlation-ID dari client dipertahankan konsisten sepanjang siklus request.
     */
    public function test_it_preserves_client_provided_x_correlation_id_across_request_and_response(): void
    {
        $clientCorrelationId = 'client-trace-'.(string) Str::uuid();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Correlation-ID' => $clientCorrelationId])
            ->getJson('/api/tasks/summary');

        $response->assertStatus(200);
        $response->assertHeader('X-Correlation-ID', $clientCorrelationId);
    }

    /**
     * R3: Header X-Correlation-ID dan format payload standar disematkan pada respons error validasi (422).
     */
    public function test_it_attaches_x_correlation_id_and_standard_error_payload_on_validation_exceptions(): void
    {
        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson('/api/tasks/reminders', []); // Payload kosong memicu ValidationException

        $response->assertStatus(422);
        $response->assertHeader('X-Correlation-ID');
        $response->assertJsonValidationErrors('title');

        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertNotEmpty($data['correlation_id']);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $data['correlation_id']);
    }

    /**
     * R4: PII Redaction Processor menyensor atribut data pribadi sensitif pada Monolog logging.
     */
    public function test_it_redacts_sensitive_pii_fields_in_monolog_logs(): void
    {
        $processor = new PiiRedactionProcessor;

        $rawRecord = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'User registered with NIK: 3201234567890123 and password: SecretPassword123!',
            context: [
                'user_id' => 42,
                'nik' => '3201234567890123',
                'nisn' => '0012345678',
                'birth_date' => '2008-05-15',
                'mother_name' => 'Ibu Kandung',
                'password' => 'SuperSecret123',
                'token' => 'plain-text-token-abc',
                'safe_field' => 'Public School Information',
            ],
            extra: [
                'client_ip' => '127.0.0.1',
                'secret' => 'api-secret-key-xyz',
            ]
        );

        $sanitizedRecord = $processor($rawRecord);

        // Assert context fields are redacted
        $this->assertSame(42, $sanitizedRecord->context['user_id']);
        $this->assertSame('Public School Information', $sanitizedRecord->context['safe_field']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->context['nik']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->context['nisn']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->context['birth_date']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->context['mother_name']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->context['password']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->context['token']);

        // Assert extra fields are redacted
        $this->assertSame('127.0.0.1', $sanitizedRecord->extra['client_ip']);
        $this->assertSame('[REDACTED]', $sanitizedRecord->extra['secret']);

        // Assert message strings are redacted
        $this->assertStringNotContainsString('3201234567890123', $sanitizedRecord->message);
        $this->assertStringNotContainsString('SecretPassword123!', $sanitizedRecord->message);
        $this->assertStringContainsString('[REDACTED_NIK]', $sanitizedRecord->message);
    }

    /**
     * R5: Job tracking service memantau progres dan melindungi operasi dengan Idempotency Replay Guard.
     */
    public function test_it_tracks_job_progress_and_enforces_idempotency_replay_guard(): void
    {
        $service = new JobTrackingService;
        $idempotencyKey = 'batch-import-key-'.(string) Str::uuid();

        // 1. Buat pekerjaan baru
        $job1 = $service->createJobStatus(
            type: 'student_import',
            userId: $this->operator->id,
            idempotencyKey: $idempotencyKey,
            totalRecords: 100
        );

        $this->assertSame(JobExecutionStatus::STATUS_PENDING, $job1->status);
        $this->assertSame(0, $job1->progress_percentage);
        $this->assertSame(100, $job1->total_records);

        // 2. Perbarui progres ke 50%
        $service->updateProgress($job1->job_id, processed: 50, total: 100);
        $job1->refresh();
        $this->assertSame(JobExecutionStatus::STATUS_PROCESSING, $job1->status);
        $this->assertSame(50, $job1->progress_percentage);
        $this->assertSame(50, $job1->processed_records);

        // 3. Replay request dengan idempotency_key yang sama harus mengembalikan record yang sama (tanpa duplikasi)
        $jobReplay = $service->createJobStatus(
            type: 'student_import',
            userId: $this->operator->id,
            idempotencyKey: $idempotencyKey,
            totalRecords: 100
        );

        $this->assertSame($job1->id, $jobReplay->id);
        $this->assertSame($job1->job_id, $jobReplay->job_id);

        // 4. Selesaikan pekerjaan
        $service->markCompleted($job1->job_id, ['imported_rows' => 100]);
        $job1->refresh();
        $this->assertSame(JobExecutionStatus::STATUS_COMPLETED, $job1->status);
        $this->assertSame(100, $job1->progress_percentage);
        $this->assertSame(100, $job1->result_payload['imported_rows']);
    }

    /**
     * R6: Endpoint status pekerjaan (/api/jobs/status/{id}) menegakkan otorisasi kepemilikan.
     */
    public function test_it_exposes_job_status_endpoint_with_ownership_authorization(): void
    {
        $service = new JobTrackingService;

        $job = $service->createJobStatus(
            type: 'dapodik_reconcile',
            userId: $this->operator->id,
            idempotencyKey: 'reconcile-'.(string) Str::uuid(),
            totalRecords: 50
        );

        // 1. Pemilik (operator) dapat melihat status pekerjaannya sendiri
        $responseOwner = $this->actingAs($this->operator, 'sanctum')
            ->getJson("/api/jobs/status/{$job->job_id}");

        $responseOwner->assertStatus(200);
        $responseOwner->assertHeader('X-Correlation-ID');
        $responseOwner->assertJson([
            'success' => true,
            'data' => [
                'job_id' => $job->job_id,
                'type' => 'dapodik_reconcile',
                'status' => 'pending',
                'progress_percentage' => 0,
                'total_records' => 50,
            ],
        ]);

        // 2. Admin sistem dapat memantau status pekerjaan apa pun
        $responseAdmin = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/jobs/status/{$job->job_id}");

        $responseAdmin->assertStatus(200);

        // 3. Pengguna biasa yang bukan pemilik dan tanpa hak manage ditolak (403)
        $responseForbidden = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson("/api/jobs/status/{$job->job_id}");

        $responseForbidden->assertStatus(403);
        $responseForbidden->assertJson([
            'success' => false,
        ]);

        // 4. Job ID yang tidak ada mengembalikan 404
        $fakeUuid = (string) Str::uuid();
        $responseNotFound = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/jobs/status/{$fakeUuid}");

        $responseNotFound->assertStatus(404);
        $responseNotFound->assertJson([
            'success' => false,
        ]);
    }

    /**
     * R7: Tabel antrean (jobs, failed_jobs, job_batches, job_execution_statuses) tersedia dan siap untuk proses asynchronous.
     */
    public function test_it_verifies_queue_tables_schema_readiness(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('job_execution_statuses'));
    }
}

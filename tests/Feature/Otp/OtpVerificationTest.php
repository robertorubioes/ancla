<?php

declare(strict_types=1);

namespace Tests\Feature\Otp;

use App\Jobs\SendOtpCodeJob;
use App\Livewire\Signing\SigningPage;
use App\Models\Document;
use App\Models\OtpCode;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Document $document;

    private SigningProcess $process;

    private Signer $signer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        // Create document
        $this->document = Document::factory()
            ->for($this->tenant)
            ->for($this->user, 'user')
            ->create();

        // Create signing process
        $this->process = SigningProcess::factory()
            ->for($this->tenant)
            ->for($this->document)
            ->for($this->user, 'createdBy')
            ->create(['status' => 'sent']);

        // Create signer
        $this->signer = Signer::factory()
            ->for($this->process, 'signingProcess')
            ->create(['status' => Signer::STATUS_VIEWED]);

        Queue::fake();
        Mail::fake();
    }

    /** @test */
    public function signer_can_request_otp_from_livewire_component(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->call('requestOtp')
            ->assertSet('otpRequested', true)
            ->assertSet('otpError', false)
            ->assertSee('Verification code sent to your email');
    }

    /** @test */
    public function email_is_sent_when_otp_is_requested(): void
    {
        Queue::fake();

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->call('requestOtp');

        Queue::assertPushed(SendOtpCodeJob::class);
    }

    /** @test */
    public function signer_can_verify_otp_with_correct_code(): void
    {
        $otpService = app(OtpService::class);
        $result = $otpService->generate($this->signer);
        $code = $result->code;

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', $code)
            ->call('verifyOtp')
            ->assertSet('otpVerified', true)
            ->assertSet('otpError', false)
            ->assertSee('Verified successfully');
    }

    /** @test */
    public function verification_fails_with_incorrect_code(): void
    {
        $otpService = app(OtpService::class);
        $otpService->generate($this->signer);

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', '999999')
            ->call('verifyOtp')
            ->assertSet('otpVerified', false)
            ->assertSet('otpError', true)
            ->assertSee('Invalid verification code');
    }

    /** @test */
    public function expired_code_shows_appropriate_message(): void
    {
        OtpCode::factory()
            ->for($this->signer)
            ->expired()
            ->withCode('123456')
            ->create();

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', '123456')
            ->call('verifyOtp')
            ->assertSet('otpError', true)
            ->assertSee('expired');
    }

    /** @test */
    public function rate_limit_blocks_after_3_requests(): void
    {
        // Create 3 OTP codes in the last hour
        OtpCode::factory()->for($this->signer)->count(3)->create();

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->call('requestOtp')
            ->assertSet('otpError', true)
            ->assertSee('Rate limit exceeded');
    }

    /** @test */
    public function otp_input_is_disabled_until_code_is_requested(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->assertSee('Request Verification Code')
            ->assertDontSee('Enter Verification Code');
    }

    /** @test */
    public function signing_section_is_unlocked_after_verification(): void
    {
        $otpService = app(OtpService::class);
        $result = $otpService->generate($this->signer);

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', $result->code)
            ->call('verifyOtp')
            ->assertSet('otpVerified', true)
            ->assertSee('Verified')
            ->assertSee('You can now proceed to sign');
    }

    /** @test */
    public function multi_tenant_isolation_is_enforced(): void
    {
        // Create another tenant and signer
        $otherTenant = Tenant::factory()->create();
        $otherProcess = SigningProcess::factory()
            ->for($otherTenant)
            ->for(Document::factory()->for($otherTenant))
            ->create();
        $otherSigner = Signer::factory()
            ->for($otherProcess, 'signingProcess')
            ->create();

        // Generate OTP for the other signer
        $otpService = app(OtpService::class);
        $result = $otpService->generate($otherSigner);

        // Try to use the other signer's code with this signer's token
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', $result->code)
            ->call('verifyOtp')
            ->assertSet('otpError', true);
    }

    /** @test */
    public function queue_job_retries_on_failure(): void
    {
        $otpCode = OtpCode::factory()
            ->for($this->signer)
            ->withCode('123456')
            ->create();

        $job = new SendOtpCodeJob($otpCode, $this->signer, '123456');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->backoff);
    }

    /** @test */
    public function can_request_new_code_after_expiration(): void
    {
        // Create expired code
        OtpCode::factory()
            ->for($this->signer)
            ->expired()
            ->create();

        // Should be able to request new code
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->call('requestOtp')
            ->assertSet('otpRequested', true)
            ->assertSet('otpError', false);

        Queue::assertPushed(SendOtpCodeJob::class);
    }

    /** @test */
    public function otp_code_is_6_digits_only(): void
    {
        $otpService = app(OtpService::class);
        $result = $otpService->generate($this->signer);

        $this->assertEquals(6, strlen($result->code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result->code);
    }

    /** @test */
    public function cannot_verify_without_requesting_first(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpCode', '123456')
            ->call('verifyOtp')
            ->assertSet('otpError', true)
            ->assertSee('No active verification code found');
    }

    /** @test */
    public function empty_code_shows_validation_error(): void
    {
        $otpService = app(OtpService::class);
        $otpService->generate($this->signer);

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', '')
            ->call('verifyOtp')
            ->assertSet('otpError', true)
            ->assertSee('Please enter the verification code');
    }

    /** @test */
    public function code_must_be_6_digits(): void
    {
        $otpService = app(OtpService::class);
        $otpService->generate($this->signer);

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true)
            ->set('otpCode', '123')
            ->call('verifyOtp')
            ->assertSet('otpError', true)
            ->assertSee('must be 6 digits');
    }

    /** @test */
    public function verified_signer_sees_verified_status(): void
    {
        OtpCode::factory()
            ->for($this->signer)
            ->verified()
            ->create();

        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->assertSee('Verified')
            ->assertSee('You can now proceed to sign');
    }

    /** @test */
    public function can_request_new_code_if_not_received(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->call('requestOtp')
            ->assertSet('otpRequested', true)
            ->assertSee("Didn't receive it? Request new code");
    }

    /** @test */
    public function audit_trail_is_created_for_otp_events(): void
    {
        $otpService = app(OtpService::class);
        $result = $otpService->generate($this->signer);

        // Check otp.requested was logged
        $this->assertDatabaseHas('audit_trail_entries', [
            'action' => 'otp.requested',
            'entity_type' => 'signer',
            'entity_id' => $this->signer->id,
        ]);

        // Verify the code
        $otpService->verify($this->signer, $result->code);

        // Check otp.verified was logged
        $this->assertDatabaseHas('audit_trail_entries', [
            'action' => 'otp.verified',
            'entity_type' => 'signer',
            'entity_id' => $this->signer->id,
        ]);
    }

    /** @test */
    public function max_5_attempts_are_allowed_per_code(): void
    {
        $otpService = app(OtpService::class);
        $otpService->generate($this->signer);

        $component = Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('otpRequested', true);

        // Make 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $component->set('otpCode', '999999')->call('verifyOtp');
        }

        // 6th attempt should fail with max attempts exceeded
        $component->set('otpCode', '999999')
            ->call('verifyOtp')
            ->assertSet('otpError', true)
            ->assertSee('Maximum verification attempts exceeded');
    }

    /** @test */
    public function code_is_not_stored_in_plain_text(): void
    {
        $otpService = app(OtpService::class);
        $result = $otpService->generate($this->signer);

        $otpCode = OtpCode::where('signer_id', $this->signer->id)->first();

        $this->assertNotEquals($result->code, $otpCode->code_hash);
        $this->assertTrue(Hash::check($result->code, $otpCode->code_hash));
    }
}

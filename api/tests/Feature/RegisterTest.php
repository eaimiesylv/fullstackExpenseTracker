<?php

namespace Tests\Feature;

use App\Mail\DynamicAppMail;
use App\Models\EmailVerificationOtp;
use App\Models\GroupMember;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful.',
            ])
            ->assertJsonPath('data.user.fullname', 'John Doe')
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonPath('data.user.phone_number', '+2348012345678')
            ->assertJsonStructure(['data' => ['user' => ['id', 'fullname', 'email', 'phone_number'], 'token', 'token_type']]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'phone_number' => '+2348012345678',
        ]);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_user_cannot_register_with_existing_phone_number(): void
    {
        User::factory()->create(['phone_number' => '+2348012345678']);

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone_number');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'not-an-email',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_password_confirmation_is_required(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_weak_password_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_password_is_hashed_and_not_returned(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated();
        $response->assertJsonMissing(['password']);

        $user = User::query()->where('email', 'john@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Password@123', $user->password));
    }

    public function test_registration_returns_authentication_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()->assertJsonPath('data.token_type', 'Bearer');
        $this->assertIsString($response->json('data.token'));
    }

    public function test_registration_sends_an_otp_verification_email_when_email_is_provided(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated();

        Mail::assertSent(DynamicAppMail::class, function (DynamicAppMail $mail): bool {
            $this->assertSame('Verify your email address', $mail->data['subject']);
            $this->assertSame('John Doe', $mail->data['full_name']);
            $this->assertSame('john@example.com', $mail->data['user_data']['email']);

            return true;
        });

        $user = User::query()->where('email', 'john@example.com')->firstOrFail();
        $this->assertTrue(EmailVerificationOtp::query()->where('user_id', $user->id)->exists());
    }

    public function test_guest_can_register_using_valid_invitation(): void
    {
        $groupMember = GroupMember::create([
            'group_id' => 1,
            'user_id' => null,
            'fullname' => 'Peter Guest',
            'role' => 'member',
            'status' => 'pending',
        ]);

        $invite = Invite::create([
            'group_id' => 1,
            'group_member_id' => $groupMember->id,
            'invited_by' => User::factory()->create()->id,
            'email' => 'peter@example.com',
            'token' => 'VALIDTOKEN',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Peter Guest',
            'email' => 'peter@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'invite_token' => 'VALIDTOKEN',
        ]);

        $response->assertCreated()->assertJson(['success' => true]);

        $user = User::query()->where('email', 'peter@example.com')->firstOrFail();
        $groupMember->refresh();
        $invite->refresh();

        $this->assertSame($user->id, $groupMember->user_id);
        $this->assertSame('accepted', $invite->status);
        $this->assertNotNull($invite->accepted_at);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $groupMember = GroupMember::create([
            'group_id' => 1,
            'user_id' => null,
            'fullname' => 'Peter Guest',
            'role' => 'member',
            'status' => 'pending',
        ]);

        Invite::create([
            'group_id' => 1,
            'group_member_id' => $groupMember->id,
            'invited_by' => User::factory()->create()->id,
            'email' => 'peter@example.com',
            'token' => 'EXPIREDTOKEN',
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Peter Guest',
            'email' => 'peter@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'invite_token' => 'EXPIREDTOKEN',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false, 'message' => 'The invitation is invalid or has expired.']);
    }

    public function test_already_accepted_invitation_is_rejected(): void
    {
        $groupMember = GroupMember::create([
            'group_id' => 1,
            'user_id' => null,
            'fullname' => 'Peter Guest',
            'role' => 'member',
            'status' => 'pending',
        ]);

        Invite::create([
            'group_id' => 1,
            'group_member_id' => $groupMember->id,
            'invited_by' => User::factory()->create()->id,
            'email' => 'peter@example.com',
            'token' => 'ACCEPTEDTOKEN',
            'status' => 'accepted',
            'expires_at' => now()->addDay(),
            'accepted_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Peter Guest',
            'email' => 'peter@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'invite_token' => 'ACCEPTEDTOKEN',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false, 'message' => 'The invitation is invalid or has expired.']);
    }

    public function test_invalid_invitation_token_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Peter Guest',
            'email' => 'peter@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'invite_token' => 'INVALIDTOKEN',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false, 'message' => 'The invitation is invalid or has expired.']);
    }

    public function test_registering_one_guest_does_not_merge_another_guest_with_same_name(): void
    {
        $otherGuest = GroupMember::create([
            'group_id' => 2,
            'user_id' => null,
            'fullname' => 'Peter Guest',
            'role' => 'member',
            'status' => 'pending',
        ]);

        $guest = GroupMember::create([
            'group_id' => 1,
            'user_id' => null,
            'fullname' => 'Peter Guest',
            'role' => 'member',
            'status' => 'pending',
        ]);

        $invite = Invite::create([
            'group_id' => 1,
            'group_member_id' => $guest->id,
            'invited_by' => User::factory()->create()->id,
            'email' => 'peter1@example.com',
            'token' => 'GUESTTOKEN',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Peter Guest',
            'email' => 'peter1@example.com',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'invite_token' => 'GUESTTOKEN',
        ]);

        $response->assertCreated();
        $otherGuest->refresh();
        $guest->refresh();

        $this->assertNull($otherGuest->user_id);
        $this->assertNotNull($guest->user_id);
        $this->assertSame('accepted', $invite->refresh()->status);
    }

    public function test_registration_can_use_only_phone_number(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullname' => 'Jane Doe',
            'phone_number' => '+2348012345678',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()->assertJsonPath('data.user.phone_number', '+2348012345678');
    }
}

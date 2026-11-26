<?php

namespace Tests\Feature;

use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\ContactSubmissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function openForm(bool $open = true): void
    {
        PortfolioSetting::findOrFail(1)->forceFill(['testimonial_submissions_open' => $open])->save();
    }

    private function token(): string
    {
        // The guard reads the real clock, so issue a token that is five seconds old.
        return Crypt::encryptString((string) (time() - 5));
    }

    private function submission(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Grace Client',
            'email' => 'grace@example.test',
            'company' => 'Example Ltd',
            'quote' => 'Hamid rebuilt our booking system and it has been reliable ever since.',
            'rating' => 5,
            'consent' => '1',
            'website' => '',
            'submission_token' => $this->token(),
        ], $overrides);
    }

    private function createTestimonialsSection(): PortfolioSection
    {
        return PortfolioSection::create([
            'key' => 'kind-words', 'type' => 'testimonials', 'label' => 'Kind words', 'title' => 'Kind words',
            'items' => [], 'is_visible' => true, 'sort_order' => 90, 'show_in_navigation' => false,
        ]);
    }

    public function test_form_is_closed_by_default_and_refuses_submissions(): void
    {
        $this->get('/testimonials/new')->assertOk()->assertSee("isn't collecting testimonials", false)->assertDontSee('name="quote"', false);
        $this->post('/testimonials', $this->submission())->assertRedirect('/testimonials/new');
        $this->assertSame(0, Testimonial::count());
    }

    public function test_submission_waits_for_review_and_keeps_email_private(): void
    {
        $this->openForm();
        $this->get('/testimonials/new')->assertOk()->assertSee('name="quote"', false)->assertHeader('X-Robots-Tag', 'noindex');

        $this->post('/testimonials', $this->submission())->assertRedirect('/testimonials/new')->assertSessionHas('testimonial_sent');

        $testimonial = Testimonial::sole();
        $this->assertSame('pending', $testimonial->status);
        $this->assertSame('website', $testimonial->source);
        $this->assertNotNull($testimonial->consent_at);
        $this->assertArrayNotHasKey('email', $testimonial->toArray());

        $this->createTestimonialsSection();
        $this->get('/')->assertOk()->assertDontSee('booking system');
    }

    public function test_submission_requires_consent_and_blocks_bots(): void
    {
        // The route allows 3 posts per 10 minutes; this test checks the app's own guards behind it.
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->openForm();
        $this->post('/testimonials', $this->submission(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post('/testimonials', $this->submission(['website' => 'http://spam.example']))->assertSessionHasErrors('website');
        $this->post('/testimonials', $this->submission(['submission_token' => 'forged']))->assertSessionHasErrors('quote');
        $this->post('/testimonials', $this->submission(['submission_token' => app(ContactSubmissionGuard::class)->issueToken()]))
            ->assertSessionHasErrors('quote'); // sent faster than a person can type
        $this->assertSame(0, Testimonial::count());
    }

    public function test_tokens_are_single_use_and_emails_are_rate_limited(): void
    {
        // The route allows 3 posts per 10 minutes; this test checks the app's own guards behind it.
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->openForm();
        $first = $this->submission();
        $this->post('/testimonials', $first)->assertSessionHas('testimonial_sent');
        $this->post('/testimonials', $first)->assertSessionHasErrors('quote');
        $this->post('/testimonials', $this->submission())->assertSessionHas('testimonial_sent');
        $this->post('/testimonials', $this->submission())->assertSessionHasErrors('quote');
        $this->assertSame(2, Testimonial::count());
    }

    public function test_approved_testimonials_appear_in_testimonials_sections_and_rejected_do_not(): void
    {
        $this->createTestimonialsSection();
        Testimonial::create(['name' => 'Approved Person', 'quote' => 'Clear, quick and kind.', 'rating' => 4, 'company' => 'Acme', 'status' => 'approved', 'source' => 'manual']);
        Testimonial::create(['name' => 'Rejected Person', 'quote' => 'Should never show.', 'status' => 'rejected', 'source' => 'manual']);
        Testimonial::create(['name' => 'Pending Person', 'quote' => 'Not yet.', 'status' => 'pending', 'source' => 'manual']);

        $this->get('/')->assertOk()
            ->assertSee('<h3>Approved Person</h3><p class="eyebrow">Acme &middot; &#9733;&#9733;&#9733;&#9733;&#9734;</p>', false)
            ->assertSee('Clear, quick and kind.')
            ->assertDontSee('Rejected Person')->assertDontSee('Pending Person');
    }

    public function test_admin_moderation_flow(): void
    {
        $admin = $this->admin();
        $this->get('/admin/testimonials')->assertRedirect('/admin/login');
        $testimonial = Testimonial::create(['name' => 'Queue Person', 'quote' => 'Waiting in the queue.', 'status' => 'pending', 'source' => 'website', 'email' => 'q@example.test']);

        $this->actingAs($admin)->get('/admin/testimonials')->assertOk()->assertSee('Queue Person')->assertSee('q@example.test');
        $this->actingAs($admin)->patch("/admin/testimonials/{$testimonial->id}/status", ['status' => 'approved'])->assertSessionHas('status');
        $this->assertSame('approved', $testimonial->fresh()->status);
        $this->assertSame($admin->id, $testimonial->fresh()->reviewed_by);

        $this->actingAs($admin)->put("/admin/testimonials/{$testimonial->id}", [
            'name' => 'Queue Person', 'quote' => 'Edited typo.', 'source' => 'linkedin', 'is_featured' => '1',
        ])->assertSessionHas('status');
        $fresh = $testimonial->fresh();
        $this->assertSame('website', $fresh->source, 'website origin cannot be relabelled');
        $this->assertTrue($fresh->is_featured);

        $this->actingAs($admin)->patch("/admin/testimonials/{$testimonial->id}/status", ['status' => 'rejected']);
        $this->assertFalse($testimonial->fresh()->is_featured, 'unpublished testimonials cannot stay featured');

        $this->actingAs($admin)->delete("/admin/testimonials/{$testimonial->id}")->assertSessionHas('status');
        $this->assertSame(0, Testimonial::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'testimonial.deleted']);
    }

    public function test_admin_can_add_and_publish_and_toggle_the_form(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/testimonials', [
            'name' => 'Direct Add', 'quote' => 'Received by email.', 'source' => 'email', 'publish' => '1',
        ])->assertRedirect('/admin/testimonials?status=approved');
        $this->assertSame('approved', Testimonial::sole()->status);

        $this->actingAs($admin)->post('/admin/testimonials', ['name' => 'Bad', 'quote' => 'x', 'source' => 'manual', 'rating' => 9])
            ->assertSessionHasErrors('rating');
        $this->actingAs($admin)->post('/admin/testimonials', ['name' => 'Bad', 'quote' => 'x', 'source' => 'manual', 'source_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('source_url');

        $this->actingAs($admin)->post('/admin/testimonials/submissions', ['open' => 1]);
        $this->assertTrue(PortfolioSetting::findOrFail(1)->testimonial_submissions_open);
        $this->actingAs($admin)->get('/admin/testimonials')->assertSee(route('testimonials.create'));
    }

    public function test_pending_testimonials_show_in_nav_and_search(): void
    {
        $admin = $this->admin();
        Testimonial::create(['name' => 'Searchable Sam', 'quote' => 'Findable words.', 'status' => 'pending', 'source' => 'manual']);

        $this->actingAs($admin)->get('/admin')->assertSee('1 waiting for review');
        $groups = $this->actingAs($admin)->getJson('/admin/search?q=Searchable')->json('groups');
        $this->assertSame('Searchable Sam', collect($groups)->firstWhere('type', 'testimonials')['items'][0]['title']);
    }
}

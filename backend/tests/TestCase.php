<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any relation read without being eager loaded fails the test. The
        // feature suite exercises every endpoint, so this is what keeps an
        // N+1 from creeping into a list or a resource.
        Model::preventLazyLoading();

        // Sanctum only makes a request stateful — starting the session and
        // enforcing CSRF — when it arrives from a configured stateful domain,
        // which it detects from the Origin/Referer header. Browsers always
        // send one; the test client does not, so it is set here and every
        // test exercises the real SPA path rather than bypassing it.
        $this->withHeader('Origin', config('app.url'));
    }
}

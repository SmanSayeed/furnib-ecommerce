<?php

declare(strict_types=1);

// The 'orders' limiter is 20/min by IP (AppServiceProvider). Empty bodies 422
// at validation but still count toward the limit, so the 21st request is 429.
it('throttles the checkout endpoint after the limit', function () {
    cache()->flush(); // start from a clean limiter

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/v1/orders', []);
    }

    $this->postJson('/api/v1/orders', [])->assertStatus(429);
});

<?php

test('the root redirects to the admin login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

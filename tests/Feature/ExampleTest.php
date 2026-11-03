<?php

test('the application sends guests from the home page to the login form', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

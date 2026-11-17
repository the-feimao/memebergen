<?php

test('public registration screen is unavailable', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration requests are unavailable', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

<?php

it('redirects the root to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

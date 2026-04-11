<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('servers.index'))->assertRedirect(route('login'));
});

test('authenticated users can visit the servers index', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('servers.index'))->assertOk();
});

<?php

it('has sites relation on server model', function () {
    $this->assertTrue(method_exists(\App\Models\Server::class, 'sites'));
});

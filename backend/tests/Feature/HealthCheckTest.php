<?php

it('exposes the versioned api health endpoint', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'version' => 'v1']);
});

it('exposes the framework health check', function () {
    $this->get('/up')->assertOk();
});

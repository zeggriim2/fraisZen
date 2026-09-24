<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

it('lit un payload JSON', function (): void {
    $request = Request::create(
        '/api/test',
        Request::METHOD_POST,
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode(['personId' => 'person-id', 'decision' => 'confirm'], JSON_THROW_ON_ERROR),
    );

    expect($request->getPayload()->all())->toBe([
        'personId' => 'person-id',
        'decision' => 'confirm',
    ]);
});

it('lit aussi un formulaire', function (): void {
    $request = Request::create('/api/test', Request::METHOD_POST, ['personId' => 'person-id']);

    expect($request->getPayload()->all())->toBe(['personId' => 'person-id']);
});

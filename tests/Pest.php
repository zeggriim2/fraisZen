<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(App\Tests\TestCase::class)->in('Unit');

const APP_URL = 'http://node:5173';
const TEST_EMAIL = 'alice@example.com';
const TEST_PASSWORD = 'Test1234!';
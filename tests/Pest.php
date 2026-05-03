<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(App\Tests\TestCase::class)->in('Unit');

const APP_URL = 'http://node:5173';
const TEST_EMAIL = 'browser-test@test.com';
const TEST_PASSWORD = 'TestPassword123!';
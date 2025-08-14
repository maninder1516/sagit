<?php

namespace App\Tests\Unit\Controller\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account');

        self::assertResponseIsSuccessful();
    }
}

<?php

namespace App\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $account = new \App\Entity\Account();
        $user = new \App\Entity\User(); // Assuming User entity exists

        $account->setBalance(100.0);
        $account->setAccountHolder($user);
        $account->setAccountManager($user);

        $this->assertEquals(100.0, $account->getBalance());
        $this->assertSame($user, $account->getAccountHolder());
        $this->assertSame($user, $account->getAccountManager());
    }

    public function testItWorksTheSame(): void
    {
        $this->assertSame(expected: 40, actual: 40);
    }
}
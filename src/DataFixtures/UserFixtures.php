<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    /**
     * @var UserPasswordHasherInterface
     */
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $adminUser = new User();
        $adminUser->setEmail('maninder1516@gmail.com');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setPassword($this->passwordHasher->hashPassword($adminUser, 'maninder@123'));
        
        // Create client user
        $clientUser = new User();
        $clientUser->setEmail('client@example.com');
        $clientUser->setRoles(['ROLE_CLIENT']);
        $clientUser->setPassword($this->passwordHasher->hashPassword($clientUser, 'client123'));
        
        $manager->persist($adminUser);
        $manager->persist($clientUser);
        $manager->flush();
    }
}

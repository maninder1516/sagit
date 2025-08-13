<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class AccountVoter extends Voter
{
    public const EDIT = 'POST_EDIT';
    public const SHOW = 'SHOW';
    public const DELETE = 'DELETE';

    public function __construct(public Security $security)  // Inject Security service to access user info
    {
        
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Our job is to determine if your voter should vote on the attribute/subject combination.
        // We return true if the attribute is show or delete and if the object is a Account instance.
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::SHOW, self::DELETE])
            && $subject instanceof \App\Entity\Account;
    }

    /**
     * @param string $attribute
     * @param Account $account
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute(string $attribute, mixed $account, TokenInterface $token): bool
    {
        //dd($this->security);
        // If we return true from supports(), then this method is called. Your job is to return true
        // to allow access and false to deny access. The $token can be used to find the current user object

        $user = $token->getUser();

        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        return match ($attribute) {
            self::SHOW => $this->show($account, $user),   // User is account holder or account manager
            self::DELETE => $this->security->isGranted('ROLE_ADMIN')   // User is admin
        };

        // return match ($attribute) {
        //     self::SHOW => $account->getAccountHolder() === $user || $account->getAccountManager() === $user,   // User is account holder or account manager
        //     self::DELETE => $this->security->isGranted('ROLE_ADMIN')   // User is admin
        // };

        // ... (check conditions and return true to grant permission) ...
        // switch ($attribute) {
        //     case self::SHOW:
        //         // logic to determine if the user can EDIT
        //         // return true or false
        //         return true;
        //         break;

        //     case self::DELETE:
        //         // logic to determine if the user can VIEW
        //         // return true or false
        //         break;
        // }

        //return false;
    }

    /**
     * Check if the user can view the account
     */
    private function show($account, UserInterface $user): bool
    {
        return $account->getAccountHolder() === $user 
            || $account->getAccountManager() === $user
            || $this->security->isGranted('ROLE_ADMIN');  // Admin can view any account
    }
}

<?php

namespace App\Security\Voter;

use App\Entity\Mission;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class MissionVoter extends Voter
{
    // Define constants for our supported permissions
    const VIEW = 'VIEW';
    const EDIT = 'EDIT';

    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        // Only vote on `Mission` objects for the VIEW or EDIT attributes
        return in_array($attribute, [self::VIEW, self::EDIT])
            && $subject instanceof Mission;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        // The user must be logged in; if not, deny access
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Admin users have access to everything
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        /** @var Mission $mission */
        $mission = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($mission, $user);
            case self::EDIT:
                return $this->canEdit($mission, $user);
        }

        return false;
    }

    private function canView(Mission $mission, UserInterface $user): bool
    {
        // If they can edit, they can view
        if ($this->canEdit($mission, $user)) {
            return true;
        }

        // Admins can view all missions (already checked above)
        // Clients can only view their own missions
        return $mission->getClient() && $mission->getClient()->getId() === $user->getId();
    }

    private function canEdit(Mission $mission, UserInterface $user): bool
    {
        // Clients can only edit their own missions
        return $mission->getClient() && $mission->getClient()->getId() === $user->getId();
    }
}

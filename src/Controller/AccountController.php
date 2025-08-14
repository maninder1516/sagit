<?php

namespace App\Controller;

use App\Entity\Account;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{    
    #[Route('/account', name: 'sagit_account')]
    public function index(): Response
    {
        return $this->render('account/index.html.twig', [
            'controller_name' => 'AccountController',
        ]);
    }

    #[Route('/account/{id}', name: 'sagit_account_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('SHOW', subject: 'account')]
    public function show(Account $account)
    {
        // if($this->isGranted('SHOW', $account)) {
        //     // User is admin or the account holder, proceed to show account
        //      dd('Granted...');
        // } else {
        //     // User does not have permission to view this account
        //     throw $this->createAccessDeniedException('You do not have permission to view this account.');
        // }   
        // dd($account);
        return $this->render('account/show.html.twig', [
            'account' => $account,
        ]);
    }

    #[Route('/account/{id}/delete', name: 'sagit_account_delete', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('DELETE', subject: 'account')]
    public function delete (Account $account) {
        // if($this->isGranted('DELETE', $account)) {
        //     // User is admin or the account holder, proceed to delete account
        //      dd('Granted Delete...');
        // } else {
        //     // User does not have permission to delete this account
        //     throw $this->createAccessDeniedException('You do not have permission to delete this account.');
        // }

        return new Response('Deleting the Account here : '. $account->getId(), Response::HTTP_OK);
    }
}

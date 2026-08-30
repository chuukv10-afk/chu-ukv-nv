<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route('/api/v1/login', name: 'api_login', methods: ['POST'])]
    public function login(): Response
    {
        throw new \LogicException('Cette route est interceptée par le firewall json_login.');
    }
}

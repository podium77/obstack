<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    #[Route('/home', name: 'homepage_alt')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }
        return $this->render('home/index.html.twig');
    }
}
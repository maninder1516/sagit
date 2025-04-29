<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ErrorController extends AbstractController
{
    public function show(\Throwable $exception): Response
    {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
        }
        
        // Try to load specific error template for common errors
        $template = 'error/error.html.twig';
        if (in_array($statusCode, [403, 404, 500])) {
            $template = "error/$statusCode.html.twig";
        }
        
        return $this->render($template, [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Unknown Error',
            'exception' => $exception,
        ], new Response('', $statusCode));
    }
}

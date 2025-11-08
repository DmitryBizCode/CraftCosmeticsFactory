<?php
namespace App\Services;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Exception;

class TemplateService
{
    private Environment $twig;
    public function __construct()
    {
        $loader  = new FilesystemLoader(__DIR__. '/../../views');
        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => true,
            'auto_reload' => true
        ]);
    }

    public function render(string $templateName, array $data = []): string
    {
        try {
            return $this->twig->render($templateName.'.twig', $data);
        } catch (Exception $e) {
            echo "Error loading template: " . $e->getMessage();
            return '';
        }
    }

}

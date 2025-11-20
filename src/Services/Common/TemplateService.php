<?php
namespace App\Services\Common;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Exception;

class TemplateService
{
    private Environment $twig;
    public function __construct()
    {
        // Визначаємо шлях до views відносно кореня проекту
        $viewsPath = dirname(__DIR__, 3) . '/views';
        $loader  = new FilesystemLoader($viewsPath);
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

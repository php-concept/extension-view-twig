<?php declare(strict_types=1);

namespace Concept\Extensions\ViewTwig;

use Concept\Extensions\View\Events\TemplateRendered;
use Concept\Extensions\View\Contracts\ViewInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class TwigView implements ViewInterface
{
    public function __construct(
        private readonly Twig $twig,
        private readonly string $defaultExtension,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(string $viewName, array $data = []): string
    {
        $startedAt = microtime(true);
        $content = $this->twig->render($this->ensureExtension($viewName), $data);
        $this->dispatcher?->dispatch(new TemplateRendered(
            view: $viewName,
            startedAt: $startedAt,
            duration: microtime(true) - $startedAt,
        ));

        return $content;
    }

    private function ensureExtension(string $viewName): string
    {
        if (str_ends_with($viewName, $this->defaultExtension)) {
            return $viewName;
        }

        return $viewName . $this->defaultExtension;
    }
}

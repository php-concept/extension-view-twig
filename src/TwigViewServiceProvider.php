<?php declare(strict_types=1);

namespace Concept\Extensions\ViewTwig;

use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use Concept\Extensions\View\Contracts\ViewInterface;
use Concept\Extensions\View\Registry\ViewRegistry;
use Concept\Extensions\ViewTwig\Console\Commands\ViewClearCommand;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;

final class TwigViewServiceProvider extends AbstractServiceProvider
{
    private const string EXTENSION_NAME = 'view-twig';
    public const string DEFAULT_EXTENSION = '.twig';

    public function __construct(
        private readonly string $root,
        private readonly string $viewsPath,
        private readonly string $cacheDir = '',
        private readonly bool $debug = false,
        private readonly string $defaultExtension = self::DEFAULT_EXTENSION,
    ) {}

    public function provides(string $id): bool
    {
        return in_array($id, [ViewInterface::class, ViewClearCommand::class], true);
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(Filesystem::class, fn(): Filesystem => new Filesystem())->setShared(true);

        $container->add(ViewClearCommand::class, function() use ($container): ViewClearCommand {
            /** @var Filesystem $filesystem */
            $filesystem = $container->get(Filesystem::class);

            return new ViewClearCommand(
                cacheDir: $this->cacheDir,
                filesystem: $filesystem,
            );
        })->setShared(true);

        $container->add(ViewInterface::class, function() use ($container): TwigView {
            EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
                extensionName: self::EXTENSION_NAME,
                anchorId: ViewInterface::class,
            ));

            $loader = new FilesystemLoader($this->viewsPath);
            $twig = new Environment($loader, [
                'cache' => $this->debug ? false : $this->cacheDir,
                'debug' => $this->debug,
            ]);

            /** @var ViewRegistry $viewRegistry */
            $viewRegistry = $container->get(ViewRegistry::class);
            $this->addExtensions($twig, $viewRegistry->extensions()->all(), $this->debug);
            $this->addPaths($loader, $this->root, $viewRegistry->paths()->all());
            $this->addFallbackPath($loader, $this->viewsPath);

            return new TwigView(
                $twig,
                $this->defaultExtension,
                EventDispatcherResolver::optional($container),
            );
        })->setShared(true);
    }

    /**
     * @param array<int, class-string> $extensions
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function addExtensions(Environment $twig, array $extensions, bool $debug): void
    {
        if ($debug) {
            $twig->addExtension(new DebugExtension());
        }

        foreach ($extensions as $extensionClass) {
            /** @var ExtensionInterface $extension */
            $extension = $this->getContainer()->get($extensionClass);
            $twig->addExtension($extension);
        }
    }

    /**
     * @param array<string, string> $namespaces
     * @throws LoaderError
     */
    private function addPaths(FilesystemLoader $loader, string $rootPath, array $namespaces): void
    {
        foreach ($namespaces as $namespace => $path) {
            $loader->addPath(rtrim($rootPath, '/') . '/' . ltrim($path, '/'), $namespace);
        }
    }

    /**
     * @throws LoaderError
     */
    private function addFallbackPath(FilesystemLoader $loader, string $templatesPath): void
    {
        $loader->addPath($templatesPath);
    }
}

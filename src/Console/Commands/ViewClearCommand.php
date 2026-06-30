<?php declare(strict_types=1);

namespace Concept\Extensions\ViewTwig\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

final class ViewClearCommand extends Command
{
    private const string COMMAND_NAME = 'view:clear';
    private const string COMMAND_DESCRIPTION = 'Clear all compiled view templates';

    private const string MSG_STARTING = 'Clearing view cache...';
    private const string MSG_SUCCESS = 'View cache cleared successfully.';
    private const string ERR_CLEAR_FAILED = 'Failed to clear view cache: %s';

    public function __construct(
        private readonly string $cacheDir,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title(self::MSG_STARTING);

        try {
            if ($this->filesystem->exists($this->cacheDir)) {
                $this->filesystem->remove($this->cacheDir);
            }

            $io->success(self::MSG_SUCCESS);

            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $io->error(sprintf(self::ERR_CLEAR_FAILED, $exception->getMessage()));

            return Command::FAILURE;
        }
    }
}

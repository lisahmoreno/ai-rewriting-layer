<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AiRewriting\ArrangementRewriteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:arrangement:rewrite',
    description: 'Generate AI-rewritten titles and descriptions for portal-specific content',
)]
class ArrangementRewriteCommand extends Command
{
    public function __construct(
        private readonly ArrangementRewriteService $rewriteService,
        private readonly bool $enabled, // AI_REWRITING_ENABLED env flag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('portal',        null, InputOption::VALUE_REQUIRED, 'Target portal (e.g. ku)', 'ku')
            ->addOption('batch-size',    null, InputOption::VALUE_REQUIRED, 'Arrangements per batch', 100)
            ->addOption('retry-failed',  null, InputOption::VALUE_NONE,     'Re-process Failed records')
            ->addOption('arrangement-id',null, InputOption::VALUE_REQUIRED, 'Process a single arrangement by ID')
            ->addOption('dry-run',       null, InputOption::VALUE_NONE,     'Run without persisting results');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->enabled) {
            $output->writeln('AI rewriting is disabled (AI_REWRITING_ENABLED=0)');
            return Command::SUCCESS;
        }

        // For each active arrangement:
        //   1. Compute sourceHash; skip if unchanged + same promptVersion + already Approved/Generated
        //   2. Call rewrite(); persist with status Approved / Rejected (reason) / Failed
        //   3. Flush + clear every 10 to keep memory flat on large batches
        //
        // Prints: Processed / Approved / Rejected / Failed / Skipped
        //
        // ... implementation trimmed (repository + entity manager wiring)

        return Command::SUCCESS;
    }
}

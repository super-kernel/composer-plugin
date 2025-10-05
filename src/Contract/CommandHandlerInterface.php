<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Contract;

use Composer\Composer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface CommandHandlerInterface
{
	public function __construct(Composer $composer, InputInterface $input, OutputInterface $output);

	public function handle(): void;
}
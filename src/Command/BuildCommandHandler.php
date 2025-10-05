<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Command;

use Composer\Composer;
use SuperKernel\ComposerPlugin\Abstract\CommandHandlerAbstract;
use SuperKernel\ComposerPlugin\Contract\CommandHandlerInterface;
use SuperKernel\ComposerPlugin\Support\Target;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class BuildCommandHandler extends CommandHandlerAbstract implements CommandHandlerInterface
{
	protected function configure(): void
	{
		$this->setDescription('Build a binary self-executable file (outside the host environment).');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('<info>开始构建...</info>');

		$process = new Process(
			[
				'echo',
				'模拟构建流程',
			],
		);

		$process->run(function ($type, $buffer) use ($output) {
			$output->write($buffer);
		});

		$output->writeln('<info>构建完成！</info>');

		return Command::SUCCESS;
	}

	public function handle(): void
	{
		// TODO: Implement handle() method.
	}

	/**
	 * @return Target
	 */
	protected function getTarget(): Target
	{
		return Target::RELEASE_DIR;
	}
}
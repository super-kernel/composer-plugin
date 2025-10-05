<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Command;

use SuperKernel\ComposerPlugin\Abstract\CommandHandlerAbstract;
use SuperKernel\ComposerPlugin\Contract\CommandHandlerInterface;
use SuperKernel\ComposerPlugin\Support\Target;

final class ServeCommandHandler extends CommandHandlerAbstract implements CommandHandlerInterface
{
	public function handle(): void
	{
		$this->output->writeln('<info>Run the development server with hot reload...</info>');
		$this->output->writeln('<info>Scanning packages...</info>');

		parent::handlePackages();

		$this->output->writeln('<info>Scanning source code...</info>');

		parent::handleSourceCode();

		$this->output->writeln('<info>Checking out .stub and building bootloader...</info>');

		parent::generatePharStub();

		$this->output->writeln('<info>Packaging into phar...</info>');

		parent::buildPharFile();

		$this->output->writeln('<info>The startup entry file has been generated.</info>');
	}

	/**
	 * @return Target
	 */
	protected function getTarget(): Target
	{
		return Target::DEBUG_DIR;
	}
}
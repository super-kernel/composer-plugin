<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin;

use Composer\Command\BaseCommand;
use Composer\EventDispatcher\EventDispatcher;
use Psr\Container\ContainerInterface;
use SuperKernel\ComposerPlugin\Event\AfterBuildEvent;
use SuperKernel\ComposerPlugin\Event\BeforeBuildEvent;
use SuperKernel\ComposerPlugin\Event\BuildEvent;
use SuperKernel\Di\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SuperKernelCommand extends BaseCommand
{
	private string $logo = <<<LOGO
      ___           ___           ___           ___           ___           ___           ___ 
     /  /\         /  /\         /  /\         /  /\         /  /\         /  /\         /  /\
    /  /::\       /  /:/        /  /::\       /  /::\       /  /::|       /  /::\       /  /:/
   /__/:/\:\     /  /:/        /  /:/\:\     /  /:/\:\     /  /:|:|      /  /:/\:\     /  /:/ 
  _\_ \:\ \:\   /  /::\____   /  /::\ \:\   /  /::\ \:\   /  /:/|:|__   /  /::\ \:\   /  /:/  
 /__/\ \:\ \:\ /__/:/\:::::\ /__/:/\:\ \:\ /__/:/\:\_\:\ /__/:/ |:| /\ /__/:/\:\ \:\ /__/:/   
 \  \:\ \:\_\/ \__\/~|:|~~~~ \  \:\ \:\_\/ \__\/~|::\/:/ \__\/  |:|/:/ \  \:\ \:\_\/ \  \:\   
  \  \:\_\:\      |  |:|      \  \:\ \:\      |  |:|::/      |  |:/:/   \  \:\ \:\    \  \:\  
   \  \:\/:/      |  |:|       \  \:\_\/      |  |:|\/       |__|::/     \  \:\_\/     \  \:\ 
    \  \::/       |__|:|        \  \:\        |__|:|~        /__/:/       \  \:\        \  \:\
     \__\/         \__\|         \__\/         \__\|         \__\/         \__\/         \__\/
LOGO;

	public function __construct()
	{
		parent::__construct('skernel');
	}

	protected function configure(): void
	{
		$this
			->addOption('disable-binary', null, InputOption::VALUE_NONE, 'Disable binary build, Only build phar archive.')
			->addOption('dev', null, InputOption::VALUE_NONE, 'Use require-dev requirement.')
			->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug mode.');

		$this->ignoreValidationErrors();
	}

	public function getDescription(): string
	{
		return PHP_EOL . sprintf(
				"%s\n\n<fg=green;options=bold>%s</> %s",
				$this->logo,
				$this->getName(),
				date('Y-m-d H:i:s'),
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$debug = $input->getOption('debug');

		if (true === $debug) {
			$output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
		}

		$filteredDevPackages = false === $input->getOption('dev');

		$classMap = $this->getClassMap($filteredDevPackages);

		$container = $this->createContainer([]);

		$eventDispatcher = $container->get(EventDispatcher::class);

		$eventDispatcher->dispatch(new BeforeBuildEvent());
		$eventDispatcher->dispatch(new BuildEvent());
		$eventDispatcher->dispatch(new AfterBuildEvent());

		return Command::SUCCESS;
	}

	private function createContainer(array $attributes): ContainerInterface
	{
		return new Container($attributes);
	}

	private function getClassMap(bool $filteredDevPackages): array
	{
		$composer = $this->getApplication()->getComposer();

		$installationManager = $composer->getInstallationManager();
		$rootPackage         = $composer->getPackage();
		$localRepo           = $composer->getRepositoryManager()->getLocalRepository();

		$autoloadGenerator = $composer->getAutoloadGenerator();

		$packageMap = $autoloadGenerator->buildPackageMap($installationManager, $rootPackage, $localRepo->getCanonicalPackages());

		return $autoloadGenerator->parseAutoloads($packageMap, $rootPackage, $filteredDevPackages);
	}
}
<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use RuntimeException;
use SuperKernel\ComposerPlugin\Command\BuildCommandHandler;
use SuperKernel\ComposerPlugin\Command\ServeCommandHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CommandProvider implements CommandProviderCapability
{

	/**
	 * @return BaseCommand[]
	 */
	public function getCommands(): array
	{
		return [
			new class extends BaseCommand {

				private string $logo = <<<LOGO
   _____ __ __                     __
  / ___// //_/__  _________  ___  / /
  \__ \/ ,< / _ \/ ___/ __ \/ _ \/ /
 ___/ / /| /  __/ /  / / / /  __/ /
/____/_/ |_\___/_/  /_/ /_/\___/_/
LOGO;

				protected function configure(): void
				{
					$this->setName('skernel')
						->setDescription('Run the skernel tool via Composer.')
						->addArgument('subcommand', InputArgument::OPTIONAL, 'The skernel subcommand to run');
				}

				protected function execute(InputInterface $input, OutputInterface $output): int
				{
					$io = new SymfonyStyle($input, $output);

					$subcommand = $input->getArgument('subcommand');

					if (is_string($subcommand)) {

						// 配置全局应用上下文
						ApplicationContext::setComposer($this->requireComposer());
						ApplicationContext::setOutput($output);

						// 对输入进行处理
						new (match ($subcommand) {
							'serve' => ServeCommandHandler::class,
							'build' => BuildCommandHandler::class,
							default => throw new RuntimeException(sprintf('Unknown command "%s"', $subcommand)),
						})($this->requireComposer(), $input, $output)->handle();

						return Command::SUCCESS;
					}

					// 没有子命令时，显示默认信息
					$this->showDefaultInfo($io);
					return Command::SUCCESS;
				}

				/**
				 * 显示默认信息（logo、版本、Usage）
				 */
				private function showDefaultInfo(SymfonyStyle $io): void
				{
					$io->writeln($this->logo);
					$io->newLine();
					$io->writeln('<fg=green>SKernel version 1.0.0</>');
					$io->newLine();
					$io->writeln('<fg=yellow>Usage:</> composer skernel [command] [options]');
					$io->newLine();
					$io->writeln('<fg=yellow>Available commands:</>');
					$io->writeln('  serve    Run the development server with hot reload');
					$io->writeln('  build    Build a binary self-executable file (outside the host environment).');
					$io->newLine();
					$io->success('Welcome to super-kernel CLI!');
				}

				/**
				 * 将输入交给调度器处理
				 */
				private function dispatch(string $subcommand): void
				{
					(match ($subcommand) {
						'serve' => new ServeCommandHandler,
						'build' => new BuildCommandHandler,
						default => throw new RuntimeException(sprintf('Unknown command "%s"', $subcommand)),
					})->handle();
				}
			},
		];
	}
}
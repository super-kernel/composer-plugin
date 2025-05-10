<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use SuperKernel\ComposerPlugin\EventHandler\PostAutoloadDumpEventHandler;

/**
 * @SuperKernelPlugin
 * @\ComposerPlugin\SuperKernelPlugin
 */
final class SuperKernelPlugin implements PluginInterface
{
	/**
	 * @inheritDoc
	 */
	public function activate(Composer $composer, IOInterface $io): void
	{
		$io->info(sprintf('[%s] plugin activated.', 'super-kernel'));

		$eventDispatcher = $composer->getEventDispatcher();

		$eventDispatcher->addSubscriber(new PostAutoloadDumpEventHandler());
	}

	/**
	 * @inheritDoc
	 */
	public function deactivate(Composer $composer, IOInterface $io)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function uninstall(Composer $composer, IOInterface $io)
	{
	}
}
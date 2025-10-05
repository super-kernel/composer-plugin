<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Generator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SuperKernel\ComposerPlugin\Abstract\GeneratorAbstract;
use SuperKernel\ComposerPlugin\Enum\Target;

final class PackageGenerator extends GeneratorAbstract
{
	private readonly string $targetDir;


	private readonly RecursiveIteratorIterator $iterator;

	public function __construct(string $packageName, private readonly string $sourcePath)
	{
		$this->targetDir = Target::RUNTIME_DIR->get() . 'vendor' . DIRECTORY_SEPARATOR . $packageName;

		$this->iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
		);
	}

	protected function getSourcePath(): string
	{
		return $this->sourcePath;
	}

	protected function getTargetDir(): string
	{
		return $this->targetDir;
	}

	protected function getIterator(): RecursiveIteratorIterator
	{
		return $this->iterator;
	}
}
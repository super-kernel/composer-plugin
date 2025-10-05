<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Generator;

use AppendIterator;
use CallbackFilterIterator;
use FilesystemIterator;
use IteratorIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SuperKernel\ComposerPlugin\Abstract\GeneratorAbstract;
use SuperKernel\ComposerPlugin\Enum\Target;
use function count;
use function iterator_to_array;

final class ProjectFileGenerator extends GeneratorAbstract
{
	private readonly string $targetDir;

	private readonly AppendIterator $iterator;

	public function __construct(array $psr4Dirs, private readonly string $sourcePath)
	{
		$this->targetDir = Target::RUNTIME_DIR->get();

		$this->iterator = new AppendIterator();

		// 添加项目根目录下的文件（仅一层）
		$fs            = new FilesystemIterator($sourcePath, FilesystemIterator::SKIP_DOTS);
		$rootFilesOnly = new CallbackFilterIterator($fs, fn(SplFileInfo $current): bool => $current->isFile());
		// 统一返回可迭代接口
		$this->iterator->append(new IteratorIterator($rootFilesOnly));

		// 添加每个 PSR-4 目录的递归迭代器
		foreach ($psr4Dirs as $dir) {
			$path = rtrim($sourcePath . DIRECTORY_SEPARATOR . $dir, DIRECTORY_SEPARATOR);

			if (!is_dir($path)) {
				continue;
			}

			$recursive = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			);

			$this->iterator->append($recursive);
		}
	}

	public function steps(): int
	{
		return count(iterator_to_array($this->iterator, false));
	}

	protected function getSourcePath(): string
	{
		return $this->sourcePath;
	}

	protected function getTargetDir(): string
	{
		return $this->targetDir;
	}

	protected function getIterator(): AppendIterator
	{
		return $this->iterator;
	}
}
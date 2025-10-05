<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Support;

use Composer\Util\Filesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function getcwd;

enum Target: string
{
	case  RUNTIME_DIR = 'target/runtime/';
	case  DEBUG_DIR   = 'target/debug/';
	case  RELEASE_DIR = 'target/release/';

	public function get(): string
	{
		return new Filesystem()->normalizePath(getcwd()) . DIRECTORY_SEPARATOR . $this->value;
	}

	public function readyDirectory(int $permissions = 0777): void
	{
		$directory = $this->get();

		if (!is_dir($directory)) {
			mkdir($directory, $permissions, true);
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $fileInfo) {
			$fileInfo->isDir()
				? rmdir($fileInfo->getRealPath())
				: unlink($fileInfo->getRealPath());
		}
	}
}
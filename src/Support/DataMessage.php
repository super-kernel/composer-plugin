<?php
declare(strict_types=1);

namespace SuperKernel\ComposerPlugin\Support;

final class DataMessage
{
	public function __construct(
		public bool    $status,
		public ?string $message = null,
		public mixed   $data = null,
	)
	{
	}
}
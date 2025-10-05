<?php
declare(strict_types=1);

namespace SKernel\Annotation;

use Attribute;

#[
	Attribute(Attribute::TARGET_ALL),
]
final class Inject
{
}
<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Exception;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

/**
 * Reports a configuration value outside the canonical return-target bounds.
 */
final class InvalidReturnTargetConfigurationException extends InvalidArgumentMaatifyException implements ReturnTargetExceptionInterface {}

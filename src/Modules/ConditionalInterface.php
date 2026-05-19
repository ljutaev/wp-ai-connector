<?php
declare(strict_types=1);

namespace WPAIConnector\Modules;

interface ConditionalInterface {

	public function is_met(): bool;

	public function reason(): string;
}

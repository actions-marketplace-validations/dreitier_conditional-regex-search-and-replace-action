<?php
declare(strict_types=1);
namespace App\Variable;
use App\EnvironmentVariable;

/**
 * Container for holding all variable contexts
 */
class Collection
{
	private array $items = [];

	const WELL_KNOWN_VARIABLE_NAMES = [
		'docker_image_tag',
		'git_tag',
		'git_branch'
	];

	public function __construct(
		private readonly ?object $onMissingVariable = null,
	)
	{
	}

	/**
	 * All items in this container
	 * @return array
	 */
	public function items(): array
	{
		return array_values($this->items);
	}

	/**
	 * Get all variable names
	 * @return array
	 */
	public function variableNames(): array
	{
		return array_keys($this->items);
	}

	public function raw(): array
	{
		return $this->items;
	}

	public function add(Variable $variable): Collection
	{
		$this->items[$variable->name] = $variable;
		return $this;
	}

	public function get($name): ?Variable
	{
		return $this->items[$name] ?? null;
	}

	/**
	 * Locate the given variable names from the environment and add them to this container instance
	 *
	 * @param array environmentVariableNames
	 * @return Collection
	 */
	public function locateAndMerge(array $environmentVariableNames = []): Collection
	{
		$r = collect(EnvironmentVariable::fromFriendlyNames($environmentVariableNames, $this->onMissingVariable))
			->map(fn($item) => new Variable($item->friendlyName, $item->value))
			->toArray();

		foreach ($r as $variable) {
			$this->add($variable);
		}

		return $this;
	}

	/**
	 * Merges the well known variables to this instance
	 *
	 * @throws \Exception if not at least one of the well-known variable names is found.
	 * @param array wellKnownVariables
	 * @return Collection
	 */
	public function mergeWellKnownVariables(?array $wellKnownVariables = []): Collection
	{
		if (!$wellKnownVariables) {
			$wellKnownVariables = self::WELL_KNOWN_VARIABLE_NAMES;
		}

		$sizeBefore = sizeof($this->items);
		$this->locateAndMerge($wellKnownVariables);

		if (sizeof($this->items) == $sizeBefore) {
			throw new MissingWellKnownVariableException("At least one of the well-known variables (" . implode(", ", $wellKnownVariables) . ") must be provided");
		}

		return $this;
	}
}

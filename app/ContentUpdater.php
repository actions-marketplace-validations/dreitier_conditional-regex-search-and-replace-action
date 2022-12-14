<?php
declare(strict_types=1);

namespace App;

use App\Events\LineModifiedEvent;
use App\Events\LogEvent;
use App\Variable\Variable;
use App\Variable\Collection as VariableCollection;

class ContentUpdater
{
    public function __construct(
        public readonly Variable           $variable,
        public readonly array              $replacers,
        public readonly VariableCollection $allVariables,
    )
    {
    }

    public function updateFile($path, $newFilePath = null)
    {
        $content = file_get_contents($path);
        $content = $this->update($content);
        $targetFilePath = $newFilePath ?? $path;

        file_put_contents($targetFilePath, $content);
    }

    public function update(string $content): string
    {
        $contentInEdit = $content;

        foreach ($this->replacers as $replacer) {
            LogEvent::info("      Trying to replace '/{$replacer->regex}/' with '{$this->variable->value}'");

            if (preg_match_all('/' . $replacer->regex . '/', $contentInEdit, $matches)) {
                $totalMatches = sizeof($matches[0]);

                LogEvent::info("        Found $totalMatches matches, replacing:");

                for ($i = 0; $i < $totalMatches; $i++) {
                    $lineFound = $matches[0][$i];
                    $newLine = $lineFound;

                    // only find named capturing groups
                    $variableNameReferences = collect(array_keys($matches))->where(fn($item) => is_string($item))->toArray();

                    foreach ($variableNameReferences as $variableName) {
                        $variable = $this->allVariables->get($variableName);

                        if (!$variable) {
                            LogEvent::warn("You are referencing variable '$variableName' but this is not registered. Only [" . implode(", ", $this->allVariables->variableNames()) . "] are available. Maybe you've misspelled the variable name?");
                        }

                        $oldValue = $matches[$variableName][$i];
                        $newValue = $variable->value;
                        $newLine = str_replace($oldValue, $newValue, $newLine);

                        LineModifiedEvent::dispatch($variableName, $oldValue, $newValue);
                    }

                    $contentInEdit = str_replace($lineFound, $newLine, $contentInEdit);

                    LogEvent::info("          Converted line '$lineFound' to '$newLine'");
                }
            } else {
                LogEvent::warn("        No matches found for '/{$replacer->regex}/'");
            }
        }

        return $contentInEdit;
    }
}

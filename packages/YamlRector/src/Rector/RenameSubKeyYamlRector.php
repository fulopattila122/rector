<?php declare(strict_types=1);

namespace Rector\YamlRector\Rector;

use Nette\Utils\Strings;
use Rector\YamlRector\Contract\YamlRectorInterface;

final class RenameSubKeyYamlRector implements YamlRectorInterface
{
    /**
     * @var string[]
     */
    private $pathsToNewKeys = [];

    /**
     * @param string[] $pathsToNewKeys
     */
    public function __construct(array $pathsToNewKeys)
    {
        $this->pathsToNewKeys = $pathsToNewKeys;
    }

    public function isCandidate(string $content): bool
    {
        foreach ($this->pathsToNewKeys as $path => $newKey) {
            $pathSteps = Strings::split($path, '#[\s+]?>[\s+]?#');
            $pathPattern = $this->createPattern($pathSteps);

            if ((bool) Strings::match($content, $pathPattern)) {
                return true;
            }
        }

        return false;
    }

    public function refactor(string $content): string
    {
        foreach ($this->pathsToNewKeys as $path => $newKey) {
            $pathSteps = Strings::split($path, '#[\s+]?>[\s+]?#');
            $pathPattern = $this->createPattern($pathSteps);

            while (Strings::match($content, $pathPattern)) {
                $replacement = $this->createReplacement($pathSteps, $newKey);
                $content = Strings::replace($content, $pathPattern, $replacement, 1);
            }
        }

        return $content;
    }

    /**
     * @param string[] $pathSteps
     */
    private function createPattern(array $pathSteps): string
    {
        $pattern = '';
        foreach ($pathSteps as $key => $pathStep) {
            if ($key === (count($pathSteps) - 1)) {
                // last only up-to the key name
                $pattern .= sprintf('(%s)(.*?)', preg_quote($pathStep));
            } else {
                // see https://regex101.com/r/n8XPbV/3/ for ([^:]*?)
                $pattern .= sprintf('(%s:\s+)([^:]*?)', preg_quote($pathStep));
            }
        }

        return '#^' . $pattern . '#ms';
    }

    /**
     * @param string[] $pathSteps
     */
    private function createReplacement(array $pathSteps, string $newKey): string
    {
        $replacement = '';

        $final = 2 * count($pathSteps);
        for ($i = 1; $i < $final - 1; ++$i) {
            $replacement .= '$' . $i;
        }

        $replacement .= preg_quote($newKey);
        $replacement .= '$' . ($i + 3);

        return $replacement;
    }
}

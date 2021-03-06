<?php declare(strict_types=1);

namespace Rector\Parser;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Parser as NikicParser;

final class Parser
{
    /**
     * @var NikicParser
     */
    private $nikicParser;

    /**
     * @var Stmt[][]
     */
    private $nodesByFile = [];

    public function __construct(NikicParser $nikicParser)
    {
        $this->nikicParser = $nikicParser;
    }

    /**
     * @return Node[]
     */
    public function parseFile(string $filePath): array
    {
        if (isset($this->nodesByFile[$filePath])) {
            return $this->nodesByFile[$filePath];
        }

        $fileContent = file_get_contents($filePath);
        $this->nodesByFile[$filePath] = (array) $this->nikicParser->parse($fileContent);

        return $this->nodesByFile[$filePath];
    }
}

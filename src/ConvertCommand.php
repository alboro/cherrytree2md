<?php
namespace Ctb2Md;

use Symfony\Component\Console\{Command\Command, Input\InputArgument, Input\InputInterface, Output\OutputInterface};
use Ctb2Md\Entity\Node;
use Ctb2Md\Entity\Relation;
use Ctb2Md\Factory\EntityManagerFactory;
use Ctb2Md\Repository\NodeRepository;
use Ctb2Md\Repository\RelationRepository;
use Doctrine\DBAL\DriverManager;
use League\HTMLToMarkdown\HtmlConverter;

class ConvertCommand extends Command {
    protected static $defaultName = 'convert';
    private string $assetsDir = '';

    protected function configure(): void {
        $this->setName('convert')
            ->setDescription('Convert CherryTree .ctb to Markdown')
            ->addArgument('ctb', InputArgument::REQUIRED, 'Path to .ctb file')
            ->addArgument('out', InputArgument::REQUIRED, 'Output folder for .md files');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int {
        $ctbFilePath = $in->getArgument('ctb');
        $outDir = rtrim($in->getArgument('out'), '/');
        @mkdir($outDir, 0777, true);

        // Create assets directory
        $baseOutputDir = $outDir . '/' . basename($ctbFilePath, '.ctb');
        $this->assetsDir = $baseOutputDir . '/assets';
        if (!is_dir($this->assetsDir)) {
            mkdir($this->assetsDir, 0777, true);
            $out->writeln("<info>Created assets directory: {$this->assetsDir}</info>");
        }

        $entityManager = (new EntityManagerFactory(dirname(__DIR__)))->entityManager(
            $ctbFilePath
        );

        /** @var $repo RelationRepository */
        $repoRelation = $entityManager->getRepository(Relation::class);
        try {
            $tree = $repoRelation->buildTree();

            // Create file tree instead of JSON output
            $this->createFileTree($tree, $outDir . '/' . basename($ctbFilePath, '.ctb'), $out);

        } catch (\Exception $e) {
            $out->writeln("<error>Error in buildTree(): " . $e->getMessage() . "</error>");

            // Fallback: show simple data
            $relations = $repoRelation->findAll();
            $out->writeln("Found relations: " . count($relations));

            foreach (array_slice($relations, 0, 3) as $relation) {
                $out->writeln("Relation: nodeId={$relation->nodeId()}, parentId={$relation->parentId()}, sequence={$relation->sequence()}");
            }
        }

        $out->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }

    /**
     * Recursively creates file and directory tree from CherryTree structure
     *
     * @param Relation[] $relations Array of Relation objects
     * @param string $currentPath Current path for file creation
     * @param OutputInterface $out For process information output
     */
    private function createFileTree(array $relations, string $currentPath, OutputInterface $out): void
    {
        foreach ($relations as $relation) {
            /** @var Relation $relation */
            $node = $relation->node();
            $nodeName = $this->sanitizeFileName($node->name());

            // Get child elements
            $children = $relation->children();

            if (!empty($children)) {
                // If there are child elements - create directory
                $dirPath = $currentPath . '/' . $nodeName;

                if (!is_dir($dirPath)) {
                    mkdir($dirPath, 0777, true);
                    $out->writeln("<info>Created directory: {$dirPath}</info>");
                }

                // Create file with root node content in directory
                $rootFileName = $dirPath . '/' . $nodeName . '.md';
                $this->createNodeFile($node, $rootFileName, $out);

                // Recursively process child elements
                $this->createFileTree($children, $dirPath, $out);

            } else {
                // If no child elements - create simple file
                $filePath = $currentPath . '/' . $nodeName . '.md';
                $this->createNodeFile($node, $filePath, $out);
            }
        }
    }

    /**
     * Creates file with node content
     *
     * @param Node $node CherryTree node
     * @param string $filePath File path
     * @param OutputInterface $out For information output
     */
    private function createNodeFile(Node $node, string $filePath, OutputInterface $out): void
    {
        $content = $node->content();

        // If content is in rich text format, process XML and change extension to .md
        if ($node->isRich()) {
            $content = $this->processRichText($node, $content);
        }

        // Add metadata to the beginning of file
        $metadata = "---\n";
        $metadata .= "Title: " . $node->name() . "\n";
        $metadata .= "Sequence: " . $node->relation()->sequence() . "\n";
        $metadata .= "Created: " . date('Y-m-d H:i:s', $node->tsCreation()) . "\n";
        if (!empty($node->tags())) {
            $metadata .= "Tags: " . $node->tags() . "\n";
        }
        if ($node->syntax() !== 'plain-text') {
            $metadata .= "Syntax: " . $node->syntax() . "\n";
        }
        if (($node->tsLastSave() !== null) && ($node->tsLastSave() !== 0) && ($node->tsLastSave() !== $node->tsCreation())) {
            $metadata .= "Modified: " . date('Y-m-d H:i:s', $node->tsLastSave()) . "\n";
        }
        $metadata .= "---\n\n";

        $fullContent = $metadata . $content;

        if (file_put_contents($filePath, $fullContent) !== false) {
            $out->writeln("<info>Created file: {$filePath}</info>");
        } else {
            $out->writeln("<error>Error creating file: {$filePath}</error>");
        }
    }

    /**
     * Processes CherryTree rich text format, replacing tags with markdown and embedding content
     *
     * @param Node $node Node with rich text content
     * @param string $content XML content
     * @return string Processed markdown
     */
    private function processRichText(Node $node, string $content): string
    {
        // Check that content is not empty and contains XML
        if (empty($content) || trim($content) === '') {
            return '';
        }

        // Check that this is actually XML (starts with '<')
        $trimmedContent = trim($content);
        if (!str_starts_with($trimmedContent, '<')) {
            // If not XML, return as is
            return $content;
        }

        // Load XML with error handling
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);

        if (!$doc->loadXML($content)) {
            // If XML is invalid, return original content
            libxml_clear_errors();
            return $content;
        }

        libxml_clear_errors();

        $richTextNodes = $doc->getElementsByTagName('rich_text');
        $result = '';
        $index = 0;

        foreach ($richTextNodes as $richTextNode) {
            $text = $richTextNode->textContent;

            // Process attributes for formatting
            $formattedText = $this->applyRichTextFormatting($text, $richTextNode);

            // Replace with corresponding content by offset
            $embeddedContent = $this->getEmbeddedContentByOffset($node, $index);
            if ($embeddedContent) {
                $result .= $embeddedContent . "\n\n";
            } else {
                $result .= $formattedText;
            }

            $index++;
        }

        return $result;
    }

    /**
     * Applies formatting to text based on rich_text attributes
     *
     * @param string $text Source text
     * @param \DOMElement $element rich_text element with attributes
     * @return string Formatted markdown text
     */
    private function applyRichTextFormatting(string $text, \DOMElement $element): string
    {
        // Process various formatting attributes
        if ($element->hasAttribute('scale')) {
            $scale = $element->getAttribute('scale');
            switch ($scale) {
                case 'h1':
                    return "# " . $text;
                case 'h2':
                    return "## " . $text;
                case 'h3':
                    return "### " . $text;
                case 'h4':
                    return "#### " . $text;
                case 'h5':
                    return "##### " . $text;
                case 'h6':
                    return "###### " . $text;
            }
        }

        if ($element->hasAttribute('weight')) {
            $weight = $element->getAttribute('weight');
            if ($weight === 'heavy') {
                $text = "**" . $text . "**";
            }
        }

        if ($element->hasAttribute('style')) {
            $style = $element->getAttribute('style');
            if ($style === 'italic') {
                $text = "*" . $text . "*";
            }
        }

        if ($element->hasAttribute('underline')) {
            $underline = $element->getAttribute('underline');
            if ($underline === 'single') {
                $text = "<u>" . $text . "</u>";
            }
        }

        if ($element->hasAttribute('link')) {
            $link = $element->getAttribute('link');
            if (str_starts_with($link, 'webs ')) {
                $url = substr($link, 5);
                $text = "[" . $text . "](" . $url . ")";
            }
        }

        return $text;
    }

    /**
     * Gets embedded content (image, table, codebox) by offset index
     *
     * @param Node $node Node
     * @param int $offset Position index in rich text
     * @return string|null Embedded content or null
     */
    private function getEmbeddedContentByOffset(Node $node, int $offset): ?string
    {
        // Check images
        foreach ($node->images() as $image) {
            if ($image->offset() === $offset) {
                return $this->formatImageContent($image);
            }
        }

        // Check tables (grids)
        foreach ($node->grids() as $grid) {
            if ($grid->offset() === $offset) {
                return $this->formatGridContent($grid);
            }
        }

        // Check codeboxes
        foreach ($node->codeboxes() as $codebox) {
            if ($codebox->offset() === $offset) {
                return $this->formatCodeboxContent($codebox);
            }
        }

        return null;
    }

    /**
     * Formats image to markdown
     *
     * @param \Ctb2Md\Entity\Image $image Image object
     * @return string Markdown representation of image
     */
    private function formatImageContent(\Ctb2Md\Entity\Image $image): string
    {
        $link = $image->link();

        if ($link) {
            // If there's an external link, use it
            $filename = $image->filename() ?: 'external_image';
            return "[![{$filename}]({$link})]({$link})";
        } else {
            // Save image as separate file
            $filename = $image->nodeId() . '_' . $image->offset() . '.png';
            $filePath = $this->assetsDir . '/' . $filename;

            // Save PNG data to file
            if (file_put_contents($filePath, $image->png()) !== false) {
                // Return relative link to image
                return "![{$filename}](assets/{$filename})";
            } else {
                // If failed to save file, return fallback
                return "![Image](data:image/png;base64," . base64_encode($image->png()) . ")";
            }
        }
    }

    /**
     * Formats table to markdown
     *
     * @param \Ctb2Md\Entity\Grid $grid Table object
     * @return string Markdown representation of table
     */
    private function formatGridContent(\Ctb2Md\Entity\Grid $grid): string
    {
        $content = $grid->txt();

        // Remove XML declaration if present
        $content = preg_replace('/<\?xml[^>]*\?>\s*/', '', $content);

        // If content is empty after cleanup
        if (empty(trim($content))) {
            return "*Empty table*";
        }

        // Convert CherryTree XML directly to Markdown table
        $markdownTable = $this->convertCherryTreeTableToMarkdown($content);

        // Add column information as comment
        $result = $markdownTable;
        $result .= "\n\n*Table info: Columns " . $grid->colMin() . "-" . $grid->colMax() . "*";

        return $result;
    }

    /**
     * Converts CherryTree XML table directly to Markdown
     *
     * @param string $cherryTreeXml CherryTree XML with <table><row><cell> tags
     * @return string Markdown table
     */
    private function convertCherryTreeTableToMarkdown(string $cherryTreeXml): string
    {
        // Load XML
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        if (!$doc->loadXML($cherryTreeXml)) {
            // If XML is invalid, return as is
            libxml_clear_errors();
            return $cherryTreeXml;
        }
        
        libxml_clear_errors();
        
        $table = $doc->getElementsByTagName('table')->item(0);
        if (!$table) {
            return $cherryTreeXml;
        }
        
        $rows = $table->getElementsByTagName('row');
        if ($rows->length === 0) {
            return "*Empty table*";
        }
        
        $markdownRows = [];
        $maxColumns = 0;
        $headerRowIndex = -1;
        
        // Process all rows and search for headers
        foreach ($rows as $index => $row) {
            $cells = $row->getElementsByTagName('cell');
            $markdownCells = [];
            
            foreach ($cells as $cell) {
                $cellContent = trim($cell->textContent);
                // Escape pipe characters in cell content
                $cellContent = str_replace('|', '\\|', $cellContent);
                $markdownCells[] = $cellContent;
            }
            
            $maxColumns = max($maxColumns, count($markdownCells));
            $markdownRows[] = $markdownCells;
            
            // Check if this row is headers
            // Look for characteristic words for headers
            $rowText = implode(' ', $markdownCells);
            if (preg_match('/причины|пояснение|срок|попадания|header|заголовок|name|description|category|status/ui', $rowText)) {
                $headerRowIndex = $index;
            }
        }
        
        if (empty($markdownRows)) {
            return "*Empty table*";
        }
        
        // Create markdown table
        $result = '';
        
        // If we found header row, use it as first
        if ($headerRowIndex >= 0) {
            $headerRow = $markdownRows[$headerRowIndex];
            // Remove headers from data rows array
            unset($markdownRows[$headerRowIndex]);
            // Reindex array
            $markdownRows = array_values($markdownRows);
        } else {
            // If no headers found, use first row
            $headerRow = array_shift($markdownRows);
        }
        
        // Fill with empty cells up to maximum number of columns
        while (count($headerRow) < $maxColumns) {
            $headerRow[] = '';
        }
        
        $result .= '| ' . implode(' | ', $headerRow) . ' |' . "\n";
        
        // Separator row
        $separator = [];
        for ($i = 0; $i < $maxColumns; $i++) {
            $separator[] = '---';
        }
        $result .= '| ' . implode(' | ', $separator) . ' |' . "\n";
        
        // Other rows
        foreach ($markdownRows as $row) {
            // Fill with empty cells up to maximum number of columns
            while (count($row) < $maxColumns) {
                $row[] = '';
            }
            $result .= '| ' . implode(' | ', $row) . ' |' . "\n";
        }
        
        return $result;
    }

    /**
     * Formats codebox to markdown
     *
     * @param \Ctb2Md\Entity\Codebox $codebox Codebox object
     * @return string Markdown representation of codebox
     */
    private function formatCodeboxContent(\Ctb2Md\Entity\Codebox $codebox): string
    {
        $syntax = $codebox->syntax() ?: '';
        $content = "```" . $syntax . "\n";
        $content .= $codebox->txt() . "\n";
        $content .= "```";

        return $content;
    }

    /**
     * Cleans filename from invalid characters
     *
     * @param string $name Source name
     * @return string Cleaned name
     */
    private function sanitizeFileName(string $name): string
    {
        // Remove empty strings and null values
        if (empty($name) || trim($name) === '') {
            return 'unnamed_node';
        }

        // Convert to string just in case
        $sanitized = (string) $name;

        // Replace filesystem-invalid characters
        $sanitized = preg_replace('/[<>:"\\/\\\\|?*]/', '_', $sanitized);

        // Replace leading dots (hidden files in Unix)
        $sanitized = preg_replace('/^\.+/', '', $sanitized);

        // Remove multiple spaces and replace with single underscores
        $sanitized = preg_replace('/\s+/', '_', $sanitized);

        // Remove multiple underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);

        // Remove underscores at beginning and end
        $sanitized = trim($sanitized, '_');

        // If name is empty after cleanup, use fallback
        if (empty($sanitized) || preg_match('/^\.+$/', $sanitized)) {
            $sanitized = 'unnamed_node';
        }

        return $sanitized;
    }
}

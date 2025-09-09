# CherryTree to Markdown Converter

Converts CherryTree files (`.ctb`) to markdown files, preserving hierarchical structure and embedded content.

> **Note**: This project is designed to help migrate from CherryTree to modern note-taking applications like Obsidian and Logseq. The data models are based on [fractalnote](https://github.com/alboro/fractalnote).

## Features

- **Structure Preservation**: Maintains CherryTree node hierarchy as directories and files
- **Rich Text Support**: Converts formatting to Markdown syntax
- **Embedded Content**: Handles images, tables, and code blocks
- **Assets Management**: Extracts images to separate `assets/` directory
- **Metadata Export**: Preserves node metadata in YAML frontmatter
- **File Naming**: Sanitizes node names for filesystem compatibility

## Requirements

- PHP >= 8.4
- Composer
- SQLite support

## Installation

### Local Installation
```bash
composer install
chmod +x bin/ctb2md
```

### Docker Installation
```bash
# Build image
docker-compose build
```

## Usage

### Local Usage
```bash
./bin/ctb2md convert <ctb_file> <output_directory>
```

### Docker Usage
```bash
# Place your .ctb file in ./input/ directory
cp your-file.ctb input/

# Run conversion
docker-compose run --rm cherrytree2md convert /input/your-file.ctb /output

# Or use one-liner
docker run --rm -v $(pwd)/input:/input:ro -v $(pwd)/output:/output cherrytree2md convert /input/your-file.ctb /output
```

### Development with Docker
```bash
# Interactive shell for development
docker-compose run --rm cherrytree2md-dev

# Run with memory limit
docker-compose run --rm cherrytree2md php -d memory_limit=1G bin/ctb2md convert /input/large-file.ctb /output
```

### Output Structure

```
output/
└── demo/
    ├── assets/                 # Images: {node_id}_{offset}.png
    │   ├── 123_0.png
    │   └── 456_1.png
    ├── laravel.md
    ├── mock-server.md
    └── bugs/
        ├── bugs.md
        └── user/
```

### File Format

Generated markdown files include YAML frontmatter with metadata:

```yaml
---
Title: Node Name
Sequence: 7
Created: 2024-01-15 10:30:00
Tags: tag1,tag2             # Only if present
Syntax: rich-text           # Only if not plain-text
Modified: 2024-01-20 15:45:00  # Only if different from created
---
```

## Supported Content

### Text Formatting
- **Bold** (`**bold**`), *Italic* (`*italic*`), <u>Underlined</u>
- Headers (# H1, ## H2, etc.), Links `[text](url)`

### Embedded Content

#### Images
- Extracted to `assets/` directory as `{node_id}_{offset}.png`
- Referenced with relative paths: `![image](assets/123_0.png)`
- External links preserved as-is

#### Tables
Converts CherryTree XML tables to standard Markdown:

```xml
<table>
    <row><cell>Name</cell><cell>Status</cell></row>
    <row><cell>Project A</cell><cell>Active</cell></row>
</table>
```

Becomes:

```markdown
| Name | Status |
| --- | --- |
| Project A | Active |

*Table info: Columns 0-2*
```

Features: Automatic header detection, pipe escaping, empty cell handling.

#### Code Blocks
Preserves syntax highlighting from CherryTree codeboxes:

```php
echo "Hello World";
```

## Architecture

### CherryTree Database Structure
- **Node ↔ Relation**: One-to-one bidirectional relationship
- **Tree Structure**: Built through Relations, not direct Node connections
- **Key Tables**: `node` (content), `children` (structure), `image`/`grid`/`codebox` (attachments)

### Project Structure
```
src/
├── ConvertCommand.php          # Main conversion logic
├── Entity/                     # Doctrine entities
├── Repository/                 # Data access layer
└── Factory/                    # Doctrine configuration
```

### Key Classes
- **ConvertCommand**: Main orchestrator (reads DB, builds tree, converts content)
- **RelationRepository**: Tree structure logic (`buildTree()`, traversal)
- **Node Entity**: Represents nodes with content, metadata, embedded objects

## Dependencies

- **Symfony Console**: Command-line interface
- **Doctrine ORM/DBAL**: Database abstraction
- **League HTML-to-Markdown**: Content conversion

## Development

### Memory Considerations
For large files:
```bash
php -d memory_limit=1G bin/ctb2md convert large_file.ctb output
```

### Debugging
Includes verbose output for directory creation, file conversion status, and error handling.

## Limitations

- Some CherryTree-specific formatting approximated in Markdown
- Large files may require memory limit adjustments
- Complex tables converted to simple Markdown tables

## Contributing

1. Fork repository → 2. Create feature branch → 3. Make changes → 4. Submit PR

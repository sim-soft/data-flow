---
title: Spreadsheet
nav_order: 14
---

# Spreadsheet (PhpSpreadsheet)

Read and write Excel files (.xlsx, .xls) using PhpSpreadsheet. For
high-performance streaming of large files,
see [SpoutExtractor/SpoutLoader](02-USEFUL_PROCESSORS.md).

## Reading Spreadsheets

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Extractors\SpreadsheetExtractor;

$result = (new DataFlow())
    ->from(SpreadsheetExtractor::from('data/members.xlsx'))
    ->load(function (array $row) {
        echo $row['name'] . ' - ' . $row['email'] . PHP_EOL;
    })
    ->run();
```

The first row is automatically used as column headers. Each subsequent row
yields an associative array.

### Custom Headers

```php
$extractor = SpreadsheetExtractor::from('data/report.xlsx')
    ->headers(['id', 'name', 'amount', 'date']);
```

### Selecting a Sheet

```php
$extractor = SpreadsheetExtractor::from('data/workbook.xlsx')
    ->sheet('Transactions');
```

### Reading from Pipeline (Dynamic File Paths)

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Extractors\SpreadsheetExtractor;

// Pass null to constructor — file paths come from upstream
(new DataFlow())
    ->from(['file1.xlsx', 'file2.xlsx', 'file3.xlsx'])
    ->from(new SpreadsheetExtractor(null))
    ->load(fn($row) => processRow($row))
    ->run();
```

## Writing Spreadsheets

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Loaders\SpreadsheetLoader;

(new DataFlow())
    ->from($records)
    ->load(new SpreadsheetLoader('output/report', docType: 'Xlsx'))
    ->run();
// Creates: output/report_20250517-143022.xlsx (with timestamp)
```

### Parameters

```php
new SpreadsheetLoader(
    filePath: 'output/report',   // base path (without extension)
    docType: 'Xlsx',             // 'Xlsx' or 'Csv' (default: 'Xlsx')
    cacheDir: '/tmp/cache',      // optional cache directory for large files
)
```

### Append Mode

Disable the automatic timestamp suffix to overwrite the same file.

```php
(new DataFlow())
    ->from($records)
    ->load((new SpreadsheetLoader('output/latest.xlsx'))->append())
    ->run();
// Creates: output/latest.xlsx (no timestamp)
```

### Sheet Name

```php
(new DataFlow())
    ->from($records)
    ->load(
        (new SpreadsheetLoader('output/report'))
            ->sheet('Sales Data', sheetIndex: 0)
    )
    ->run();
```

## SpreadsheetExtractor vs SpoutExtractor

| Feature         | SpreadsheetExtractor                   | SpoutExtractor           |
|-----------------|----------------------------------------|--------------------------|
| Library         | PhpSpreadsheet                         | Box\Spout                |
| Memory          | Loads entire file                      | Streams row-by-row       |
| Best for        | Small/medium files, complex formatting | Large files (100K+ rows) |
| Formats         | xlsx, xls, csv, ods                    | xlsx, csv, ods           |
| Cell formatting | Full access                            | Read-only values         |

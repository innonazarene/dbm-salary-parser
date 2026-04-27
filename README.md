# dbm-salary-grade-parser
[![Tests](https://github.com/innonazarene/dbm-salary-grade-parser/actions/workflows/ci.yml/badge.svg)](https://github.com/innonazarene/dbm-salary-grade-parser/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Philippine Government Salary Grade schedule as a PHP library.

Data source: **National Budget Circular No. 601 (2026)** — Department of Budget and Management (DBM).

---

## Requirements

- PHP 8.1+
- For PDF extraction: `smalot/pdfparser`

```bash
composer require smalot/pdfparser
```

## Installation

```bash
composer require innonazarene/dbm-salary-grade-parser
```

---

## Usage

### Parse a salary schedule PDF

```php
use PhSalaryGrade\SalaryGradeParser;

// From a PDF file — extracts text then parses the salary table
$schedule = SalaryGradeParser::parseFile('/path/to/nbc601.pdf');

// Parse a specific table region (e.g. "First Class" in LGU circulars)
// The parser will skip everything until it finds the specified keyword
$firstClassSchedule = SalaryGradeParser::parseFile('/path/to/lbc165.pdf', 'First Class');

// $schedule is keyed by salary grade, each value is an array of step amounts
// [
//   1  => [14061, 14164, 14278, 14393, 14509, 14626, 14743, 14862],
//   2  => [14925, 15035, ...],
//   ...
//   30 => [203200, 206401, ...],
// ]

// Access a specific grade + step
$grade15step3 = $schedule[15][2]; // step index is 0-based → 41006

// Or use the SalaryGrade lookup class with built-in NBC 601 data
use PhSalaryGrade\SalaryGrade;
$salary = SalaryGrade::get(15, 3); // → 41006
```

### Parse from raw text (if you already extracted it)

```php
use PhSalaryGrade\SalaryGradeParser;

$text = file_get_contents('salary_text.txt');
$schedule = SalaryGradeParser::parse($text);

// Or with a keyword to target a specific table
$schedule = SalaryGradeParser::parse($text, 'Special Cities');
```

### Extract text from PDF only

```php
use PhSalaryGrade\PdfExtractor;

$text = PdfExtractor::extract('/path/to/nbc601.pdf');
echo $text; // raw text from all pages
```

### How extraction works

1. **`PdfExtractor::extract()`** — reads the PDF using `smalot/pdfparser` and reconstructs the table rows mathematically based on the exact [X, Y] coordinates of the text on the page!
2. **`SalaryGradeParser::parse()`** — scans each line, finds rows that start with a grade number (1–30) followed by salary amounts in the ₱10,000–₱999,999 range, validates that steps are ascending, and returns a clean array

### Using the static schedule (no PDF needed)

```php
use PhSalaryGrade\SalaryGrade;

// Get salary for Grade 15, Step 3
$salary = SalaryGrade::get(15, 3);
// → 41006

// Get salary for Grade 10, Step 1 (default)
$salary = SalaryGrade::get(10);
// → 25586

// Get all 8 steps for a grade
$steps = SalaryGrade::getSteps(24);
// → [1 => 98185, 2 => 99721, 3 => 101283, ..., 8 => 109431]

// Get the full salary schedule (all grades and steps)
$all = SalaryGrade::all();
// → [1 => [1 => 14061, ...], 2 => [...], ..., 30 => [...]]

// Find which grade/step a specific amount belongs to
$match = SalaryGrade::find(40208);
// → ['grade' => 15, 'step' => 1]

// Get all grade/step combinations within a salary range
$results = SalaryGrade::range(40000, 45000);
// → [['grade' => 15, 'step' => 1, 'amount' => 40208], ...]

// Convenience: min and max salaries
SalaryGrade::min(); // → 14061  (Grade 1, Step 1)
SalaryGrade::max(); // → 226319 (Grade 30, Step 8)
```

---

## Laravel Integration Example (Step-by-Step Workflow)

This package is designed to easily integrate into Laravel applications. Below is the complete structure and workflow for a system where users can upload a PDF, download a generated CSV for review, and upload the reviewed CSV back to the system to be saved.

### 1. Database Migration
Create a table to store the salary tranche data:

```bash
php artisan make:migration create_salary_tranches_table
```

```php
public function up()
{
    Schema::create('salary_tranches', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('circular_no')->nullable();
        $table->string('pdf_path')->nullable();
        $table->string('csv_path')->nullable();
        $table->json('parsed_data'); // Stores the array of salary grades
        $table->timestamps();
    });
}
```

### 2. Eloquent Model
Ensure your model casts the `parsed_data` column to an array so Laravel automatically handles the JSON serialization:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryTranche extends Model
{
    protected $fillable = ['title', 'circular_no', 'pdf_path', 'csv_path', 'parsed_data'];

    protected $casts = [
        'parsed_data' => 'array',
    ];
}
```

### 3. API Routes
Define the endpoints for uploading PDFs, downloading the generated CSV, and uploading the final reviewed CSV:

```php
use App\Http\Controllers\SalaryTrancheController;

// Upload a PDF and generate a CSV
Route::post('/salary-tranches', [SalaryTrancheController::class, 'store']);

// Download the generated CSV for review
Route::get('/salary-tranches/{id}/download-csv', [SalaryTrancheController::class, 'downloadCsv']);

// Upload the reviewed CSV to finalize and save the data
Route::post('/salary-tranches/upload-csv', [SalaryTrancheController::class, 'storeCsv']);
```

### 4. Controller Logic
Create the controller to tie it all together:

```php
namespace App\Http\Controllers;

use App\Models\SalaryTranche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhSalaryGrade\SalaryGradeParser;

class SalaryTrancheController extends Controller
{
    /**
     * Step 1: Upload a PDF. The package parses it and generates a CSV.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'circular_no' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255', // e.g. "First Class"
            'pdf' => 'required|file|mimes:pdf|max:10240'
        ]);

        $pdfPath = $request->file('pdf')->store('salary-tranches/pdfs', 'public');
        $fullPdfPath = Storage::disk('public')->path($pdfPath);

        // Parse PDF using the package (targets a specific keyword/region if provided)
        $parsedData = SalaryGradeParser::parseFile($fullPdfPath, $request->region);

        // Generate a CSV for user review
        $csvPath = 'salary-tranches/csvs/salary_tranche_' . time() . '.csv';
        Storage::disk('public')->makeDirectory('salary-tranches/csvs');
        
        $csvFile = fopen(Storage::disk('public')->path($csvPath), 'w');
        fputcsv($csvFile, ['Salary Grade', 'Step 1', 'Step 2', 'Step 3', 'Step 4', 'Step 5', 'Step 6', 'Step 7', 'Step 8']);
        
        foreach ($parsedData as $row) {
            fputcsv($csvFile, [
                $row['salary_grade'] ?? '', $row['step_1'] ?? '', $row['step_2'] ?? '',
                $row['step_3'] ?? '', $row['step_4'] ?? '', $row['step_5'] ?? '',
                $row['step_6'] ?? '', $row['step_7'] ?? '', $row['step_8'] ?? ''
            ]);
        }
        fclose($csvFile);

        $tranche = SalaryTranche::create([
            'title' => $request->title,
            'circular_no' => $request->circular_no,
            'pdf_path' => $pdfPath,
            'csv_path' => $csvPath,
            'parsed_data' => $parsedData,
        ]);

        return response()->json([
            'message' => 'PDF parsed and CSV generated.',
            'download_csv_url' => url('/api/salary-tranches/' . $tranche->id . '/download-csv')
        ]);
    }

    /**
     * Step 2: Download the generated CSV for manual review/correction.
     */
    public function downloadCsv($id)
    {
        $tranche = SalaryTranche::findOrFail($id);
        return Storage::disk('public')->download($tranche->csv_path, 'salary_table.csv');
    }

    /**
     * Step 3: Upload the reviewed CSV. Saves data to DB and deletes the CSV automatically.
     */
    public function storeCsv(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'csv' => 'required|file|mimes:csv,txt|max:10240'
        ]);

        // Get the temporary uploaded file
        $csvFile = $request->file('csv');
        $path = $csvFile->getRealPath();

        $parsedData = [];
        if (($handle = fopen($path, 'r')) !== false) {
            fgetcsv($handle); // Skip header
            while (($row = fgetcsv($handle)) !== false) {
                if (!empty($row[0])) {
                    $parsedData[] = [
                        'salary_grade' => (int) $row[0],
                        'step_1' => isset($row[1]) && $row[1] !== '' ? (int) $row[1] : null,
                        'step_2' => isset($row[2]) && $row[2] !== '' ? (int) $row[2] : null,
                        'step_3' => isset($row[3]) && $row[3] !== '' ? (int) $row[3] : null,
                        'step_4' => isset($row[4]) && $row[4] !== '' ? (int) $row[4] : null,
                        'step_5' => isset($row[5]) && $row[5] !== '' ? (int) $row[5] : null,
                        'step_6' => isset($row[6]) && $row[6] !== '' ? (int) $row[6] : null,
                        'step_7' => isset($row[7]) && $row[7] !== '' ? (int) $row[7] : null,
                        'step_8' => isset($row[8]) && $row[8] !== '' ? (int) $row[8] : null,
                    ];
                }
            }
            fclose($handle);
        }

        // Store to DB. The uploaded CSV file is automatically deleted by Laravel after this request.
        $tranche = SalaryTranche::create([
            'title' => $request->title,
            'circular_no' => $request->circular_no ?? null,
            'parsed_data' => $parsedData,
        ]);

        return response()->json(['message' => 'Salary tranche stored from CSV successfully!']);
    }
}
```

---

## Salary Schedule (NBC No. 601 — 2026)

| SG  | Step 1  | Step 2  | Step 3  | Step 4  | Step 5  | Step 6  | Step 7  | Step 8  |
|-----|---------|---------|---------|---------|---------|---------|---------|---------|
| 1   | 14,061  | 14,164  | 14,278  | 14,393  | 14,509  | 14,626  | 14,743  | 14,862  |
| 2   | 14,925  | 15,035  | 15,146  | 15,258  | 15,371  | 15,484  | 15,599  | 15,714  |
| 3   | 15,852  | 15,971  | 16,088  | 16,208  | 16,329  | 16,448  | 16,571  | 16,693  |
| 4   | 16,833  | 16,958  | 17,084  | 17,209  | 17,337  | 17,464  | 17,594  | 17,724  |
| 5   | 17,866  | 18,000  | 18,133  | 18,267  | 18,401  | 18,538  | 18,676  | 18,813  |
| 6   | 18,957  | 19,098  | 19,239  | 19,383  | 19,526  | 19,670  | 19,816  | 19,963  |
| 7   | 20,110  | 20,258  | 20,408  | 20,560  | 20,711  | 20,865  | 21,019  | 21,175  |
| 8   | 21,448  | 21,642  | 21,839  | 22,035  | 22,234  | 22,435  | 22,638  | 22,843  |
| 9   | 23,226  | 23,411  | 23,599  | 23,788  | 23,978  | 24,170  | 24,364  | 24,558  |
| 10  | 25,586  | 25,790  | 25,996  | 26,203  | 26,412  | 26,623  | 26,835  | 27,050  |
| 11  | 30,024  | 30,308  | 30,597  | 30,889  | 31,185  | 31,486  | 31,790  | 32,099  |
| 12  | 32,245  | 32,529  | 32,817  | 33,108  | 33,403  | 33,702  | 34,004  | 34,310  |
| 13  | 34,421  | 34,733  | 35,049  | 35,369  | 35,694  | 36,022  | 36,354  | 36,691  |
| 14  | 37,024  | 37,384  | 37,749  | 38,118  | 38,491  | 38,869  | 39,252  | 39,640  |
| 15  | 40,208  | 40,604  | 41,006  | 41,413  | 41,824  | 42,241  | 42,662  | 43,090  |
| 16  | 43,560  | 43,996  | 44,438  | 44,885  | 45,338  | 45,796  | 46,261  | 46,730  |
| 17  | 47,247  | 47,727  | 48,213  | 48,705  | 49,203  | 49,708  | 50,218  | 50,735  |
| 18  | 51,304  | 51,832  | 52,367  | 52,907  | 53,456  | 54,010  | 54,572  | 55,140  |
| 19  | 56,390  | 57,165  | 57,953  | 58,753  | 59,567  | 60,394  | 61,235  | 62,089  |
| 20  | 62,967  | 63,842  | 64,732  | 65,637  | 66,557  | 67,479  | 68,409  | 69,342  |
| 21  | 70,013  | 71,000  | 72,004  | 73,024  | 74,061  | 75,115  | 76,151  | 77,239  |
| 22  | 78,162  | 79,277  | 80,411  | 81,564  | 82,735  | 83,887  | 85,096  | 86,324  |
| 23  | 87,315  | 88,574  | 89,855  | 91,163  | 92,592  | 94,043  | 95,518  | 96,955  |
| 24  | 98,185  | 99,721  | 101,283 | 102,871 | 104,483 | 106,123 | 107,739 | 109,431 |
| 25  | 111,727 | 113,476 | 115,254 | 117,062 | 118,899 | 120,766 | 122,664 | 124,591 |
| 26  | 126,252 | 128,228 | 130,238 | 132,280 | 134,356 | 136,465 | 138,608 | 140,788 |
| 27  | 142,663 | 144,897 | 147,169 | 149,407 | 151,752 | 153,850 | 156,267 | 158,723 |
| 28  | 160,469 | 162,988 | 165,548 | 167,994 | 170,634 | 173,320 | 175,803 | 178,572 |
| 29  | 180,492 | 183,332 | 186,218 | 189,151 | 192,131 | 194,797 | 197,870 | 200,993 |
| 30  | 203,200 | 206,401 | 209,558 | 212,766 | 216,022 | 219,434 | 222,797 | 226,319 |

---

## Running Tests

```bash
composer install
composer test
```

---

## License

MIT. See [LICENSE](LICENSE).

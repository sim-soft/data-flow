# Implementation Plan: Unit Test Suite

## Overview

Implement a comprehensive PHPUnit 11 test suite for the simsoft/data-flow ETL
pipeline library. The suite covers all public classes, traits, interfaces, and
enums with unit tests, property-based tests, and integration tests. Tests mirror
the source structure and use `#[Test]` attributes with PHPUnit's built-in mock
builder for dependency isolation.

## Tasks

- [x]
    1. Set up test infrastructure and base classes

    - [x] 1.1 Create base TestCase class and PHPUnit configuration
        - Create `tests/TestCase.php` with helper methods: `iteratorToArray()`,
          `arrayToIterator()`, `fixturePath()`
        - Create `phpunit.xml` with Unit, Integration, and Properties test
          suites
        - Create directory structure: `tests/Enums/`, `tests/Exceptions/`,
          `tests/Extractors/`, `tests/Transformers/`, `tests/Loaders/`,
          `tests/Traits/`, `tests/Integration/`, `tests/Properties/`,
          `tests/fixtures/`
        - _Requirements: All (infrastructure)_

    - [x] 1.2 Create test fixture files
        - Create `tests/fixtures/sample.xlsx` with 2 sheets: "Profile" (3 rows +
          header), "Address" (3 rows + header)
        - Create `tests/fixtures/sample.csv` with 5 rows and a header row
        - Create `tests/fixtures/empty.xlsx` with a single empty sheet
        - _Requirements: 6.1, 6.3, 6.4, 6.5, 7.1, 7.4, 7.5_

- [x]
    2. Implement core class tests

    - [x] 2.1 Implement DataFlowTest
        - Create `tests/DataFlowTest.php` covering pipeline orchestration
        - Test null initial state, `from()` with array/closure/DataFlow,
          `transform()`, `filter()`, `map()`, `setNewMap()`, `chunk()`,
          `limit()`, `load()`, `preview()` exception, `run()`, multiple
          extractors, multiple transformers
        - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10,
          1.11, 1.12, 1.13, 1.14, 1.15, 1.16_

    - [x] 2.2 Implement CallableProcessorTest
        - Create `tests/CallableProcessorTest.php`
        - Test valid callable construction, invalid callable exception,
          invocation with dataframe, transformed value output, Signal::Next
          skip, Signal::Stop halt
        - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

    - [x] 2.3 Implement PayloadTest
        - Create `tests/PayloadTest.php`
        - Test constructor with initial attributes, property set/get,
          `__isset()` true/false, `__unset()`, `offsetExists()`, `offsetGet()`
          with non-string key, `offsetSet()`, `getAttribute()`, `reset()`
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_

    - [x] 2.4 Implement ProcessorTest
        - Create `tests/ProcessorTest.php` using a concrete anonymous class stub
          extending Processor
        - Test `setFlow()` returns same instance, fluent chaining, Macroable
          trait usage
        - _Requirements: 22.1, 22.2, 22.3_

    - [x] 2.5 Implement SignalTest
        - Create `tests/Enums/SignalTest.php`
        - Test Signal::Next value is 1, Signal::Stop value is 9
        - _Requirements: 4.1, 4.2_

- [x]
    3. Implement exception tests

    - [x] 3.1 Implement exception hierarchy tests
        - Create `tests/Exceptions/DataFlowExceptionTest.php`,
          `ExtractorExceptionTest.php`, `TransformerExceptionTest.php`,
          `LoaderExceptionTest.php`, `InvalidCallableExceptionTest.php`
        - Verify inheritance: DataFlowException extends RuntimeException,
          ExtractorException/TransformerException/LoaderException extend
          DataFlowException, InvalidCallableException extends
          InvalidArgumentException
        - Verify getMessage() returns provided message for each exception
        - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 20.6_

- [x]
    4. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    5. Implement extractor tests

    - [x] 5.1 Implement IterableExtractorTest
        - Create `tests/Extractors/IterableExtractorTest.php`
        - Test array conversion to ArrayIterator, Traversable acceptance,
          non-iterable exception, invocation yields all items, empty array
          yields no items
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

    - [x] 5.2 Implement SpoutExtractorTest
        - Create `tests/Extractors/SpoutExtractorTest.php`
        - Test valid file construction, invalid file ExtractorException,
          `withoutHeaders()`, `sheet()` with name, multi-sheet sequential
          reading
        - Use `tests/fixtures/sample.xlsx` and `tests/fixtures/sample.csv`
        - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

    - [x] 5.3 Implement SpreadsheetExtractorTest
        - Create `tests/Extractors/SpreadsheetExtractorTest.php`
        - Test valid file loading, non-existent file exception, custom headers,
          default first-row headers, sheet selection, null file path reads from
          dataframe
        - Use `tests/fixtures/sample.xlsx`
        - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

    - [x] 5.4 Implement ActiveQueryExtractorTest
        - Create `tests/Extractors/ActiveQueryExtractorTest.php`
        - Mock ActiveQuery interface; test construction, `size()` batch
          parameter, `toArray()`, `make()` factory, invocation yields all rows
        - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

    - [x] 5.5 Implement FileFinderExtractorTest
        - Create `tests/Extractors/FileFinderExtractorTest.php`
        - Mock Flysystem or use in-memory adapter; test path normalization,
          `recursive()`, `fileOnly()`, `directoryOnly()`, default both files and
          directories
        - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [x]
    6. Implement transformer tests

    - [x] 6.1 Implement ChunkTest
        - Create `tests/Transformers/ChunkTest.php`
        - Test chunking with exact division, remainder chunk, single chunk equal
          to count, chunk larger than count, null dataframe
        - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

    - [x] 6.2 Write property test for Chunk count preservation
        - **Property 1: Chunk Count Preservation**
        - Create property test in
          `tests/Properties/TransformerPropertiesTest.php`
        - Use `#[DataProvider]` generating 100+ random inputs with varying array
          sizes and chunk sizes
        - Verify total items across all chunks equals original input count
        - **Validates: Requirements 10.6**

    - [x] 6.3 Implement FilterTest
        - Create `tests/Transformers/FilterTest.php`
        - Test all-true predicate, all-false predicate, selective predicate,
          null dataframe, key preservation
        - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

    - [x] 6.4 Write property tests for Filter
        - **Property 2: Filter Key Preservation**
        - **Property 3: Filter Metamorphic Count**
        - Add to `tests/Properties/TransformerPropertiesTest.php`
        - Use `#[DataProvider]` generating 100+ random inputs
        - Verify output keys are subset of input keys; verify output count ≤
          input count
        - **Validates: Requirements 11.5, 11.6**

    - [x] 6.5 Implement MappingTest
        - Create `tests/Transformers/MappingTest.php`
        - Test string-to-string mappings, callable mappings, missing source key
          default, `newDataFrame()` output-only keys, without `newDataFrame()`
          retains original keys, null dataframe
        - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

- [x]
    7. Implement loader tests

    - [x] 7.1 Implement PreviewTest
        - Create `tests/Loaders/PreviewTest.php`
        - Use output buffering (`ob_start()`/`ob_get_clean()`) to capture
          printed output
        - Test dataframe output, nested Iterator printing, Signal::Next
          passthrough
        - _Requirements: 13.1, 13.2, 13.3_

    - [x] 7.2 Implement VisualizeTest
        - Create `tests/Loaders/VisualizeTest.php`
        - Use output buffering; test FORMAT_JSON output, FORMAT_OBJ var_dump
          output, passthrough behavior, nested Iterator handling
        - _Requirements: 14.1, 14.2, 14.3, 14.4_

    - [x] 7.3 Implement SpoutLoaderTest
        - Create `tests/Loaders/SpoutLoaderTest.php`
        - Use `sys_get_temp_dir()` for output files with cleanup in `tearDown()`
        - Test valid file creation, invalid path LoaderException,
          `withHeaders()`, `withoutHeaders()`, auto-detected headers from array
          keys, non-array UnsupportedTypeException
        - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

    - [x] 7.4 Implement SpreadsheetLoaderTest
        - Create `tests/Loaders/SpreadsheetLoaderTest.php`
        - Use `sys_get_temp_dir()` for output files with cleanup in `tearDown()`
        - Test path/extension parsing, `append()` no timestamp, `sheet()` name,
          array data rows, Iterator batch writing
        - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5_

- [x]
    8. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    9. Implement trait tests

    - [x] 9.1 Implement DataFrameTest
        - Create `tests/Traits/DataFrameTest.php` using an anonymous class that
          uses the DataFrame trait
        - Test `setDataFrame()` with Iterator returns same via `getDataFrame()`,
          null set/get, fluent chaining
        - _Requirements: 17.1, 17.2, 17.3_

    - [x] 9.2 Implement CallableDataFrameTest
        - Create `tests/Traits/CallableDataFrameTest.php` using an anonymous
          class that uses the CallableDataFrame trait
        - Test transformed data output, Signal::Next skip, Signal::Stop halt,
          Iterator yield-from, null return yields original, error callback
          throws DataFlowException, null dataframe yields nothing
        - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7_

    - [x] 9.3 Implement MacroableTest
        - Create `tests/Traits/MacroableTest.php` using an anonymous class that
          uses the Macroable trait
        - Test macro registration and calling, `$this` binding, `mixin()`
          registers public/protected methods, `mixin(replace: false)` no
          overwrite, mixin Closure registration, undefined method
          BadMethodCallException, non-Closure callable via
          `call_user_func_array`
        - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7_

- [x]
    10. Implement integration and property tests

    - [x] 10.1 Implement PipelineTest (integration)
        - Create `tests/Integration/PipelineTest.php`
        - Test full pipeline: array extract → closure transform → collector load
        - Test chained transformers: filter → map → chunk
        - Test Signal::Stop in transformer limits downstream
        - Test Signal::Next skips items in loader output
        - Test Payload shared state across stages
        - Test DataFlow-as-source composition
        - _Requirements: 21.1, 21.2, 21.3, 21.4, 21.5, 21.6_

    - [x] 10.2 Write property test for Identity Pipeline Round-Trip
        - **Property 4: Identity Pipeline Round-Trip**
        - Add to `tests/Properties/TransformerPropertiesTest.php`
        - Use `#[DataProvider]` generating 100+ random non-empty arrays
        - Verify identity transformation produces output equal to input
        - **Validates: Requirements 21.7**

    - [x] 10.3 Write property test for Payload Reset Round-Trip
        - **Property 5: Payload Reset Round-Trip**
        - Add to `tests/Properties/TransformerPropertiesTest.php`
        - Use `#[DataProvider]` generating 100+ random attribute sets with
          random modifications
        - Verify `reset()` restores initial state
        - **Validates: Requirements 3.10, 3.2**

    - [x] 10.4 Write property test for IterableExtractor Round-Trip
        - **Property 6: IterableExtractor Round-Trip**
        - Add to `tests/Properties/TransformerPropertiesTest.php`
        - Use `#[DataProvider]` generating 100+ random arrays
        - Verify IterableExtractor invocation yields identical items in same
          order
        - **Validates: Requirements 5.4**

- [x]
    11. Final checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties using PHPUnit data
  providers with 100+ randomized iterations
- Unit tests validate specific examples and edge cases
- All spreadsheet tests use fixture files in `tests/fixtures/` or temp files
  cleaned up in `tearDown()`
- Mocking uses PHPUnit's built-in mock builder; concrete test doubles (anonymous
  classes) for abstract classes

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "2.4", "2.5", "3.1"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3", "5.1", "9.1"] },
    { "id": 3, "tasks": ["5.2", "5.3", "5.4", "5.5", "6.1", "6.3", "6.5", "9.2", "9.3"] },
    { "id": 4, "tasks": ["6.2", "6.4", "7.1", "7.2", "7.3", "7.4"] },
    { "id": 5, "tasks": ["10.1", "10.2", "10.3", "10.4"] }
  ]
}
```

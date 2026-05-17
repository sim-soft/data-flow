# Requirements Document

## Introduction

A comprehensive PHPUnit test suite for the simsoft/data-flow ETL pipeline
library. The suite covers all public classes, traits, interfaces, and enums with
thorough scenario coverage. Test classes mirror the source structure (
`src/Foo.php` → `tests/FooTest.php`) and use PHPUnit 11 `#[Test]` attributes.
The goal is to verify correctness of the pipeline orchestrator, all built-in
extractors, transformers, loaders, the payload container, signal-based flow
control, the macroable trait, and error handling paths.

## Glossary

- **Test_Suite**: The complete collection of PHPUnit test classes covering the
  data-flow library
- **DataFlow_Pipeline**: The `DataFlow` class that orchestrates extraction,
  transformation, and loading stages
- **Processor**: Abstract base class implementing `Flowable`; parent of
  Extractor, Transformer, and Loader
- **CallableProcessor**: Concrete processor that wraps a closure as a pipeline
  stage
- **Payload**: Shared mutable state container implementing `ArrayAccess`
- **Signal**: Enum with `Next` (skip row) and `Stop` (halt iteration) cases for
  flow control
- **IterableExtractor**: Extractor that converts arrays or Traversable objects
  into an Iterator
- **SpoutExtractor**: Streaming spreadsheet extractor using the vendored Spout
  library
- **SpreadsheetExtractor**: In-memory spreadsheet extractor using PhpSpreadsheet
- **ActiveQueryExtractor**: Database extractor using ActiveQuery
- **FileFinderExtractor**: Filesystem extractor using Flysystem
- **Chunk_Transformer**: Transformer that batches items into fixed-size arrays
- **Filter_Transformer**: Transformer that yields only items passing a callback
  predicate
- **Mapping_Transformer**: Transformer that remaps array keys via configuration
- **Preview_Loader**: Loader that prints data for debugging
- **Visualize_Loader**: Loader that outputs data in JSON or object format
- **SpoutLoader**: Streaming spreadsheet writer using the vendored Spout library
- **SpreadsheetLoader**: In-memory spreadsheet writer using PhpSpreadsheet
- **DataFrame_Trait**: Trait providing Iterator get/set on the pipeline
- **CallableDataFrame_Trait**: Trait bridging closures to Iterator processing
  with Signal support
- **Macroable_Trait**: Trait enabling runtime method extension via macros and
  mixins
- **Test_Class**: A PHPUnit test class with `#[Test]` attribute methods

## Requirements

### Requirement 1: DataFlow Pipeline Orchestration Tests

**User Story:** As a developer, I want comprehensive tests for the DataFlow
class, so that I can verify the pipeline correctly chains extractors,
transformers, and loaders.

#### Acceptance Criteria

1. WHEN a DataFlow is constructed, THE Test_Suite SHALL verify that the pipeline
   has no dataframe initially (null state)
2. WHEN `from()` is called with an array, THE Test_Suite SHALL verify that the
   pipeline wraps the array in an IterableExtractor and produces the expected
   Iterator
3. WHEN `from()` is called with a Closure, THE Test_Suite SHALL verify that the
   closure is wrapped in a CallableProcessor and produces the expected Iterator
4. WHEN `from()` is called with another DataFlow instance, THE Test_Suite SHALL
   verify that the source pipeline's dataframe is adopted
5. WHEN `transform()` is called with a Closure, THE Test_Suite SHALL verify that
   each item is transformed by the closure
6. WHEN `transform()` is called with a Processor instance, THE Test_Suite SHALL
   verify that the processor's `__invoke` is used
7. WHEN `filter()` is called with a predicate Closure, THE Test_Suite SHALL
   verify that only items satisfying the predicate are yielded
8. WHEN `map()` is called with a mapping array, THE Test_Suite SHALL verify that
   output keys match the mapping configuration
9. WHEN `setNewMap()` is called with a mapping array, THE Test_Suite SHALL
   verify that a new dataframe is created containing only mapped keys
10. WHEN `chunk()` is called with a chunk size, THE Test_Suite SHALL verify that
    items are batched into arrays of the specified size
11. WHEN `limit()` is called with a count, THE Test_Suite SHALL verify that
    iteration stops after the specified number of items
12. WHEN `load()` is called with a Closure, THE Test_Suite SHALL verify that
    each item passes through the loader closure
13. WHEN `preview()` is called with max less than or equal to zero, THE
    Test_Suite SHALL verify that a DataFlowException is thrown
14. WHEN `run()` is called on a fully configured pipeline, THE Test_Suite SHALL
    verify that all stages execute and produce the expected output
15. WHEN multiple extractors are passed to `from()`, THE Test_Suite SHALL verify
    that all sources are chained sequentially
16. WHEN multiple transformers are passed to `transform()`, THE Test_Suite SHALL
    verify that transformations are applied in order

### Requirement 2: CallableProcessor Tests

**User Story:** As a developer, I want tests for CallableProcessor, so that I
can verify closure wrapping and invocation behavior.

#### Acceptance Criteria

1. WHEN a valid callable is provided to CallableProcessor, THE Test_Suite SHALL
   verify that the processor is constructed without error
2. WHEN an invalid (non-callable) value is provided to CallableProcessor, THE
   Test_Suite SHALL verify that an InvalidCallableException is thrown
3. WHEN CallableProcessor is invoked with a dataframe Iterator, THE Test_Suite
   SHALL verify that the callback processes each item via the CallableDataFrame
   trait
4. WHEN the callback returns a transformed value, THE Test_Suite SHALL verify
   that the output Iterator yields the transformed value
5. WHEN the callback returns Signal::Next, THE Test_Suite SHALL verify that the
   current item is skipped
6. WHEN the callback returns Signal::Stop, THE Test_Suite SHALL verify that
   iteration halts immediately

### Requirement 3: Payload Container Tests

**User Story:** As a developer, I want tests for the Payload class, so that I
can verify attribute storage, access, and reset behavior.

#### Acceptance Criteria

1. WHEN a Payload is constructed with initial attributes, THE Test_Suite SHALL
   verify that all attributes are accessible via property syntax
2. WHEN a property is set on a Payload instance, THE Test_Suite SHALL verify
   that the value is stored and retrievable
3. WHEN `__isset()` is called on an existing attribute, THE Test_Suite SHALL
   verify that it returns true
4. WHEN `__isset()` is called on a non-existing attribute, THE Test_Suite SHALL
   verify that it returns false
5. WHEN `__unset()` is called on an attribute, THE Test_Suite SHALL verify that
   the attribute is removed
6. WHEN `offsetExists()` is called with a string key, THE Test_Suite SHALL
   verify correct ArrayAccess behavior
7. WHEN `offsetGet()` is called with a non-string key, THE Test_Suite SHALL
   verify that null is returned
8. WHEN `offsetSet()` is called with a string key, THE Test_Suite SHALL verify
   that the value is stored
9. WHEN `getAttribute()` is called, THE Test_Suite SHALL verify that it returns
   the attribute value or null for missing keys
10. WHEN `reset()` is called, THE Test_Suite SHALL verify that all attributes
    revert to the initial constructor state

### Requirement 4: Signal Enum Tests

**User Story:** As a developer, I want tests for the Signal enum, so that I can
verify flow control values and their integration with the pipeline.

#### Acceptance Criteria

1. THE Test_Suite SHALL verify that Signal::Next has integer value 1
2. THE Test_Suite SHALL verify that Signal::Stop has integer value 9
3. WHEN Signal::Next is returned from a callback in CallableDataFrame, THE
   Test_Suite SHALL verify that the current item is skipped and iteration
   continues
4. WHEN Signal::Stop is returned from a callback in CallableDataFrame, THE
   Test_Suite SHALL verify that iteration halts

### Requirement 5: IterableExtractor Tests

**User Story:** As a developer, I want tests for IterableExtractor, so that I
can verify extraction from arrays and Traversable objects.

#### Acceptance Criteria

1. WHEN an array is provided to IterableExtractor, THE Test_Suite SHALL verify
   that it is converted to an ArrayIterator
2. WHEN a Traversable object is provided to IterableExtractor, THE Test_Suite
   SHALL verify that it is accepted without conversion
3. WHEN a non-iterable value is provided to IterableExtractor, THE Test_Suite
   SHALL verify that an Exception is thrown
4. WHEN IterableExtractor is invoked, THE Test_Suite SHALL verify that the
   returned Iterator yields all items from the source
5. WHEN an empty array is provided to IterableExtractor, THE Test_Suite SHALL
   verify that the returned Iterator yields no items

### Requirement 6: SpoutExtractor Tests

**User Story:** As a developer, I want tests for SpoutExtractor, so that I can
verify streaming spreadsheet reading behavior.

#### Acceptance Criteria

1. WHEN a valid spreadsheet file path is provided, THE Test_Suite SHALL verify
   that SpoutExtractor constructs without error
2. WHEN an invalid file path is provided, THE Test_Suite SHALL verify that an
   ExtractorException is thrown
3. WHEN `withoutHeaders()` is called, THE Test_Suite SHALL verify that the
   extractor reads data without treating the first row as headers
4. WHEN `sheet()` is called with a sheet name, THE Test_Suite SHALL verify that
   only the specified sheet is read
5. WHEN SpoutExtractor is invoked on a multi-sheet file without specifying a
   sheet, THE Test_Suite SHALL verify that all sheets are read sequentially

### Requirement 7: SpreadsheetExtractor Tests

**User Story:** As a developer, I want tests for SpreadsheetExtractor, so that I
can verify in-memory spreadsheet reading behavior.

#### Acceptance Criteria

1. WHEN a valid file path is provided, THE Test_Suite SHALL verify that
   SpreadsheetExtractor loads the spreadsheet
2. WHEN a non-existent file path is provided, THE Test_Suite SHALL verify that
   an Exception is thrown
3. WHEN `headers()` is called with custom headers, THE Test_Suite SHALL verify
   that output rows use the provided headers as keys
4. WHEN no custom headers are set, THE Test_Suite SHALL verify that the first
   row is used as headers
5. WHEN `sheet()` is called with a sheet name, THE Test_Suite SHALL verify that
   the specified sheet is read
6. WHEN SpreadsheetExtractor is constructed with null file path, THE Test_Suite
   SHALL verify that it reads file paths from the incoming dataframe

### Requirement 8: ActiveQueryExtractor Tests

**User Story:** As a developer, I want tests for ActiveQueryExtractor, so that I
can verify database extraction behavior with mocked queries.

#### Acceptance Criteria

1. WHEN an ActiveQuery mock is provided, THE Test_Suite SHALL verify that
   ActiveQueryExtractor constructs without error
2. WHEN `size()` is called, THE Test_Suite SHALL verify that the batch size is
   passed to the query's `each()` method
3. WHEN `toArray()` is called, THE Test_Suite SHALL verify that results are
   output as arrays
4. WHEN the `make()` factory method is called, THE Test_Suite SHALL verify that
   it returns a configured instance with the specified size
5. WHEN ActiveQueryExtractor is invoked, THE Test_Suite SHALL verify that it
   yields all rows from the query collection

### Requirement 9: FileFinderExtractor Tests

**User Story:** As a developer, I want tests for FileFinderExtractor, so that I
can verify filesystem listing behavior.

#### Acceptance Criteria

1. WHEN FileFinderExtractor is constructed with a directory path, THE Test_Suite
   SHALL verify that the path is normalized with a trailing separator
2. WHEN `recursive()` is called, THE Test_Suite SHALL verify that subdirectories
   are traversed
3. WHEN `fileOnly()` is called, THE Test_Suite SHALL verify that only file
   entries are yielded
4. WHEN `directoryOnly()` is called, THE Test_Suite SHALL verify that only
   directory entries are yielded
5. WHEN neither filter is set, THE Test_Suite SHALL verify that both files and
   directories are yielded

### Requirement 10: Chunk Transformer Tests

**User Story:** As a developer, I want tests for the Chunk transformer, so that
I can verify batching behavior with various sizes and inputs.

#### Acceptance Criteria

1. WHEN Chunk is invoked with a dataframe, THE Test_Suite SHALL verify that
   items are grouped into arrays of the specified chunk size
2. WHEN the total item count is not evenly divisible by chunk size, THE
   Test_Suite SHALL verify that the last chunk contains the remaining items
3. WHEN chunk size equals the total item count, THE Test_Suite SHALL verify that
   a single chunk is yielded
4. WHEN chunk size is larger than the total item count, THE Test_Suite SHALL
   verify that a single chunk containing all items is yielded
5. WHEN a null dataframe is provided, THE Test_Suite SHALL verify that no items
   are yielded
6. FOR ALL positive chunk sizes and non-empty input arrays, THE Test_Suite SHALL
   verify that the total number of items across all chunks equals the original
   input count (invariant property)

### Requirement 11: Filter Transformer Tests

**User Story:** As a developer, I want tests for the Filter transformer, so that
I can verify predicate-based filtering behavior.

#### Acceptance Criteria

1. WHEN Filter is invoked with a predicate that returns true for all items, THE
   Test_Suite SHALL verify that all items are yielded
2. WHEN Filter is invoked with a predicate that returns false for all items, THE
   Test_Suite SHALL verify that no items are yielded
3. WHEN Filter is invoked with a selective predicate, THE Test_Suite SHALL
   verify that only matching items are yielded
4. WHEN a null dataframe is provided, THE Test_Suite SHALL verify that no items
   are yielded
5. THE Test_Suite SHALL verify that Filter preserves the original keys from the
   dataframe
6. FOR ALL input arrays and filter predicates, THE Test_Suite SHALL verify that
   the filtered output count is less than or equal to the input count (
   metamorphic property)

### Requirement 12: Mapping Transformer Tests

**User Story:** As a developer, I want tests for the Mapping transformer, so
that I can verify field remapping behavior.

#### Acceptance Criteria

1. WHEN Mapping is invoked with string-to-string mappings, THE Test_Suite SHALL
   verify that values are copied from source keys to destination keys
2. WHEN Mapping is invoked with callable mappings, THE Test_Suite SHALL verify
   that the callable result is used as the destination value
3. WHEN a source key does not exist in the input row, THE Test_Suite SHALL
   verify that the mapping value itself is used as a literal default
4. WHEN `newDataFrame()` is called before invocation, THE Test_Suite SHALL
   verify that output rows contain only the mapped keys
5. WHEN `newDataFrame()` is not called, THE Test_Suite SHALL verify that output
   rows retain original keys plus mapped keys
6. WHEN a null dataframe is provided, THE Test_Suite SHALL verify that no items
   are yielded

### Requirement 13: Preview Loader Tests

**User Story:** As a developer, I want tests for the Preview loader, so that I
can verify that data is output for debugging.

#### Acceptance Criteria

1. WHEN Preview is invoked with a dataframe, THE Test_Suite SHALL verify that
   each item produces printed output
2. WHEN the data item is an Iterator, THE Test_Suite SHALL verify that nested
   rows are printed individually
3. WHEN Preview processes items, THE Test_Suite SHALL verify that Signal::Next
   is returned for each item (data passes through)

### Requirement 14: Visualize Loader Tests

**User Story:** As a developer, I want tests for the Visualize loader, so that I
can verify JSON and object output formatting.

#### Acceptance Criteria

1. WHEN Visualize is constructed with FORMAT_JSON, THE Test_Suite SHALL verify
   that array data is output as JSON strings
2. WHEN Visualize is constructed with FORMAT_OBJ, THE Test_Suite SHALL verify
   that data is output via var_dump
3. WHEN Visualize processes items, THE Test_Suite SHALL verify that items are
   yielded through (passthrough behavior)
4. WHEN the data item is an Iterator, THE Test_Suite SHALL verify that nested
   rows are output and yielded individually

### Requirement 15: SpoutLoader Tests

**User Story:** As a developer, I want tests for SpoutLoader, so that I can
verify streaming spreadsheet writing behavior.

#### Acceptance Criteria

1. WHEN a valid file path is provided, THE Test_Suite SHALL verify that
   SpoutLoader constructs and creates the output file
2. WHEN an invalid file path is provided, THE Test_Suite SHALL verify that a
   LoaderException is thrown
3. WHEN `withHeaders()` is called, THE Test_Suite SHALL verify that headers are
   written as the first row with bold styling
4. WHEN `withoutHeaders()` is called, THE Test_Suite SHALL verify that automatic
   header detection is disabled
5. WHEN SpoutLoader is invoked with associative array data, THE Test_Suite SHALL
   verify that headers are auto-detected from array keys
6. WHEN non-array data is provided, THE Test_Suite SHALL verify that an
   UnsupportedTypeException is thrown

### Requirement 16: SpreadsheetLoader Tests

**User Story:** As a developer, I want tests for SpreadsheetLoader, so that I
can verify in-memory spreadsheet writing behavior.

#### Acceptance Criteria

1. WHEN SpreadsheetLoader is constructed with a file path, THE Test_Suite SHALL
   verify that the path and extension are parsed correctly
2. WHEN `append()` is called, THE Test_Suite SHALL verify that the timestamp is
   not appended to the filename
3. WHEN `sheet()` is called with a name, THE Test_Suite SHALL verify that data
   is written to the specified sheet
4. WHEN SpreadsheetLoader is invoked with array data, THE Test_Suite SHALL
   verify that rows are added to the spreadsheet
5. WHEN SpreadsheetLoader is invoked with Iterator data, THE Test_Suite SHALL
   verify that rows are written in batches of 10

### Requirement 17: DataFrame Trait Tests

**User Story:** As a developer, I want tests for the DataFrame trait, so that I
can verify Iterator storage and retrieval.

#### Acceptance Criteria

1. WHEN `setDataFrame()` is called with an Iterator, THE Test_Suite SHALL verify
   that `getDataFrame()` returns the same Iterator
2. WHEN `setDataFrame()` is called with null, THE Test_Suite SHALL verify that
   `getDataFrame()` returns null
3. THE Test_Suite SHALL verify that `setDataFrame()` returns the instance for
   fluent chaining

### Requirement 18: CallableDataFrame Trait Tests

**User Story:** As a developer, I want tests for the CallableDataFrame trait, so
that I can verify closure-to-Iterator bridging with Signal support.

#### Acceptance Criteria

1. WHEN `call()` is invoked with a dataframe and a closure that returns
   transformed data, THE Test_Suite SHALL verify that the output Iterator yields
   transformed items
2. WHEN the closure returns Signal::Next, THE Test_Suite SHALL verify that the
   item is skipped
3. WHEN the closure returns Signal::Stop, THE Test_Suite SHALL verify that
   iteration halts
4. WHEN the closure returns an Iterator, THE Test_Suite SHALL verify that the
   Iterator items are yielded via `yield from`
5. WHEN the closure returns null, THE Test_Suite SHALL verify that the original
   data item is yielded
6. WHEN the closure invokes the error callback with a message, THE Test_Suite
   SHALL verify that a DataFlowException is thrown with that message
7. WHEN `call()` is invoked with a null dataframe, THE Test_Suite SHALL verify
   that no items are yielded

### Requirement 19: Macroable Trait Tests

**User Story:** As a developer, I want tests for the Macroable trait, so that I
can verify runtime method extension via macros and mixins.

#### Acceptance Criteria

1. WHEN `macro()` is called with a name and Closure, THE Test_Suite SHALL verify
   that the method becomes callable on instances
2. WHEN a macro Closure is called, THE Test_Suite SHALL verify that `$this` is
   bound to the calling instance
3. WHEN `mixin()` is called with an object, THE Test_Suite SHALL verify that all
   public and protected methods are registered as macros
4. WHEN `mixin()` is called with `replace: false` and a macro already exists,
   THE Test_Suite SHALL verify that the existing macro is not overwritten
5. WHEN a mixin method returns a Closure, THE Test_Suite SHALL verify that the
   Closure itself is registered (not the method reference)
6. WHEN an undefined method is called without a registered macro, THE Test_Suite
   SHALL verify that a BadMethodCallException is thrown
7. WHEN a non-Closure callable is registered via `macro()`, THE Test_Suite SHALL
   verify that it is invoked via `call_user_func_array`

### Requirement 20: Exception Hierarchy Tests

**User Story:** As a developer, I want tests for the exception classes, so that
I can verify the inheritance hierarchy and instantiation.

#### Acceptance Criteria

1. THE Test_Suite SHALL verify that DataFlowException extends RuntimeException
2. THE Test_Suite SHALL verify that ExtractorException extends DataFlowException
3. THE Test_Suite SHALL verify that TransformerException extends
   DataFlowException
4. THE Test_Suite SHALL verify that LoaderException extends DataFlowException
5. THE Test_Suite SHALL verify that InvalidCallableException extends
   InvalidArgumentException
6. WHEN any exception is constructed with a message, THE Test_Suite SHALL verify
   that getMessage() returns the provided message

### Requirement 21: End-to-End Pipeline Integration Tests

**User Story:** As a developer, I want integration tests that exercise complete
pipelines, so that I can verify that stages compose correctly.

#### Acceptance Criteria

1. WHEN a pipeline extracts from an array, transforms with a closure, and loads
   into a collector, THE Test_Suite SHALL verify that the final output matches
   the expected transformation
2. WHEN a pipeline chains multiple transformers (filter, map, chunk), THE
   Test_Suite SHALL verify that transformations compose in order
3. WHEN a pipeline uses Signal::Stop in a transformer, THE Test_Suite SHALL
   verify that downstream stages receive only items before the stop
4. WHEN a pipeline uses Signal::Next in a transformer, THE Test_Suite SHALL
   verify that skipped items do not appear in the loader output
5. WHEN a pipeline uses Payload for shared state, THE Test_Suite SHALL verify
   that state is accessible across stages
6. WHEN one DataFlow is passed as source to another DataFlow, THE Test_Suite
   SHALL verify that pipelines compose correctly
7. FOR ALL non-empty input arrays and identity transformations, THE Test_Suite
   SHALL verify that the output equals the input (round-trip property)

### Requirement 22: Processor Base Class Tests

**User Story:** As a developer, I want tests for the Processor abstract class,
so that I can verify flow assignment and retrieval.

#### Acceptance Criteria

1. WHEN `setFlow()` is called with a DataFlow instance, THE Test_Suite SHALL
   verify that `getFlow()` returns the same instance
2. THE Test_Suite SHALL verify that `setFlow()` returns the processor instance
   for fluent chaining
3. THE Test_Suite SHALL verify that Processor uses the Macroable trait

---
title: Custom Processors
parent: Getting Started
nav_order: 3
---

## Create Customized ETL Processor.

Create your own ETL processor.

1) Extractor - Extract data from data source.
2) Transformer - Modify/ filter/ calculate/ translate data in the pipeline.
3) Loader - Load data into database, csv, text, etc.

### Example Customized Extractor.

```php
use App\Model\User;
use DateTimeImmutable;
use Simsoft\DataFlow\Extractor;
use Iterator;

/**
* Extract birthday users from database.
*/
class BirthdayUsersExtractor extends Extractor
{
    /**
    * Constructor.
    * @param DateTimeImmutable $dob
    */
    public function __construct(protected DateTimeImmutable $dob)
    {

    }

    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        // query birthday users from database.
        yield from User::find()
            ->where('birthday', $this->dob->format('Y-m-d'))
            ->get();
    }
}
```

### Example Customized Transformer.
```php
use Simsoft\DataFlow\Transformer;
use Iterator;

/**
* Prepare birthday greeting message.
 */
class BirthdayGreetingTransformer extends Transformer
{
    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        // Expecting iterable $model from extractor.
        foreach ($dataFrame as $model) {

            $name = ucwords("$model->first_name $model->last_name");

            yield (object) [
                'headers' => 'From: admin@domain.com',
                'email' => $model->email,
                'subject' => "Happy Birthday, $name",
                'message' => <<<GREETING
Dear $name,
Wishing you a wonderful birthday filled with happiness and joy!
Have an amazing day!

Best regards
Admin
GREETING,
            ];
        }
    }
}
```

### Example Customized Loader.

```php
use Simsoft\DataFlow\Loader;
use Iterator;

/**
* Mail birthday greeting to user.
 */
class EmailMessageLoader extends Loader
{
    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        // Expecting iterable $mail object from transformer.
        foreach ($dataFrame as $mail) {
            if (mail($mail->email, $mail->subject, $mail->message, $mail->headers)) {

                echo "Mail to $mail->email sent successfully." . PHP_EOL;

                continue;
            }

            yield $mail; // passing failed email to next processor.
            error_log("Mail birthday greeting to $mail->email failed.");
        }
    }
}
```

## Example Usage of Customized ETL Processor.

```php
use App\Model\FailedEmail;
use Simsoft\DataFlow\DataFlow;

try {

    (new DataFlow())
        ->from(new BirthdayUsersExtractor(date_create()))   // Retrieve birthday users from extractor.
        ->transform(new BirthdayGreetingTransformer())      // Preparing birthday greeting message with transformer.
        ->load(new EmailMessageLoader())                    // Delivery birthday greeting with loader.
        ->load(function($email) {                           // Record failed emails.
            $model = new FailedEmail();
            $model->email = serialize($email);
            $model->save();
        })
        ->run();

} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
}

// Output:
// Mail to johndoe@email.com sent successfully.
// Mail to janedoe@email.com sent successfully.
// Mail to peter@email.com sent successfully.
// Mail to philiip@email.com sent successfully.
```

## Error-Resilient Processors

Custom processors can be configured with error strategies at the call site.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

(new DataFlow())
    ->from(new BirthdayUsersExtractor(date_create()))
    ->transform(
        (new BirthdayGreetingTransformer())
            ->withErrorStrategy(ErrorStrategy::Skip)
            ->withName('greeting-builder')
    )
    ->load(
        (new EmailMessageLoader())
            ->withRetry(maxAttempts: 3, delay: 1000)
            ->withName('email-sender')
    )
    ->run();
```

Failed emails will be retried up to 3 times with 1-second backoff. If all
retries fail, the row is recorded in the dead-letter collection.

## Dry-Run Aware Loaders

Custom loaders can check `$this->isDryRun()` to skip side effects during dry-run
mode.

```php
use Simsoft\DataFlow\Loader;
use Iterator;

class EmailMessageLoader extends Loader
{
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        foreach ($dataFrame as $mail) {
            if (!$this->isDryRun()) {
                mail($mail->email, $mail->subject, $mail->message, $mail->headers);
            }

            yield $mail;
        }
    }
}

// Usage: validate the pipeline without sending emails
$result = (new DataFlow())
    ->from(new BirthdayUsersExtractor(date_create()))
    ->transform(new BirthdayGreetingTransformer())
    ->load(new EmailMessageLoader())
    ->dryRun()
    ->run();

echo "Would send {$result->getProcessedRows()} emails\n";
```

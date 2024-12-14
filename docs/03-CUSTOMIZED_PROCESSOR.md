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

                // Using info() method to output message.
                $this->info("Mail to $email->email sent successfully.");

                continue;
            }

            yield $email; // passing failed email to next processor.
            error_log("Mail birthday greeting to $email->email failed.");
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

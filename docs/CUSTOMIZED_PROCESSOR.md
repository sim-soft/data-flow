## Create Customized ETL Processor.

Create your own ETL processor.

1) Extractor - Extract data from data source.
2) Transformer - Modify/ filter/ calculate/ translate data in the pipeline.
3) Loader - Load data into database, csv, text, etc.

### Example Customized Extractor.

```php
use App\Model\User;
use Simsoft\DataFlow\Extractor;
use Iterator;

/**
* Extract birthday users from database.
*/
class BirthdayUsersExtractor extends Extractor
{
    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        // query birthday users from database.
        yield from User::find()
            ->where('birthday', date_create()->format('Y-m-d'))
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
        foreach ($dataFrame as $mail) {
            if (mail($mail->email, $mail->subject, $mail->message, $mail->headers)) {
                $this->info("Mail to $email->email sent successfully.");
                continue;
            }

            error_log("Mail birthday greeting to $email->email failed.");
        }
    }
}
```

## Example Usage of Customized ETL Processor.

```php
use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from(new BirthdayUsersExtractor())            // use your custom extractor.
    ->transform(new BirthdayGreetingTransformer())  // use your custom transformer.
    ->load(new EmailMessageLoader())                     // use your custom loader.
    ->run();

// Output:
// Mail to EMAIL sent successfully.
// Mail to EMAIL sent successfully.
// Mail to EMAIL sent successfully.
// Mail to EMAIL sent successfully.
```

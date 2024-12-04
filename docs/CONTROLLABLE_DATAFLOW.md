# Controllable Data Flow

Create controllable/ reusable data flow.

## Example:

Create UserReminderFlow.

```php
namespace App\ETL;

use App\Model\User;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Extractors\ActiveQueryExtractor;
use Simsoft\DataFlow\Loaders\SpreadsheetLoader;

class UserReminderFlow extends DataFlow
{
    /**
    * Find all inactive users.
    * @return $this
    */
    public function fromInactiveUsers(): static
    {
        $query = User::find()->where('status', 'inactive');
        return $this->from(new ActiveQueryExtractor($query));
    }

    /**
    * Find all users that are expiring on a specific date.
    * @param DateTimeImmutable $expiryDate
    * @return $this
    */
    public function fromExpiringUsers(DateTimeImmutable $expiryDate): static
    {
        $query = User::find()->where('expiry_date', $expiryDate->format('Y-m-d'));
        return $this->from(new ActiveQueryExtractor($query));
    }

    /**
    * Unify data format to be output to xlsx.
    * @return $this
    */
    public function toFileFormat(): static
    {
        return $this->transform(function (User $user) {
            return [
                'Name' => $user->name,
                'Email' => $user->email,
                'Status' => $user->status,
                'Expiry Date' => $user->expiry_date,
            ];
        }
    }

    /**
    * @param string $filePath
    * @return $this
    */
    public function exportToFile(string $filePath, string $sheetName): static
    {
        return $this->load((new SpreadsheetLoader($filePath))->sheet($sheetName));
    }
}
```

## Usage Demo

```php
use App\ETL\UserReminderFlow;
use Throwable;

try {

    $userType = 'expiring';
    $expiryDate = new DateTimeImmutable('2024-12-25');
    $output = "path/to/{$userType}_users_reminder_list.xlsx";

    $flow = new UserReminderFlow();

    match($userType) { // decide which type of users to export.
        'expiring' => $flow->fromExpiringUsers($expiryDate),
        'inactive' => $flow->fromInactiveUsers(),
        default => throw new Exception('Invalid user type'),
    };

    $flow
        ->toFileFormat()
        ->exportToFile($output, "$userType users")
        ->run();

} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
}
```

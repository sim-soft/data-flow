Hello,

I encountered an issue with the following code:

```phpt
$validator = new Validator();
if($validator->validate()) {
    echo 'Valid';
} else {
    echo 'Invalid';
    $validator->getErrors();
}
```

Repository version: PUT HERE YOUR repository VERSION (exact version)

PHP version: PUT HERE YOUR PHP VERSION

I expected to get:

```phpt
Valid
```

But I actually get:

```phpt
errors
```

Thanks!

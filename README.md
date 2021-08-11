# Introduction
mysql data dictionary export

# Install
```
composer require selfim/data_dictionary @dev
```
# Demo
```
require __DIR__ . '/vendor/autoload.php';
$result = DataDict::init(['dbname'=>'your database','host'=>'your host','user'=>'username','password'=>'password'])
    ->make('html');
print_r($result);
```
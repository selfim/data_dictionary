# Introduction
mysql data dictionary export

# Install
```
composer require selfim/data_dictionary @dev
```
# Demo
```
require __DIR__ . '/vendor/autoload.php';
$dataObj = new DataDict(['dbname'=>'xxx','host'=>'localhost','user'=>'root','password'=>'xxx']);
$res = $dataObj->make('html');
```